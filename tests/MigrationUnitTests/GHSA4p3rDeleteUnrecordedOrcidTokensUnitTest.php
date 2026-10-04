<?php

/**
 * @file tests/MigrationUnitTests/GHSA4p3rDeleteUnrecordedOrcidTokensUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Advisory GHSA-4p3r-qgp4-g74r — which ORCID tokens the migration
 *        deletes. The deletion itself needs a database.
 */

namespace APP\plugins\generic\codecheck\tests\MigrationUnitTests;

use APP\plugins\generic\codecheck\classes\migration\upgrade\GHSA_4p3r_DeleteUnrecordedOrcidTokens as Migration;
use PKP\tests\PKPTestCase;

class GHSA4p3rDeleteUnrecordedOrcidTokensUnitTest extends PKPTestCase
{
    private const CARBERRY = '0000-0002-1825-0097';
    private const OTHER = '0000-0001-5109-3700';

    private function token(int $submissionId, string $orcidId): object
    {
        return (object) ['id' => random_int(1, PHP_INT_MAX), 'submission_id' => $submissionId, 'orcid_id' => $orcidId];
    }

    public function testATokenOfARecordedCodecheckerIsKept(): void
    {
        $kept = $this->token(9, self::CARBERRY);

        $this->assertSame([], Migration::unrecordedTokens(
            [$kept],
            [9 => json_encode([['name' => 'J. Carberry', 'orcid' => self::CARBERRY]])]
        ));
    }

    public function testATokenOfAnAccountTheRecordDoesNotNameIsDeleted(): void
    {
        $kept = $this->token(9, self::CARBERRY);
        $stranger = $this->token(9, self::OTHER);

        $this->assertSame([$stranger], Migration::unrecordedTokens(
            [$kept, $stranger],
            [9 => json_encode([['name' => 'J. Carberry', 'orcid' => self::CARBERRY]])]
        ));
    }

    /** The case the advisory was about: codecheckers recorded by name alone. */
    public function testEveryTokenGoesWhenNoCodecheckerHasAnIdentifier(): void
    {
        $token = $this->token(9, self::CARBERRY);

        $this->assertSame([$token], Migration::unrecordedTokens([$token], [9 => json_encode([['name' => 'A']])]));
        $this->assertSame([$token], Migration::unrecordedTokens([$token], []));
    }

    /** A recorded iD on another submission does not keep a token here. */
    public function testTheRecordIsThatOfTheTokensOwnSubmission(): void
    {
        $token = $this->token(8, self::CARBERRY);

        $this->assertSame([$token], Migration::unrecordedTokens(
            [$token],
            [9 => json_encode([['name' => 'J. Carberry', 'orcid' => self::CARBERRY]]), 8 => '[]']
        ));
    }
}
