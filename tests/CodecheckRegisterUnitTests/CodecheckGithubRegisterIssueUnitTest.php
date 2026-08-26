<?php

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
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
        array $codecheckers = [['name' => 'Jane Doe', 'ORCID' => '0000-0002-1825-0097']],
        array $labels = ['community', 'journal'],
        string $authorString = 'Doe et al.'
    ): CodecheckGithubRegisterIssue {
        return new CodecheckGithubRegisterIssue(
            'codecheckers',
            'register',
            new CertificateIdentifier(2026, 7),
            new CodecheckIssueLabels($labels),
            'A Paper About Things',
            'CODECHECK Demo Journal',
            $authorString,
            '42',
            $codecheckers,
            $repositories,
            []
        );
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

    public function testTheBodyEmbedsTheMetadataAsJson()
    {
        $body = $this->buildIssue()->getBody();

        $this->assertStringContainsString('```json', $body);
        $this->assertStringContainsString('"identifier": "2026-007"', $body);
        $this->assertStringContainsString('"repositories": ["https:\/\/github.com\/example\/repo"]', $body);
        $this->assertStringContainsString('"name":"Jane Doe"', $body);
        $this->assertStringContainsString('"submissionID": 42', $body);
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
}
