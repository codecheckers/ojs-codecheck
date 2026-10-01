<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiUpdateException;
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
    private CodecheckPostOrigin $origin;
    private int $submissionId;
    private string $githubPAT;
    private string $githubRegisterOrganization;
    private string $githubRegisterRepository;
    private array $updateInformation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submissionId = 0;
        $this->githubPAT = 'testtoken123';
        $this->githubRegisterOrganization = 'codecheckers';
        $this->githubRegisterRepository = 'testing-dev-register';
        $this->origin = new CodecheckPostOrigin(
            'Example journal',
            'https://journal.example/index.php/example',
            '3.5.0.3',
            '0.1.0.0',
            'Signed by {$journal}'
        );
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
            $this->origin
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
            $this->origin
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
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
            $this->origin,
            $clientMock
        );

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertFalse($parser->hasSeenLabelledIssues());
    }

    /**
     * Read off the issue rather than from the labels endpoint, which answers one
     * unpaginated page — where "absent" and "not on this page" read alike.
     */
    public function testGetIssueLabelsReadsTheNamesOffTheIssue()
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->expects($this->once())
            ->method('show')
            ->with($this->githubRegisterOrganization, $this->githubRegisterRepository, 190)
            ->willReturn([
                'number' => 190,
                'labels' => [
                    ['name' => 'id assigned'],
                    ['name' => 'work in progress'],
                    ['name' => 'journal'],
                ],
            ]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->origin,
            $clientMock
        );

        $this->assertSame(
            ['id assigned', 'work in progress', 'journal'],
            $parser->getIssueLabels(190)
        );
    }

    /**
     * Adding is not replacing: a register issue carries labels nobody here owns,
     * and `replace` would wipe them (#174).
     */
    public function testAddLabelsToIssueAddsAndNeverReplaces()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->once())
            ->method('add')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                190,
                ['work in progress']
            );
        $labelsApiMock->expects($this->never())->method('replace');
        $labelsApiMock->expects($this->never())->method('clear');

        $this->clientWithLabelsApi($labelsApiMock)->addLabelsToIssue(190, ['work in progress']);
    }

    public function testAddLabelsToIssueDoesNothingWithoutLabels()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->never())->method('add');

        $this->clientWithLabelsApi($labelsApiMock)->addLabelsToIssue(190, []);
    }

    public function testRemoveLabelFromIssueRemovesThatOneLabel()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->once())
            ->method('remove')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                190,
                'needs codechecker'
            );
        $labelsApiMock->expects($this->never())->method('clear');

        $this->clientWithLabelsApi($labelsApiMock)->removeLabelFromIssue(190, 'needs codechecker');
    }

    public function testALabelChangeGitHubRefusesIsReportedAsAnUpdateFailure()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->method('remove')->willThrowException(new \Exception('Forbidden', 403));

        $this->expectException(ApiUpdateException::class);

        $this->clientWithLabelsApi($labelsApiMock)->removeLabelFromIssue(190, 'needs codechecker');
    }

    /**
     * GitHub answers 404 when the label is not on the issue, which is the state
     * the removal asked for. Treating that as a failure would make the sync
     * depend on nobody else having touched the issue meanwhile (#174).
     */
    public function testRemovingALabelThatIsNotThereIsNotAFailure()
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->once())
            ->method('remove')
            ->willThrowException(new \Exception('Label does not exist', 404));

        // The assertion is that this does not throw.
        $this->clientWithLabelsApi($labelsApiMock)->removeLabelFromIssue(190, 'needs codechecker');
    }

    /**
     * Updating a register issue must not write the `labels` array: that replaced
     * everything on the issue, so pressing "update issue" wiped the status
     * labels, `buddy exchange`, `help welcome` and anything else a human had
     * added. The labels the form offers are added instead (#174).
     */
    public function testUpdatingAnIssueAddsLabelsAndNeverReplacesThem()
    {
        $certMock = $this->createMock(CertificateIdentifier::class);
        $certMock->method('toStr')->willReturn('2025-001');

        $collectionMock = $this->createMock(UniqueArray::class);
        $collectionMock->method('toArray')->willReturn(['institution', 'check-nl']);
        $issueLabelsMock = $this->createMock(CodecheckIssueLabels::class);
        $issueLabelsMock->method('get')->willReturn($collectionMock);

        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->expects($this->once())
            ->method('add')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                190,
                ['id assigned', 'institution', 'check-nl']
            );
        $labelsApiMock->expects($this->never())->method('replace');

        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('labels')->willReturn($labelsApiMock);
        $issueApiMock->expects($this->once())
            ->method('update')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                190,
                $this->callback(fn ($contents) => !array_key_exists('labels', $contents))
            )
            ->willReturn(['html_url' => 'https://github.com/x/y/issues/190', 'number' => 190]);

        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->origin,
            $clientMock
        );

        $parser->updateIssue(
            $this->updateInformation,
            190,
            $certMock,
            $issueLabelsMock,
            'Some Paper',
            'Daniel Nüst et al.',
            [],
            []
        );
    }

    /** A client whose issue-labels API is the given mock. */
    private function clientWithLabelsApi(\Github\Api\Issue\Labels $labelsApiMock): CodecheckGithubRegisterApiClient
    {
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('labels')->willReturn($labelsApiMock);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        return new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->origin,
            $clientMock
        );
    }

    /** A client whose label probe fails with the given exception. */
    private function clientWhoseLabelProbeThrows(\Throwable $e): CodecheckGithubRegisterApiClient
    {
        $labelsApiMock = $this->createMock(\Github\Api\Issue\Labels::class);
        $labelsApiMock->method('show')->willThrowException($e);

        return $this->clientWithLabelsApi($labelsApiMock);
    }

    /** Every status comment says where it came from. */
    public function testAStatusCommentEndsWithTheSignature()
    {
        $commentsApiMock = $this->createMock(\Github\Api\Issue\Comments::class);
        $commentsApiMock->expects($this->once())
            ->method('create')
            ->with(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                190,
                ['body' => "The CODECHECK status changed to: completed\n\n---\nSigned by Example journal"]
            );
        $issueApiMock = $this->createMock(\Github\Api\Issue::class);
        $issueApiMock->method('comments')->willReturn($commentsApiMock);
        $clientMock = $this->createMock(\Github\Client::class);
        $clientMock->method('api')->with('issue')->willReturn($issueApiMock);

        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->origin,
            $clientMock
        );

        $parser->commentOnIssue(190, 'The CODECHECK status changed to: completed');
    }

    /** The deposit's pull request is signed below its table. */
    public function testTheDepositPullRequestBodyEndsWithTheSignature()
    {
        $parser = new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $this->submissionId,
            $this->origin
        );
        $method = new \ReflectionMethod(CodecheckGithubRegisterApiClient::class, 'buildDepositPrBody');
        $method->setAccessible(true);

        $body = $method->invoke($parser, ['Certificate' => '2026-001', 'Issue' => '12']);

        $this->assertStringContainsString("| Certificate | 2026-001 |\n", $body);
        $this->assertStringEndsWith("|\n\n\n---\nSigned by Example journal", $body);
    }
}
