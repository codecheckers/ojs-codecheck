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
        $normalized = CodecheckCodecheckers::withNormalizedOrcids([
            ['name' => '  Josiah Carberry  ', 'orcid' => 'https://orcid.org/' . self::CARBERRY],
            ['name' => 'No iD', 'orcid' => ''],
        ]);

        $this->assertSame([
            ['name' => 'Josiah Carberry', 'orcid' => self::CARBERRY],
            ['name' => 'No iD', 'orcid' => ''],
        ], $normalized);
    }

    /**
     * The endpoint refuses an introduced bad value before this is reached, so
     * what is left is a value already on file: it is dropped rather than
     * written back, because anything storing it claims it is an iD.
     */
    public function testAnUnusableIdentifierIsNotWrittenBack(): void
    {
        $normalized = CodecheckCodecheckers::withNormalizedOrcids([
            ['name' => 'Mistyped', 'orcid' => '0000-0002-1825-0098'],
        ]);

        $this->assertSame([['name' => 'Mistyped', 'orcid' => '']], $normalized);
    }

    public function testAMissingNameOrIdentifierBecomesTheEmptyString(): void
    {
        $this->assertSame(
            [['name' => '', 'orcid' => '']],
            CodecheckCodecheckers::withNormalizedOrcids([[]])
        );
    }
}
