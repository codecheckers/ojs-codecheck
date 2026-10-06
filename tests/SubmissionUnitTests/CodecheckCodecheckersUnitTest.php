<?php

/**
 * @file tests/SubmissionUnitTests/CodecheckCodecheckersUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckCodecheckersUnitTest
 *
 * @brief The ORCID rule applied where codecheckers are written.
 *
 * The check digit itself is PKP's (`PKP\validation\ValidatorORCID`), so what is
 * pinned here is what the plugin adds around it: which forms of an iD are
 * accepted, that only what a save *introduces* is refused, and that one shape
 * reaches the column. The same rules are mirrored in `resources/js/orcid.js`
 * and covered by `cypress/tests/component/orcid.cy.js`; the two files must
 * agree, and the examples below are deliberately the same ones.
 */

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class CodecheckCodecheckersUnitTest extends PKPTestCase
{
    /** ORCID's own published test identifiers. */
    private const CARBERRY = '0000-0002-1825-0097';
    private const WITH_X_CHECKSUM = '0000-0002-1694-233X';

    public function testAWellFormedIdentifierIsAccepted(): void
    {
        $this->assertTrue(CodecheckCodecheckers::isOrcid(self::CARBERRY));
        $this->assertTrue(CodecheckCodecheckers::isOrcid(self::WITH_X_CHECKSUM));
    }

    public function testAnIdentifierWhoseCheckDigitDisagreesIsRefused(): void
    {
        $this->assertFalse(CodecheckCodecheckers::isOrcid('0000-0002-1825-0098'));
    }

    public function testSomethingThatIsNotAnIdentifierIsRefused(): void
    {
        $this->assertFalse(CodecheckCodecheckers::isOrcid('0000-0002-1825-009'));
        $this->assertFalse(CodecheckCodecheckers::isOrcid('0000000218250097'));
        $this->assertFalse(CodecheckCodecheckers::isOrcid('not an orcid'));
    }

    /** The field is optional, so "none given" is not an invalid identifier. */
    public function testTheEmptyValueIsNotAnIdentifier(): void
    {
        $this->assertFalse(CodecheckCodecheckers::isOrcid(''));
        $this->assertFalse(CodecheckCodecheckers::isOrcid(null));
    }

    public function testAnIdentifierCopiedOutOfTheAddressBarIsAccepted(): void
    {
        $this->assertTrue(CodecheckCodecheckers::isOrcid('https://orcid.org/' . self::CARBERRY));
        $this->assertTrue(CodecheckCodecheckers::isOrcid('https://www.orcid.org/' . self::CARBERRY));
        $this->assertTrue(CodecheckCodecheckers::isOrcid('https://sandbox.orcid.org/' . self::CARBERRY . '/'));
        $this->assertTrue(CodecheckCodecheckers::isOrcid('https://orcid.org/' . self::CARBERRY . '?lang=en'));
    }

    public function testNormalizingReducesAnAddressToTheStoredForm(): void
    {
        $this->assertSame(
            self::WITH_X_CHECKSUM,
            CodecheckCodecheckers::normalizeOrcid('  https://orcid.org/0000-0002-1694-233x ')
        );
        $this->assertSame(self::CARBERRY, CodecheckCodecheckers::normalizeOrcid(self::CARBERRY));
        $this->assertSame('', CodecheckCodecheckers::normalizeOrcid(null));
    }

    /** OJS stores an author's iD this way, and the `codecheck.yml` must not. */
    public function testNormalizingStripsTheHostOjsStoresForAnAuthor(): void
    {
        $this->assertSame(
            self::CARBERRY,
            CodecheckCodecheckers::normalizeOrcid('https://orcid.org/' . self::CARBERRY)
        );
    }

    public function testUnusableIdentifiersAreReportedAsTheyWereGiven(): void
    {
        $codecheckers = [
            ['name' => 'Fine', 'orcid' => self::CARBERRY],
            ['name' => 'No iD at all', 'orcid' => ''],
            ['name' => 'Mistyped', 'orcid' => ' 0000-0002-1825-0098 '],
        ];

        $this->assertSame(['0000-0002-1825-0098'], CodecheckCodecheckers::unusableOrcids($codecheckers));
    }

    public function testTheSameUnusableIdentifierTwiceIsReportedOnce(): void
    {
        $codecheckers = [
            ['name' => 'One', 'orcid' => 'nonsense'],
            ['name' => 'Two', 'orcid' => 'nonsense'],
        ];

        $this->assertSame(['nonsense'], CodecheckCodecheckers::unusableOrcids($codecheckers));
    }

    public function testTheStoredJsonIsReadAsWellAsAnArray(): void
    {
        $stored = json_encode([['name' => 'Mistyped', 'orcid' => 'nonsense']]);

        $this->assertSame(['nonsense'], CodecheckCodecheckers::unusableOrcids($stored));
    }

    public function testNothingIsUnusableInAnAbsentOrUnreadableList(): void
    {
        $this->assertSame([], CodecheckCodecheckers::unusableOrcids(null));
        $this->assertSame([], CodecheckCodecheckers::unusableOrcids('not json'));
        $this->assertSame([], CodecheckCodecheckers::unusableOrcids(['not an entry']));
    }

    /**
     * Only what a save introduces is judged. Refusing a record for a value
     * already in it would turn away a save that changed only the summary, and
     * leave the editor no way out (issue #170).
     */
    public function testAnIdentifierAlreadyStoredIsNotRefusedAgain(): void
    {
        $stored = json_encode([['name' => 'Mistyped', 'orcid' => 'nonsense']]);
        $incoming = [
            ['name' => 'Mistyped', 'orcid' => 'nonsense'],
            ['name' => 'Fine', 'orcid' => self::CARBERRY],
        ];

        $this->assertSame([], CodecheckCodecheckers::newUnusableOrcids($incoming, $stored));
    }

    public function testAnIdentifierThisSaveIntroducesIsRefused(): void
    {
        $stored = json_encode([['name' => 'Mistyped', 'orcid' => 'nonsense']]);
        $incoming = [
            ['name' => 'Mistyped', 'orcid' => 'nonsense'],
            ['name' => 'New', 'orcid' => 'also nonsense'],
        ];

        $this->assertSame(['also nonsense'], CodecheckCodecheckers::newUnusableOrcids($incoming, $stored));
    }

    public function testNormalizingAListReducesEveryIdentifierAndTrimsNames(): void
    {
        $normalized = CodecheckCodecheckers::withNormalizedEntries([
            ['name' => '  Josiah Carberry  ', 'orcid' => 'https://orcid.org/' . self::CARBERRY],
            ['name' => 'No iD', 'orcid' => ''],
        ]);

        $this->assertSame([
            ['name' => 'Josiah Carberry', 'orcid' => self::CARBERRY, 'github' => ''],
            ['name' => 'No iD', 'orcid' => '', 'github' => ''],
        ], $normalized);
    }

    /**
     * The endpoint refuses an introduced bad value before this is reached, so
     * what is left is a value already on file: it is dropped rather than
     * written back, because anything storing it claims it is an iD.
     */
    public function testAnUnusableIdentifierIsNotWrittenBack(): void
    {
        $normalized = CodecheckCodecheckers::withNormalizedEntries([
            ['name' => 'Mistyped', 'orcid' => '0000-0002-1825-0098'],
        ]);

        $this->assertSame([['name' => 'Mistyped', 'orcid' => '', 'github' => '']], $normalized);
    }

    public function testAMissingNameOrIdentifierBecomesTheEmptyString(): void
    {
        $this->assertSame(
            [['name' => '', 'orcid' => '', 'github' => '']],
            CodecheckCodecheckers::withNormalizedEntries([[]])
        );
    }

    #[DataProvider('pastedGithubUsernames')]
    public function testAGithubUsernameIsReducedToTheBareName(string $pasted): void
    {
        $this->assertSame('nuest', CodecheckCodecheckers::normalizeGithubUsername($pasted));
        $this->assertTrue(CodecheckCodecheckers::isGithubUsername($pasted));
    }

    public static function pastedGithubUsernames(): array
    {
        return [
            'bare' => ['nuest'],
            'mention' => ['@nuest'],
            'padded' => ['  nuest  '],
            'profile address' => ['https://github.com/nuest'],
            'profile address with slash and query' => ['github.com/nuest/?tab=repositories'],
            'www' => ['https://www.github.com/nuest'],
        ];
    }

    #[DataProvider('notGithubUsernames')]
    public function testWhatGithubWouldRefuseIsNotAUsername(string $value): void
    {
        $this->assertFalse(CodecheckCodecheckers::isGithubUsername($value));
    }

    public static function notGithubUsernames(): array
    {
        return [
            'empty' => [''],
            'space' => ['daniel nuest'],
            'leading hyphen' => ['-nuest'],
            'trailing hyphen' => ['nuest-'],
            'double hyphen' => ['nu--est'],
            'forty characters' => [str_repeat('a', 40)],
            'underscore' => ['nu_est'],
            'a repository, not a user' => ['github.com/codecheckers/register'],
        ];
    }

    public function testThirtyNineCharactersAndSingleHyphensAreAUsername(): void
    {
        $this->assertTrue(CodecheckCodecheckers::isGithubUsername(str_repeat('a', 39)));
        $this->assertTrue(CodecheckCodecheckers::isGithubUsername('Nathan-Skene-2'));
    }

    public function testTheNormalizedListKeepsAUsableUsernameAndDropsAnUnusableOne(): void
    {
        $this->assertSame([
            ['name' => 'Daniel', 'orcid' => '', 'github' => 'nuest'],
            ['name' => 'Typo', 'orcid' => '', 'github' => ''],
        ], CodecheckCodecheckers::withNormalizedEntries([
            ['name' => 'Daniel', 'github' => '@nuest'],
            ['name' => 'Typo', 'github' => 'not a name'],
        ]));
    }

    public function testOnlyAnIntroducedUnusableUsernameIsRefused(): void
    {
        $stored = json_encode([['name' => 'Old', 'orcid' => '', 'github' => 'bad name']]);
        $incoming = [
            ['name' => 'Old', 'github' => 'bad name'],
            ['name' => 'New', 'github' => 'also bad'],
            ['name' => 'Fine', 'github' => 'nuest'],
        ];

        $this->assertSame(['also bad'], CodecheckCodecheckers::newUnusableGithubUsernames($incoming, $stored));
    }

    /**
     * Who may be credited on ORCID (GHSA-4p3r-qgp4-g74r): the recorded iDs in
     * the stored form, and nothing for a codechecker recorded by name alone.
     */
    public function testRecordedIdentifiersAreTheValidOnesInTheStoredForm(): void
    {
        $json = json_encode([
            ['name' => 'A', 'orcid' => 'https://orcid.org/' . self::CARBERRY],
            ['name' => 'B', 'orcid' => ''],
            ['name' => 'C'],
            ['name' => 'D', 'orcid' => '0000-0002-1825-0098'],
            ['name' => 'E', 'orcid' => strtolower(self::WITH_X_CHECKSUM)],
            ['name' => 'F', 'orcid' => self::CARBERRY],
        ]);

        $this->assertSame([self::CARBERRY, self::WITH_X_CHECKSUM], CodecheckCodecheckers::recordedOrcids($json));
    }

    public function testNoIdentifierIsRecordedInAnAbsentOrUnreadableList(): void
    {
        $this->assertSame([], CodecheckCodecheckers::recordedOrcids(null));
        $this->assertSame([], CodecheckCodecheckers::recordedOrcids('not json'));
        $this->assertSame([], CodecheckCodecheckers::recordedOrcids([['name' => 'Only a name']]));
    }

    /** The stored shape carries the linked account; the register's does not (#13). */
    public function testTheStoredShapeKeepsTheLinkedAccountAndThePublicOneDoesNot(): void
    {
        $list = [['userId' => '7', 'name' => ' Cora ', 'orcid' => self::CARBERRY, 'github' => '@cora']];

        $this->assertSame(
            [['userId' => 7, 'name' => 'Cora', 'orcid' => self::CARBERRY, 'github' => 'cora']],
            CodecheckCodecheckers::withStoredEntries($list)
        );
        $this->assertSame(
            [['name' => 'Cora', 'orcid' => self::CARBERRY, 'github' => 'cora']],
            CodecheckCodecheckers::withNormalizedEntries($list)
        );
    }

    public static function linkProvider(): array
    {
        return [
            'none' => [['name' => 'A'], null],
            'a number' => [['userId' => 7], 7],
            'digits' => [['userId' => '7'], 7],
            'zero' => [['userId' => 0], null],
            'negative' => [['userId' => -3], null],
            'not a number' => [['userId' => '7 OR 1=1'], null],
            'a float' => [['userId' => 7.5], null],
        ];
    }

    #[DataProvider('linkProvider')]
    public function testOnlyAPositiveWholeNumberLinksAnAccount(array $entry, ?int $expected): void
    {
        $this->assertSame($expected, CodecheckCodecheckers::withStoredEntries([$entry])[0]['userId']);
    }

    public function testTheLinkedAccountsAreEachCountedOnce(): void
    {
        $this->assertSame([7, 9], CodecheckCodecheckers::linkedUserIds(json_encode([
            ['userId' => 7], ['name' => 'B'], ['userId' => 9], ['userId' => 7],
        ])));
        $this->assertSame([], CodecheckCodecheckers::linkedUserIds(null));
    }

    /** A merged account's links move to the account it was merged into, and nothing else changes. */
    public function testAMergeMovesOnlyThatAccountsLinks(): void
    {
        $list = [
            ['userId' => 7, 'name' => 'Cora', 'extra' => 'kept'],
            ['userId' => 70, 'name' => 'Not Cora'],
            ['name' => 'Unlinked'],
        ];

        $this->assertSame([
            ['userId' => 12, 'name' => 'Cora', 'extra' => 'kept'],
            ['userId' => 70, 'name' => 'Not Cora'],
            ['name' => 'Unlinked'],
        ], CodecheckCodecheckers::withUserIdReplaced($list, 7, 12));
    }

    /** Merging one codechecker into another lists the account once. */
    public function testAMergeOfTwoCodecheckersListsTheAccountOnce(): void
    {
        $this->assertSame(
            [['userId' => 8, 'name' => 'First'], ['name' => 'Unlinked']],
            CodecheckCodecheckers::withUserIdReplaced(
                [['userId' => '5', 'name' => 'First'], ['userId' => 8, 'name' => 'Second'], ['name' => 'Unlinked']],
                5,
                8
            )
        );
    }
}
