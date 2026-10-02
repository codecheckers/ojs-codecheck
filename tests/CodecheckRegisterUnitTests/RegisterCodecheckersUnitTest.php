<?php

/**
 * @file tests/CodecheckRegisterUnitTests/RegisterCodecheckersUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class RegisterCodecheckersUnitTest
 *
 * @brief Who is assigned to a register issue, and how a codechecker is named
 *   there (#186).
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\RegisterCodecheckers;
use PKP\tests\PKPTestCase;

class RegisterCodecheckersUnitTest extends PKPTestCase
{
    private const CARBERRY = '0000-0002-1825-0097';

    public function testOnlyUsableUsernamesAreAssignedOnceEach(): void
    {
        $this->assertSame(['nuest', 'sje30'], RegisterCodecheckers::usernames([
            ['name' => 'Daniel', 'github' => '@nuest'],
            ['name' => 'No username', 'orcid' => self::CARBERRY],
            ['name' => 'Typo', 'github' => 'two words'],
            ['name' => 'Stephen', 'github' => 'sje30'],
            ['name' => 'Daniel again', 'github' => 'nuest'],
        ]));
    }

    public function testAStoredListWithoutUsernamesAssignsNobody(): void
    {
        $stored = json_encode([['name' => 'Before #186', 'orcid' => self::CARBERRY]]);

        $this->assertSame([], RegisterCodecheckers::usernames($stored));
        $this->assertSame([], RegisterCodecheckers::usernames(null));
    }

    /**
     * GitHub leaves out a username it cannot assign rather than refusing the
     * request, so whoever is missing from its answer was not assigned.
     */
    public function testWhoeverGithubDidNotAssignIsUnassigned(): void
    {
        $split = RegisterCodecheckers::split([
            ['name' => 'Daniel', 'github' => 'nuest'],
            ['name' => 'Outsider', 'github' => 'outsider'],
            ['name' => 'No username', 'orcid' => self::CARBERRY],
        ], ['Nuest', 'someone-assigned-by-hand']);

        $this->assertSame(['Daniel'], array_column($split['assigned'], 'name'));
        $this->assertSame(['Outsider', 'No username'], array_column($split['unassigned'], 'name'));
    }

    public function testAnEntryWithNothingToNameIsLeftOut(): void
    {
        $this->assertSame(
            ['assigned' => [], 'unassigned' => []],
            RegisterCodecheckers::split([['name' => '  ', 'orcid' => '']], [])
        );
    }

    public function testACodecheckerIsNamedWithTheirOrcidRecordAndMentioned(): void
    {
        $this->assertSame(
            'Josiah Carberry ([ORCID ' . self::CARBERRY . '](https://orcid.org/' . self::CARBERRY . ')) @jcarberry',
            RegisterCodecheckers::describe(['name' => 'Josiah Carberry', 'orcid' => self::CARBERRY, 'github' => 'jcarberry'])
        );
    }

    public function testWithoutAMentionTheUsernameIsLeftOut(): void
    {
        $this->assertSame(
            'Josiah Carberry',
            RegisterCodecheckers::describe(['name' => 'Josiah Carberry', 'github' => 'jcarberry'], false)
        );
    }

    /**
     * The name is the one part nobody checked, and it is published into a
     * Markdown post: it must neither format it nor notify anybody.
     */
    public function testANameCanNeitherFormatThePostNorMentionSomebody(): void
    {
        $described = RegisterCodecheckers::describe([
            'name' => "[click](https://example.org) @everyone *bold* codecheckers/register#1 &#64;someone\n\n# Heading",
        ]);

        $this->assertStringNotContainsString('@everyone', $described);
        $this->assertStringContainsString("@\u{200B}everyone", $described);
        $this->assertStringNotContainsString('#1', $described);
        // GitHub decodes `&#64;` into a mention; `&amp;` keeps it text.
        $this->assertStringNotContainsString('&#64;someone', $described);
        $this->assertStringContainsString('&amp;', $described);
        $this->assertStringContainsString('\[click\]', $described);
        $this->assertStringContainsString('\*bold\*', $described);
        $this->assertStringNotContainsString("\n", $described);
    }

    public function testAnUnusableOrcidOrUsernameIsNotPublished(): void
    {
        $this->assertSame(
            'Typo',
            RegisterCodecheckers::describe(['name' => 'Typo', 'orcid' => 'nonsense', 'github' => 'two words'])
        );
    }

    public function testTheListIsJoinedAndEmptyEntriesSkipped(): void
    {
        $this->assertSame('A, B', RegisterCodecheckers::describeAll([
            ['name' => 'A'], ['name' => ''], ['name' => 'B', 'github' => 'b-user'],
        ]));
        $this->assertSame('', RegisterCodecheckers::describeAll([]));
    }
}
