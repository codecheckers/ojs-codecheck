<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CodecheckGithubRegisterApiClientUnitTest.php
 *
 * @class CodecheckGithubRegisterApiClientUnitTest
 *
 * @brief Tests for the CodecheckGithubRegisterApiClient class
 */
class CodecheckGithubRegisterApiClientUnitTest extends PKPTestCase
{
    private \APP\journal\Journal $journal;
    private int $submissionId;
    private string $githubRegisterRepository;
    private string $journalName;
    
    protected function setUp(): void
	{
		parent::setUp();
        $this->submissionId = 0;
        $this->githubRegisterRepository = 'testing-dev-register';
        $this->journalName = 'Example journal';
        $this->journal = $this->createMock(\APP\journal\Journal::class);
        $this->journal->method('getLocalizedName')->willReturn($this->journalName);
	}

    public function testGithubRegisterClientGetEmptyLabels()
    {
        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal
        );

        $this->assertSame([], $apiParser->getLabels()->toArray());
    }

    public function testGithubRegisterClientGetEmptyLabelsUnknownJournal()
    {
        $unknownJournal = null;

        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $unknownJournal
        );

        $this->assertSame([], $apiParser->getLabels()->toArray());
    }

    public function testGithubRegisterClientGetEmptyIssues()
    {
        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal
        );

        $this->assertSame($apiParser->getIssues(), []);
    }

    public function testGithubRegisterClientFetchIssues()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturn([
            ['title' => 'Alice | 2025-001'],
            ['title' => 'Issue without a certificate Identifier'],
        ]);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);
        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );
        $apiParser->fetchIssues();
        $issues = $apiParser->getIssues();

        $this->assertCount(1, $issues);
        $this->assertEquals('Alice | 2025-001', $issues[0]['title']);
    }

    public function testGithubRegisterClientFetchLabels()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->method('all')->willReturn([
            ['name' => 'institution'],
            ['name' => 'check-nl'],
        ]);
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('labels')->willReturn($labelsApiMock);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );
        $parser->fetchLabels();
        $labels = $parser->getLabels()->toArray();

        $this->assertCount(2, $labels);
        $this->assertContains('institution', $labels);
        $this->assertContains('check-nl', $labels);
    }

    public function testAddIssueCreatesIssueAndReturnsUrl()
    {
        $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'] = 'testtoken123';

        $certMock = $this->createMock(CertificateIdentifier::class);
        $certMock->method('toStr')
            ->willReturn('2025-001');

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $expectedBody = 'Journal: `' . $this->journalName . '`<br />'
              . 'Submission ID: `' . $this->submissionId . '`';

        $issueApiMock->expects($this->once())
            ->method('create')
            ->with(
                'codecheckers',
                $this->githubRegisterRepository,
                [
                    'title'  => 'Daniel Nüst et al. | 2025-001',
                    'body'   => $expectedBody,
                    'labels' => ['id assigned', 'institution', 'check-nl']
                ]
            )
            ->willReturn([
                'html_url' => 'https://github.com/codecheckers/testing-dev-register/issues/123'
            ]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->expects($this->once())->method('authenticate')->with('testtoken123', null, \Github\Client::AUTH_ACCESS_TOKEN);

        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $url = $parser->addIssue(
            $certMock,
            'institution',
            'check-nl',
            'Daniel Nüst et al.'
        );

        $this->assertEquals(
            'https://github.com/codecheckers/testing-dev-register/issues/123',
            $url
        );
    }

    public function testAddIssueCreatesIssueAndThrowsException()
    {
        $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'] = 'testtoken123';

        $certificateIdentifier = '2025-001';

        $certMock = $this->createMock(CertificateIdentifier::class);
        $certMock->method('toStr')->willReturn($certificateIdentifier);

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->expects($this->once())->method('create')->will($this->throwException(new ApiCreateException()));;

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->expects($this->once())->method('authenticate')->with('testtoken123', null, \Github\Client::AUTH_ACCESS_TOKEN);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $this->expectException(ApiCreateException::class);
        $this->expectExceptionMessage("Error while adding the new GitHub issue with the new Certificate Identifier: " . $certificateIdentifier . "\n");

        $parser->addIssue(
            $certMock,
            'institution',
            'check-nl',
            'Daniel Nüst et al.'
        );
    }

    public function testFetchIssuesThrowsApiFetchException()
    {
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->will($this->throwException(new ApiFetchException('')));;

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $this->expectException(ApiFetchException::class);
        $this->expectExceptionMessage("Failed fetching the GitHub Issues\n");

        $parser->fetchIssues();
    }

    public function testFetchIssuesThrowsNoMatchingIssuesFoundException()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturn([]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $this->expectException(NoMatchingIssuesFoundException::class);
        $this->expectExceptionMessage("There was no open or closed issue found with the label 'id assigned' in the GitHub Codecheck Register.");

        $parser->fetchIssues();
    }
}