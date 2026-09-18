<?php

/**
 * @file tests/SubmissionUnitTests/CodecheckRepositoriesUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #154 — the rules for reading the repository blob, which the
 *        article page, the publication validator and the register deposit all
 *        share so they cannot drift apart.
 */

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use PKP\tests\PKPTestCase;

class CodecheckRepositoriesUnitTest extends PKPTestCase
{
    private const BLOB = [
        'repositories' => [
            ['url' => 'https://github.com/public/one'],
            ['url' => 'https://github.com/private/one', 'hidden' => true, 'containsCodecheckYaml' => true],
            ['url' => 'https://github.com/public/two'],
        ],
    ];

    public function testPublicEntriesWithholdsHiddenRepositories()
    {
        $this->assertSame(
            ['https://github.com/public/one', 'https://github.com/public/two'],
            array_column(CodecheckRepositories::publicEntries(self::BLOB), 'url')
        );
    }

    /**
     * The asymmetry that matters: a repository can be withheld from readers and
     * still be the one whose codecheck.yml is fetched and deposited.
     */
    public function testSelectedUrlFindsAHiddenRepository()
    {
        $this->assertSame(
            'https://github.com/private/one',
            CodecheckRepositories::selectedUrl(self::BLOB)
        );
    }

    public function testSelectedUrlIsNullWhenNothingIsMarked()
    {
        $this->assertNull(CodecheckRepositories::selectedUrl([
            'repositories' => [['url' => 'https://github.com/public/one']],
        ]));
    }

    public function testSelectedUrlIsTrimmedAndSkipsMarkedEntriesWithoutAnAddress()
    {
        $this->assertSame('https://github.com/public/two', CodecheckRepositories::selectedUrl([
            'repositories' => [
                ['url' => '   ', 'containsCodecheckYaml' => true],
                ['url' => '  https://github.com/public/two  ', 'containsCodecheckYaml' => true],
            ],
        ]));
    }

    /**
     * Only the editorial form keeps the choice exclusive, and it is the browser
     * doing it — so the extra flags are cleared where the data is written, or
     * the article page would mark two while the register deposited one.
     */
    public function testWithOneMarkedKeepsTheFirstFlagOnly()
    {
        $normalised = CodecheckRepositories::withOneMarked([
            'repositories' => [
                ['url' => 'https://github.com/a/one', 'containsCodecheckYaml' => true],
                ['url' => 'https://github.com/b/two', 'containsCodecheckYaml' => true],
                ['url' => 'https://github.com/c/three'],
            ],
        ]);

        $this->assertSame(
            [true, false, false],
            array_map(
                fn ($entry) => !empty($entry['containsCodecheckYaml']),
                $normalised['repositories']
            )
        );
    }

    /**
     * Clicking "codecheck.yml" on a row before typing its address is two
     * ordinary clicks. Every reader skips an entry with no address, so keeping
     * the flag there would show the editor a selection that publication
     * validation then reports as missing.
     */
    public function testWithOneMarkedDropsAFlagFromAnEntryWithNoAddress()
    {
        $normalised = CodecheckRepositories::withOneMarked([
            'repositories' => [
                ['url' => '', 'containsCodecheckYaml' => true],
                ['url' => 'https://github.com/a/one'],
            ],
        ]);

        $this->assertFalse($normalised['repositories'][0]['containsCodecheckYaml']);
        $this->assertNull(CodecheckRepositories::selectedUrl($normalised));
    }

    /** The bare list the editorial form posts gets the same treatment. */
    public function testWithOneMarkedNormalisesABareList()
    {
        $normalised = CodecheckRepositories::withOneMarked([
            ['url' => 'https://github.com/a/one', 'containsCodecheckYaml' => true],
            ['url' => 'https://github.com/b/two', 'containsCodecheckYaml' => true],
        ]);

        $this->assertSame(
            [true, false],
            array_map(fn ($entry) => $entry['containsCodecheckYaml'], $normalised)
        );
    }

    public function testWithOneMarkedLeavesAWellFormedBlobAlone()
    {
        $blob = ['repositories' => [['url' => 'https://github.com/a/one', 'containsCodecheckYaml' => true]]];

        $this->assertSame($blob, CodecheckRepositories::withOneMarked($blob));
    }

    /** The blob is read from JSON, from decoded arrays and from stdClass. */
    public function testTheBlobIsReadWhateverShapeItArrivesIn()
    {
        $expected = 'https://github.com/a/one';
        $array = ['repositories' => [['url' => $expected, 'containsCodecheckYaml' => true]]];

        $this->assertSame($expected, CodecheckRepositories::selectedUrl($array));
        $this->assertSame($expected, CodecheckRepositories::selectedUrl(json_encode($array)));
        $this->assertSame($expected, CodecheckRepositories::selectedUrl(json_decode(json_encode($array))));
    }

    /**
     * The pre-#154 index is not read back. The migration converts it, and
     * accepting both shapes would let one record resolve two ways.
     */
    public function testALegacyIndexIsIgnored()
    {
        $this->assertNull(CodecheckRepositories::selectedUrl([
            'repositories' => [
                ['url' => 'https://github.com/a/one'],
                ['url' => 'https://github.com/b/two'],
            ],
            'repoWithCodecheckYaml' => 1,
        ]));
    }

    /** The editorial form posts a bare list, the register issue a list of URLs. */
    public function testABareListIsReadAsWell()
    {
        $this->assertSame(
            ['https://github.com/a/one'],
            CodecheckRepositories::publicUrls([
                ['url' => 'https://github.com/a/one'],
                ['url' => 'https://github.com/b/two', 'hidden' => true],
            ])
        );

        $this->assertSame(
            ['https://github.com/a/one'],
            CodecheckRepositories::publicUrls(['https://github.com/a/one'])
        );
    }

    public function testUnusableUrlsNamesOnlyTheAddressesThatCannotBeLinks()
    {
        $this->assertSame(
            ['javascript:alert(1)', 'data:text/html,x'],
            CodecheckRepositories::unusableUrls(['repositories' => [
                ['url' => 'https://github.com/a/one'],
                ['url' => 'javascript:alert(1)'],
                ['url' => ''],
                ['url' => 'data:text/html,x'],
                ['url' => 'HTTP://github.com/b/two'],
            ]])
        );
    }

    public function testMalformedDataIsEmptyRatherThanFatal()
    {
        foreach ([null, '', '{not json', ['repositories' => 'a string'], 42] as $malformed) {
            $this->assertSame([], CodecheckRepositories::publicEntries($malformed));
            $this->assertNull(CodecheckRepositories::selectedUrl($malformed));
        }
    }
}
