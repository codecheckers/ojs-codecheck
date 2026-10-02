<?php

/**
 * @file tests/MigrationUnitTests/I185MoveRecordsToConfigSpec2UnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #185 — how the migration reads the column default back. The rows
 *        it moves and the second run being a no-op need a database, and are
 *        checked by `make check-migration`.
 */

namespace APP\plugins\generic\codecheck\tests\MigrationUnitTests;

use APP\plugins\generic\codecheck\classes\migration\upgrade\I185_MoveRecordsToConfigSpec2 as Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class I185MoveRecordsToConfigSpec2UnitTest extends PKPTestCase
{
    #[DataProvider('defaults')]
    public function testTheDefaultIsRecognisedHoweverTheServerQuotesIt(?string $reported, bool $expected)
    {
        $this->assertSame($expected, Migration::defaultIs($reported, '2.0'));
    }

    public static function defaults(): array
    {
        return [
            'MariaDB quotes a string default' => ["'2.0'", true],
            'MySQL does not' => ['2.0', true],
            'PostgreSQL adds a cast' => ["'2.0'::character varying", true],
            'PostgreSQL, the old default' => ["'latest'::character varying", false],
            'the old default' => ['latest', false],
            'the old default, quoted' => ["'latest'", false],
            'another version' => ['2.1', false],
            'no default at all' => [null, false],
            'an empty one' => ['', false],
        ];
    }
}
