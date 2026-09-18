<?php

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\api\v1\IdentifierParameterValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * The guards on the two endpoints that write to the CODECHECK register.
 *
 * Both take a JSON body straight from the browser and turn it into a GitHub
 * issue in the public register, so a body of the wrong shape has to be refused
 * before anything is written rather than part of the way through.
 *
 * The messages are asserted on, not just the fact of failure: they are what the
 * editorial form shows, so a body that fails for one reason must not be
 * reported as failing for another.
 */
class IdentifierParameterValidatorUnitTest extends PKPTestCase
{
    /** A body both endpoints accept, to vary one key at a time. */
    private function validBody(): array
    {
        return [
            'issue' => [
                'labelsSelected' => ['venue::journal'],
                'number' => 42,
                'url' => 'https://github.com/codecheckers/register/issues/42',
            ],
            'submission' => [
                'title' => 'A paper with a check',
                'authorString' => 'A. Author',
            ],
            'repositories' => [['url' => 'https://github.com/codecheckers/example']],
            'codecheckers' => [['name' => 'A. Codechecker']],
            'reserveIdentifierMode' => 'reserveNewIdentifier',
        ];
    }

    public function testACompleteBodyIsAccepted()
    {
        $this->assertNull(IdentifierParameterValidator::forReserveIdentifier($this->validBody()));
        $this->assertNull(IdentifierParameterValidator::forGithubIssueUpdate($this->validBody()));
    }

    #[DataProvider('missingKeys')]
    public function testAMissingKeyIsReportedRatherThanRaised(array $path, string $expectedMessage)
    {
        // Regression: the guards used to index the body directly, so a body
        // that simply omitted a key raised "Undefined array key" on its way to
        // a 500 instead of answering with the 400 it deserves.
        $body = $this->validBody();

        if (count($path) === 1) {
            unset($body[$path[0]]);
        } else {
            unset($body[$path[0]][$path[1]]);
        }

        $this->assertSame($expectedMessage, IdentifierParameterValidator::forReserveIdentifier($body));
    }

    public static function missingKeys(): array
    {
        return [
            'issue' => [['issue'], "The parameter 'issue' must be an array!"],
            'labels' => [['issue', 'labelsSelected'], "The parameter 'issue.labelsSelected' must be an array!"],
            'submission' => [['submission'], "Parameter 'submission' must be an array."],
            'title' => [['submission', 'title'], "Parameter 'submission.title' must be a string."],
            'authorString' => [['submission', 'authorString'], "Parameter 'submission.authorString' must be a string."],
            'repositories' => [['repositories'], "Parameter 'repositories' must be an array."],
            'codecheckers' => [['codecheckers'], "Parameter 'codecheckers' must be an array."],
            'mode' => [['reserveIdentifierMode'], 'No Reserve Identifier Mode was specified.'],
        ];
    }

    public function testAnEmptyBodyIsRefusedOnTheFirstGuard()
    {
        $this->assertSame(
            "The parameter 'issue' must be an array!",
            IdentifierParameterValidator::forReserveIdentifier([])
        );
        $this->assertSame(
            "The parameter 'issue' must be an array!",
            IdentifierParameterValidator::forGithubIssueUpdate([])
        );
    }

    public function testLinkingAnExistingIdentifierNeedsTheIdentifier()
    {
        $body = $this->validBody();
        $body['reserveIdentifierMode'] = 'linkExistingIdentifier';

        $this->assertSame(
            "Parameter 'identifier' must be a string when using mode 'linkExistingIdentifier'.",
            IdentifierParameterValidator::forReserveIdentifier($body)
        );

        $body['identifier'] = '2020-001';
        $this->assertNull(IdentifierParameterValidator::forReserveIdentifier($body));
    }

    public function testReservingANewIdentifierDoesNotNeedOne()
    {
        // Only the linking mode carries an identifier; requiring it in both
        // would make reserving impossible.
        $body = $this->validBody();
        unset($body['identifier']);

        $this->assertNull(IdentifierParameterValidator::forReserveIdentifier($body));
    }

    public function testUpdatingAnIssueNeedsTheIssueItUpdates()
    {
        // An update without a number or URL would open a second issue for a
        // check that already has one.
        $body = $this->validBody();
        unset($body['issue']['number']);

        $this->assertSame(
            "The parameter 'issue.number' must be an integer!",
            IdentifierParameterValidator::forGithubIssueUpdate($body)
        );

        $body = $this->validBody();
        $body['issue']['number'] = '42';
        $this->assertSame(
            "The parameter 'issue.number' must be an integer!",
            IdentifierParameterValidator::forGithubIssueUpdate($body),
            'an issue number sent as a string is not an issue number'
        );

        $body = $this->validBody();
        unset($body['issue']['url']);
        $this->assertSame(
            "The parameter 'issue.url' must be a string!",
            IdentifierParameterValidator::forGithubIssueUpdate($body)
        );
    }

    public function testReservingAnIdentifierDoesNotNeedAnIssueNumber()
    {
        // The issue does not exist yet when an identifier is reserved.
        $body = $this->validBody();
        unset($body['issue']['number'], $body['issue']['url']);

        $this->assertNull(IdentifierParameterValidator::forReserveIdentifier($body));
    }
}
