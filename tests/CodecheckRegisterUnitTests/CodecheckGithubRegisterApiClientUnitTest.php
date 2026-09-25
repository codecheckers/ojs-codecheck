<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
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
    private string $githubPAT;
    private string $githubRegisterOrganization;
    private string $githubRegisterRepository;
    private string $journalName;
    private array $updateInformation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submissionId = 0;
        $this->githubPAT = 'testtoken123';
        $this->githubRegisterOrganization = 'codecheckers';
        $this->githubRegisterRepository = 'testing-dev-register';
        $this->journalName = 'Example journal';
        $this->journal = $this->createMock(\APP\journal\Journal::class);
        $this->journal->method('getLocalizedName')->willReturn($this->journalName);
        $this->updateInformation = [
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY,
        ];
    }

    public function testGithubRegisterClientGetEmptyLabels()
    {
        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
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
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $unknownJournal
        );

        $this->assertSame([], $apiParser->getLabels()->toArray());
    }

    public function testGithubRegisterClientGetEmptyIssues()
    {
        $apiParser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
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
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );
        $apiParser->fetchNewestIssues();
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
            $this->githubPAT,
            $this->githubRegisterOrganization,
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
        $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'] = $this->githubPAT;

        $codecheckers = ['Example Codechecker'];
        $repos = ['https://repo.com'];
        $paperTitle = 'Some Paper';
        $authorString = 'Daniel Nüst et al.';

        $certMock = $this->createMock(CertificateIdentifier::class);
        $certMock->method('toStr')
            ->willReturn('2025-001');

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);

        $issueLabelsMock = $this->createMock(CodecheckIssueLabels::class);

        $collectionMock = $this->createMock(UniqueArray::class);
        $collectionMock->method('toArray')->willReturn(['institution', 'check-nl']);

        $issueLabelsMock->method('get')->willReturn($collectionMock);

        $issue = new CodecheckGithubRegisterIssue(
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $certMock,
            $issueLabelsMock,
            $paperTitle,
            $this->journalName,
            $authorString,
            $this->submissionId,
            $codecheckers,
            $repos,
            $this->updateInformation,
        );

        $expectedBody = $issue->getBody();

        $issueApiMock->expects($this->once())
            ->method('create')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                [
                    'title' => 'Daniel Nüst et al. | 2025-001',
                    'body' => $expectedBody,
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
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $labels = new CodecheckIssueLabels(['institution', 'check-nl']);

        $issue = $parser->addIssue(
            $certMock,
            $labels,
            $paperTitle,
            $authorString,
            $codecheckers,
            $repos,
            $this->updateInformation,
        );

        $this->assertEquals(
            'https://github.com/codecheckers/testing-dev-register/issues/123',
            $issue['html_url']
        );
    }

    /**
     * A register repository with no matching issue is a state, not an error:
     * the reservation asks the editor whether to open the first one (#130).
     */
    public function testFetchNewestIssuesLeavesTheIssueListEmptyWhenTheRegisterHasNone()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturn([]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
    }

    /** Paging stops at the first empty page. */
    public function testFetchNewestIssuesStopsPagingWhenNoIssueCarriesAnIdentifier()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->expects($this->exactly(2))
            ->method('all')
            ->willReturnOnConsecutiveCalls(
                [['title' => 'Issue without a certificate Identifier']],
                []
            );

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
    }

    public function testRegisterHasIdAssignedLabelWhenTheLabelExists()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->once())
            ->method('show')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL
            )
            ->willReturn(['name' => Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL]);

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('labels')->willReturn($labelsApiMock);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $this->assertTrue($parser->registerHasIdAssignedLabel());
    }

    public function testRegisterHasIdAssignedLabelWhenTheLabelIsMissing()
    {
        $parser = $this->clientWhoseLabelProbeThrows(new \Exception('Not Found', 404));

        $this->assertFalse($parser->registerHasIdAssignedLabel());
    }

    /**
     * Only a 404 means the label is absent. A spent rate limit or an
     * unreachable repository must not be reported as a missing label, because
     * reserving "the first" identifier there would duplicate one that already
     * exists (#129, #130).
     */
    public function testRegisterHasIdAssignedLabelIsUnknownWhenTheRepositoryCannotBeRead()
    {
        $this->assertNull(
            $this->clientWhoseLabelProbeThrows(new \Exception('API rate limit exceeded', 403))
                ->registerHasIdAssignedLabel()
        );
        $this->assertNull(
            $this->clientWhoseLabelProbeThrows(new \Exception('no route to host'))
                ->registerHasIdAssignedLabel()
        );
    }

    /**
     * An empty identifier list is only an empty register when no issue carried
     * the label at all — labelled issues whose titles hold no readable
     * identifier are a register that must not be reserved into (#130).
     */
    public function testLabelledIssuesAreSeenEvenWhenNoTitleCarriesAnIdentifier()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturnOnConsecutiveCalls(
            [['title' => 'Community codecheck 2026-001'], ['title' => 'needs codechecker']],
            []
        );

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertTrue($parser->hasSeenLabelledIssues());
    }

    public function testAnEmptyRegisterHasSeenNoLabelledIssues()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturn([]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertFalse($parser->hasSeenLabelledIssues());
    }

    /**
     * Only the remote end decides when the pages run out, so a server that
     * ignores `page` — a caching proxy, a captive portal — would be walked for
     * ever. The walk is capped instead.
     */
    public function testFetchNewestIssuesStopsWalkingAServerThatRepeatsItself()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->expects($this->atMost(50))
            ->method('all')
            ->willReturn([['title' => 'the same page for every query']]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertTrue($parser->hasSeenLabelledIssues());
    }

    /**
     * The register's own development issues are not certificates. One carrying
     * an identifier in its title must not be taken for the register's newest,
     * which would hand the next reservation the wrong number to continue from.
     */
    public function testDevelopmentIssuesAreLeftOutOfTheIdentifiers()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturn([
            [
                'title' => 'Test the register workflow | 2099-999',
                'labels' => [['name' => 'id assigned'], ['name' => 'development']],
            ],
            [
                'title' => 'Alice | 2025-001',
                'labels' => [['name' => 'id assigned']],
            ],
        ]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();
        $issues = $parser->getIssues();

        $this->assertCount(1, $issues);
        $this->assertSame('Alice | 2025-001', $issues[0]['title']);
    }

    /**
     * A register holding nothing but development issues has no certificate to
     * continue from, so it is an empty register rather than an unreadable one.
     */
    public function testARegisterOfOnlyDevelopmentIssuesCountsAsEmpty()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('all')->willReturnOnConsecutiveCalls(
            [['title' => 'Register tooling | 2099-999', 'labels' => [['name' => 'development']]]],
            []
        );

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertFalse($parser->hasSeenLabelledIssues());
    }

    /** A client whose label probe fails with the given exception. */
    private function clientWhoseLabelProbeThrows(\Throwable $e): CodecheckGithubRegisterApiClient
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->method('show')->willThrowException($e);

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('labels')->willReturn($labelsApiMock);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        return new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->journal,
            $clientMock
        );
    }
}
