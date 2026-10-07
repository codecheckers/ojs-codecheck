<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiUpdateException;
use APP\plugins\generic\codecheck\tests\Support\GithubFake;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CodecheckGithubRegisterApiClientUnitTest.php
 *
 * @class CodecheckGithubRegisterApiClientUnitTest
 *
 * @brief Tests for the CodecheckGithubRegisterApiClient class. GitHub answers
 *   by address through GithubFake, so a test says which requests it expects
 *   and what GitHub answers, not which methods of the GitHub library are
 *   called in which order (#191).
 */
class CodecheckGithubRegisterApiClientUnitTest extends PKPTestCase
{
    private const REPO = '/repos/codecheckers/testing-dev-register';

    private CodecheckPostOrigin $origin;
    private int $submissionId;
    private string $githubPAT;
    private string $githubRegisterOrganization;
    private string $githubRegisterRepository;
    private array $updateInformation;

    protected function setUp(): void
    {
        parent::setUp();
        GithubHttp::reset();
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

    protected function tearDown(): void
    {
        // A static: left tripped, it would refuse every GitHub call in the
        // tests that follow.
        GithubHttp::reset();
        parent::tearDown();
    }

    /** A register client whose GitHub answers as the fake says. */
    private function clientOn(GithubFake $github): CodecheckGithubRegisterApiClient
    {
        return new CodecheckGithubRegisterApiClient(
            $this->githubPAT,
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            (string) $this->submissionId,
            $this->origin,
            $github->client()
        );
    }

    /** A fake whose issue list answers the given pages in turn, and empty after them. */
    private static function issuePages(array ...$pages): GithubFake
    {
        $asked = 0;

        return new GithubFake([
            'GET ' . self::REPO . '/issues' => function () use (&$asked, $pages) {
                return $pages[$asked++] ?? [];
            },
        ]);
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
        $github = self::issuePages([
            ['title' => 'Alice | 2025-001'],
            ['title' => 'Issue without a certificate Identifier'],
        ]);
        $apiParser = $this->clientOn($github);
        $apiParser->fetchNewestIssues();
        $issues = $apiParser->getIssues();

        $this->assertCount(1, $issues);
        $this->assertEquals('Alice | 2025-001', $issues[0]['title']);
        // Only the issues carrying the label are asked for, newest first.
        $github->assertSent('GET ' . self::REPO . '/issues', function ($request) {
            parse_str($request->getUri()->getQuery(), $query);

            return $query['labels'] === Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL
                && $query['sort'] === 'updated'
                && $query['direction'] === 'desc';
        });
    }

    public function testAddIssueCreatesIssueAndReturnsUrl()
    {
        $codecheckers = ['Example Codechecker'];
        $repos = ['https://repo.com'];
        $paperTitle = 'Some Paper';
        $authorString = 'Daniel Nüst et al.';

        $certMock = $this->createMock(CertificateIdentifier::class);
        $certMock->method('toStr')
            ->willReturn('2025-001');

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

        $github = new GithubFake([
            'POST ' . self::REPO . '/issues' => [
                'html_url' => 'https://github.com/codecheckers/testing-dev-register/issues/123',
            ],
        ]);

        $created = $this->clientOn($github)->addIssue(
            $certMock,
            new CodecheckIssueLabels(['institution', 'check-nl']),
            $paperTitle,
            $authorString,
            $codecheckers,
            $repos,
            $this->updateInformation,
        );

        $this->assertEquals(
            'https://github.com/codecheckers/testing-dev-register/issues/123',
            $created['html_url']
        );
        $github->assertSent('POST ' . self::REPO . '/issues', function ($request, $body) use ($issue) {
            $this->assertSame([
                'title' => 'Daniel Nüst et al. | 2025-001',
                'body' => $issue->getBody(),
                'labels' => ['id assigned', 'institution', 'check-nl'],
            ], $body);
            // The register's token goes with the request.
            $this->assertStringContainsString('testtoken123', $request->getHeaderLine('Authorization'));
        });
    }

    /**
     * A register repository with no matching issue is a state, not an error:
     * the reservation asks the editor whether to open the first one (#130).
     */
    public function testFetchNewestIssuesLeavesTheIssueListEmptyWhenTheRegisterHasNone()
    {
        $parser = $this->clientOn(self::issuePages());

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
    }

    /** Paging stops at the first empty page. */
    public function testFetchNewestIssuesStopsPagingWhenNoIssueCarriesAnIdentifier()
    {
        $github = self::issuePages([['title' => 'Issue without a certificate Identifier']], []);
        $parser = $this->clientOn($github);

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $github->assertSent('GET ' . self::REPO . '/issues', null, 2);
    }

    public function testRegisterHasIdAssignedLabelWhenTheLabelExists()
    {
        $github = new GithubFake([
            'GET ' . self::REPO . '/labels/*' => ['name' => Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL],
        ]);

        $this->assertTrue($this->clientOn($github)->registerHasIdAssignedLabel());
        $github->assertSent('GET ' . self::REPO . '/labels/id%20assigned');
    }

    public function testRegisterHasIdAssignedLabelWhenTheLabelIsMissing()
    {
        $github = new GithubFake(['GET ' . self::REPO . '/labels/*' => new Response(404, [], '{"message": "Not Found"}')]);

        $this->assertFalse($this->clientOn($github)->registerHasIdAssignedLabel());
    }

    /**
     * Only a 404 means the label is absent. A spent rate limit or an
     * unreachable repository must not be reported as a missing label, because
     * reserving "the first" identifier there would duplicate one that already
     * exists (#129, #130).
     */
    public function testRegisterHasIdAssignedLabelIsUnknownWhenTheRepositoryCannotBeRead()
    {
        $forbidden = new GithubFake(['GET ' . self::REPO . '/labels/*' => new Response(403, [], '{"message": "Resource not accessible"}')]);
        $this->assertNull($this->clientOn($forbidden)->registerHasIdAssignedLabel());

        $unreachable = new GithubFake([
            'GET ' . self::REPO . '/labels/*' => new ConnectException('no route to host', new Request('GET', 'https://api.github.com/')),
        ]);
        $this->assertNull($this->clientOn($unreachable)->registerHasIdAssignedLabel());
    }

    /**
     * An empty identifier list is only an empty register when no issue carried
     * the label at all — labelled issues whose titles hold no readable
     * identifier are a register that must not be reserved into (#130).
     */
    public function testLabelledIssuesAreSeenEvenWhenNoTitleCarriesAnIdentifier()
    {
        $parser = $this->clientOn(self::issuePages(
            [['title' => 'Community codecheck 2026-001'], ['title' => 'needs codechecker']]
        ));

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertTrue($parser->hasSeenLabelledIssues());
    }

    public function testAnEmptyRegisterHasSeenNoLabelledIssues()
    {
        $parser = $this->clientOn(self::issuePages());

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
        $github = new GithubFake(['GET ' . self::REPO . '/issues' => [['title' => 'the same page for every query']]]);
        $parser = $this->clientOn($github);

        $parser->fetchNewestIssues();

        $this->assertSame([], $parser->getIssues());
        $this->assertTrue($parser->hasSeenLabelledIssues());
        $this->assertLessThanOrEqual(50, count($github->requests()));
    }

    /**
     * The register's own development issues are not certificates. One carrying
     * an identifier in its title must not be taken for the register's newest,
     * which would hand the next reservation the wrong number to continue from.
     */
    public function testDevelopmentIssuesAreLeftOutOfTheIdentifiers()
    {
        $parser = $this->clientOn(new GithubFake(['GET ' . self::REPO . '/issues' => [
            [
                'title' => 'Test the register workflow | 2099-999',
                'labels' => [['name' => 'id assigned'], ['name' => 'development']],
            ],
            [
                'title' => 'Alice | 2025-001',
                'labels' => [['name' => 'id assigned']],
            ],
        ]]));

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
        $parser = $this->clientOn(self::issuePages(
            [['title' => 'Register tooling | 2099-999', 'labels' => [['name' => 'development']]]]
        ));

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
        $github = new GithubFake(['GET ' . self::REPO . '/issues/190' => [
            'number' => 190,
            'labels' => [
                ['name' => 'id assigned'],
                ['name' => 'work in progress'],
                ['name' => 'journal'],
            ],
        ]]);

        $this->assertSame(
            ['id assigned', 'work in progress', 'journal'],
            $this->clientOn($github)->getIssueLabels(190)
        );
        $this->assertSame(['GET ' . self::REPO . '/issues/190'], $github->addresses());
    }

    /**
     * Adding is not replacing: a register issue carries labels nobody here owns,
     * and `replace` would wipe them (#174).
     */
    public function testAddLabelsToIssueAddsAndNeverReplaces()
    {
        $github = new GithubFake(['POST ' . self::REPO . '/issues/190/labels' => []]);

        $this->clientOn($github)->addLabelsToIssue(190, ['work in progress']);

        $this->assertSame(['POST ' . self::REPO . '/issues/190/labels'], $github->addresses());
        $this->assertSame([['work in progress']], $github->bodies('POST *'));
    }

    public function testAddLabelsToIssueDoesNothingWithoutLabels()
    {
        $github = new GithubFake();

        $this->clientOn($github)->addLabelsToIssue(190, []);

        $this->assertSame([], $github->addresses());
    }

    public function testRemoveLabelFromIssueRemovesThatOneLabel()
    {
        $github = new GithubFake(['DELETE ' . self::REPO . '/issues/190/labels/*' => []]);

        $this->clientOn($github)->removeLabelFromIssue(190, 'needs codechecker');

        // That label alone: never every label on the issue.
        $this->assertSame(['DELETE ' . self::REPO . '/issues/190/labels/needs%20codechecker'], $github->addresses());
    }

    public function testALabelChangeGitHubRefusesIsReportedAsAnUpdateFailure()
    {
        $github = new GithubFake(['DELETE *' => new Response(403, [], '{"message": "Forbidden"}')]);

        $this->expectException(ApiUpdateException::class);

        $this->clientOn($github)->removeLabelFromIssue(190, 'needs codechecker');
    }

    /**
     * GitHub answers 404 when the label is not on the issue, which is the state
     * the removal asked for. Treating that as a failure would make the sync
     * depend on nobody else having touched the issue meanwhile (#174).
     */
    public function testRemovingALabelThatIsNotThereIsNotAFailure()
    {
        $github = new GithubFake(['DELETE *' => new Response(404, [], '{"message": "Label does not exist"}')]);

        // The assertion is that this does not throw.
        $this->clientOn($github)->removeLabelFromIssue(190, 'needs codechecker');

        $github->assertSent('DELETE *');
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

        $github = new GithubFake([
            // The body is read first, so that an unchanged one is not written again.
            'GET ' . self::REPO . '/issues/190' => ['number' => 190, 'body' => 'An older body'],
            'POST ' . self::REPO . '/issues/190/labels' => [],
            'PATCH ' . self::REPO . '/issues/190' => ['html_url' => 'https://github.com/x/y/issues/190', 'number' => 190],
        ]);

        $this->clientOn($github)->updateIssue(
            $this->updateInformation,
            190,
            $certMock,
            $issueLabelsMock,
            'Some Paper',
            'Daniel Nüst et al.',
            [],
            []
        );

        $this->assertSame([['id assigned', 'institution', 'check-nl']], $github->bodies('POST */labels'));
        $github->assertSent('PATCH ' . self::REPO . '/issues/190', fn ($request, $body) => !array_key_exists('labels', $body));
        $github->assertNotSent('PUT *');
    }

    /** Every status comment says where it came from. */
    public function testAStatusCommentEndsWithTheSignature()
    {
        $github = new GithubFake(['POST ' . self::REPO . '/issues/190/comments' => []]);

        $this->clientOn($github)->commentOnIssue(190, 'The CODECHECK status changed to: completed');

        $this->assertSame(
            [['body' => "The CODECHECK status changed to: completed\n\n---\nSigned by Example journal"]],
            $github->bodies('POST *')
        );
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

    private function metadataBlock(string $time): string
    {
        return CodecheckGithubRegisterIssue::metadataBlock(
            '2026-007',
            'plugins.generic.codecheck.status.assignedCodechecker',
            [],
            [],
            $this->origin,
            '42',
            new \DateTimeImmutable($time)
        );
    }

    /**
     * Only the update time differs, so nothing is written: the issue's history
     * would otherwise show an edit for every save of the form.
     */
    public function testTheMetadataBlockIsNotRewrittenWhenOnlyItsTimeWouldChange()
    {
        $github = new GithubFake([
            'GET ' . self::REPO . '/issues/7' => ['number' => 7, 'body' => "## Title\n\n" . $this->metadataBlock('2026-10-01 08:00:00Z')],
        ]);

        $this->assertFalse($this->clientOn($github)->replaceIssueMetadataBlock(7, $this->metadataBlock('2026-10-02 19:15:00Z')));
        $this->assertSame(['GET ' . self::REPO . '/issues/7'], $github->addresses());
    }

    /** A block from before the marker is rewritten once, although its JSON is the same. */
    public function testABlockWrittenBeforeTheMarkerIsRewrittenOnce()
    {
        $marked = $this->metadataBlock('2026-10-02 19:15:00Z');
        preg_match('/```json\n.*?\n```/s', $marked, $json);
        $legacy = "<details>\n<summary><h3>JSON encoded CODECHECK metadata</h3></summary>\n\n" . $json[0] . "\n\n</details>";
        $github = new GithubFake([
            'GET ' . self::REPO . '/issues/7' => ['number' => 7, 'body' => $legacy],
            'PATCH ' . self::REPO . '/issues/7' => ['number' => 7],
        ]);

        $this->assertTrue($this->clientOn($github)->replaceIssueMetadataBlock(7, $marked));
        $this->assertSame(['GET ' . self::REPO . '/issues/7', 'PATCH ' . self::REPO . '/issues/7'], $github->addresses());
    }

    /**
     * GitHub not answering is a failure to update, reported as one, and the
     * rest of the request does not wait for it again.
     */
    public function testAGithubThatDoesNotAnswerIsAnUpdateFailure()
    {
        $github = new GithubFake([
            'GET *' => new ConnectException('cURL error 28: Operation timed out', new Request('GET', 'https://api.github.com/')),
        ]);

        try {
            $this->clientOn($github)->replaceIssueMetadataBlock(7, $this->metadataBlock('2026-10-02 19:15:00Z'));
            $this->fail('the failure is reported');
        } catch (ApiUpdateException $e) {
            $this->assertTrue(GithubHttp::wasUnreachable());
        }
    }
}
