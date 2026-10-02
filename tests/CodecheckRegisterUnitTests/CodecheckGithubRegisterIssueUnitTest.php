<?php

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use PKP\tests\PKPTestCase;

/**
 * What this class builds is posted to the public CODECHECK register, where a
 * malformed title or a missing metadata block is visible to everyone and has to
 * be fixed by hand afterwards. The markdown is therefore pinned here.
 *
 * The status-carrying variant is not covered: passing the update-status flag
 * makes the constructor read the current status through CodecheckStatusHandler,
 * which queries the database. That path belongs to an integration test.
 */
class CodecheckGithubRegisterIssueUnitTest extends PKPTestCase
{
    private function buildIssue(
        array $repositories = ['https://github.com/example/repo'],
        array $codecheckers = [['name' => 'Jane Doe', 'orcid' => '0000-0002-1825-0097', 'github' => 'janedoe']],
        array $labels = ['community', 'journal'],
        string $authorString = 'Doe et al.',
        string $journalName = 'CODECHECK Demo Journal'
    ): CodecheckGithubRegisterIssue {
        return new CodecheckGithubRegisterIssue(
            'codecheckers',
            'register',
            new CertificateIdentifier(2026, 7),
            new CodecheckIssueLabels($labels),
            'A Paper About Things',
            new CodecheckPostOrigin($journalName, 'https://journal.example/index.php/demo', '3.5.0.3', '0.1.0.0'),
            $authorString,
            '42',
            $codecheckers,
            $repositories,
            []
        );
    }

    /**
     * Issue #154: the register issue is public, and the editorial form posts
     * whole repository entries rather than plain URLs. A repository the
     * codechecker marked hidden must not appear in it — nor must the internal
     * flags of the ones that do.
     */
    public function testHiddenRepositoriesAreNotPublishedInTheIssue()
    {
        $body = $this->buildIssue(repositories: [
            ['url' => 'https://github.com/public/repo', 'hidden' => false],
            ['url' => 'https://github.com/private/repo', 'hidden' => true],
        ])->getBody();

        $this->assertStringContainsString('https://github.com/public/repo', $body);
        $this->assertStringNotContainsString('private/repo', $body);
        $this->assertStringNotContainsString('hidden', $body);
    }

    /** Entries are published as plain addresses, not as objects. */
    public function testRepositoryEntriesArePublishedAsAddresses()
    {
        $body = $this->buildIssue(repositories: [
            ['url' => 'https://github.com/public/repo', 'hidden' => false, 'providedByAuthor' => true],
        ])->getBody();

        $this->assertSame(['https://github.com/public/repo'], $this->metadata($body)['repositories']);
        $this->assertStringNotContainsString('providedByAuthor', $body);
    }

    public function testTheTitleIsTheAuthorsAndTheIdentifier()
    {
        $this->assertSame('Doe et al. | 2026-007', $this->buildIssue()->getTitle());
    }

    public function testAnIssueWithoutAnAuthorStringIsStillNamed()
    {
        // Reserving an identifier before the paper has authors is normal, and
        // an issue titled " | 2026-007" would be unreadable in the register.
        $this->assertSame('New CODECHECK | 2026-007', $this->buildIssue(authorString: '')->getTitle());
    }

    public function testTheBodyCarriesThePaperTheJournalAndTheRepositories()
    {
        $body = $this->buildIssue()->getBody();

        $this->assertStringContainsString('## A Paper About Things', $body);
        $this->assertStringContainsString('**Journal:** CODECHECK Demo Journal *(Submission ID: 42)*', $body);
        $this->assertStringContainsString("\t- https://github.com/example/repo\n", $body);
    }

    /** The fenced JSON block of an issue body, decoded. */
    private function metadata(string $body): array
    {
        $this->assertSame(1, preg_match('/```json\n(.*?)\n```/s', $body, $match), 'the body has one JSON block');
        $decoded = json_decode($match[1], true);
        $this->assertIsArray($decoded, 'the JSON block parses: ' . json_last_error_msg());

        return $decoded;
    }

    /**
     * The block was concatenated by hand, and had a trailing comma after
     * `journal`: no JSON parser would read it. Pinned by decoding it.
     */
    public function testTheBodyEmbedsTheMetadataAsValidJson()
    {
        $metadata = $this->metadata($this->buildIssue()->getBody());

        $this->assertSame(
            ['identifier', 'repositories', 'codecheckers', 'links', 'journal', 'plugin'],
            array_keys($metadata)
        );
        $this->assertSame('2026-007', $metadata['identifier']);
        $this->assertSame(['https://github.com/example/repo'], $metadata['repositories']);
        $this->assertSame(
            ['name' => 'Jane Doe', 'orcid' => '0000-0002-1825-0097', 'github' => 'janedoe'],
            $metadata['codecheckers'][0]
        );
        $this->assertSame([], $metadata['links']);
    }

    /** Which installation wrote the record, for whoever processes the register. */
    public function testTheMetadataNamesTheJournalAndTheSoftware()
    {
        $metadata = $this->metadata($this->buildIssue()->getBody());

        $this->assertSame(
            [
                'name' => 'CODECHECK Demo Journal',
                'url' => 'https://journal.example/index.php/demo',
                'ojsVersion' => '3.5.0.3',
                'submissionID' => 42,
            ],
            $metadata['journal']
        );
        $this->assertSame(['name' => 'ojs-codecheck', 'version' => '0.1.0.0'], $metadata['plugin']);
    }

    /** A journal name was written into the JSON unescaped. */
    public function testAJournalNameWithQuotesAndBackslashesStaysValidJson()
    {
        $name = 'The "Quoted" Journal \\ of Things';

        $metadata = $this->metadata($this->buildIssue(journalName: $name)->getBody());

        $this->assertSame($name, $metadata['journal']['name']);
    }

    /** Three backticks in a name would close the JSON fence early. */
    public function testBackticksInANameCannotCloseTheJsonFence()
    {
        $name = 'Odd ``` Journal';

        $body = $this->buildIssue(journalName: $name)->getBody();

        $this->assertSame($name, $this->metadata($body)['journal']['name']);
        $block = substr($body, strpos($body, '<details>'), strpos($body, '</details>') - strpos($body, '<details>'));
        $this->assertSame(2, substr_count($block, '```'), 'only the fence itself');
    }

    /** The body is re-rendered whole on every update, so it is signed once, last. */
    public function testTheBodyEndsWithTheSignatureOnce()
    {
        $body = $this->buildIssue()->getBody();

        $this->assertStringEndsWith(
            "\n\n---\n*Posted by the [CODECHECK plugin for OJS](https://github.com/codecheckers/ojs-codecheck)"
            . ' from [CODECHECK Demo Journal](https://journal.example/index.php/demo).*',
            $body
        );
        $this->assertSame(1, substr_count($body, 'Posted by the [CODECHECK plugin for OJS]'));
    }

    public function testWithoutTheUpdateFlagNoStatusIsRecorded()
    {
        // The status line is what makes the constructor reach for the database,
        // so its absence here is also what keeps this test a unit test.
        $body = $this->buildIssue()->getBody();

        $this->assertStringNotContainsString('CODECHECK Status:', $body);
        $this->assertStringNotContainsString('"status"', $body);
    }

    public function testEveryIssueIsLabelledIdAssignedOnTopOfTheVenueLabels()
    {
        $labels = $this->buildIssue()->getLabels();

        $this->assertSame('id assigned', $labels[0]);
        $this->assertEqualsCanonicalizing(['id assigned', 'community', 'journal'], $labels);
    }

    public function testSeveralRepositoriesAreListedOnePerLine()
    {
        $body = $this->buildIssue(repositories: [
            'https://github.com/example/code',
            'https://zenodo.org/record/123',
        ])->getBody();

        $this->assertStringContainsString("\t- https://github.com/example/code\n\t- https://zenodo.org/record/123\n", $body);
    }

    public function testTheNewIssueUrlEncodesTitleBodyAndLabels()
    {
        $url = $this->buildIssue()->getNewIssueUrl();

        $this->assertStringStartsWith(
            'https://github.com/codecheckers/register/issues/new?title=Doe%20et%20al.%20%7C%202026-007&body=',
            $url
        );
        $this->assertStringContainsString('&labels=', $url);
        // A raw space or pipe in the query would truncate the pre-filled issue.
        $this->assertStringNotContainsString(' ', $url);
    }

    public function testTheRepositoryOwnerIsKeptForTheApiCall()
    {
        $this->assertSame('codecheckers', $this->buildIssue()->getRepositoryOwner());
    }

    /**
     * Named in the body, linked to their ORCID record, and not mentioned: the
     * body is rewritten on every update, and the assignment is what says on
     * GitHub who is checking (#186).
     */
    public function testTheBodyNamesTheCodecheckersWithoutMentioningThem()
    {
        $body = $this->buildIssue()->getBody();

        $this->assertStringContainsString(
            '**Codecheckers:** Jane Doe ([ORCID 0000-0002-1825-0097](https://orcid.org/0000-0002-1825-0097))',
            $body
        );
        $this->assertStringNotContainsString('@janedoe', $body);
    }
}
