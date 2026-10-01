<?php

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckRegisterUnitTests/CodecheckPostOriginUnitTest.php
 *
 * @class CodecheckPostOriginUnitTest
 *
 * @brief The signature closing every register post, and the origin metadata
 *
 * fromContext() reads the running OJS and is covered by the live register
 * tests; everything it feeds into is pinned here.
 */
class CodecheckPostOriginUnitTest extends PKPTestCase
{
    private function origin(mixed $signature = null): CodecheckPostOrigin
    {
        return new CodecheckPostOrigin(
            'CODECHECK Demo Journal',
            'https://journal.example/index.php/demo',
            '3.5.0.3',
            '0.1.0.0',
            $signature
        );
    }

    public static function unsetOrEmpty(): array
    {
        return [
            'never saved' => [null],
            'cleared' => [''],
            'only whitespace' => ["  \n "],
            'not a string' => [['en' => 'a stray multilingual value']],
        ];
    }

    #[DataProvider('unsetOrEmpty')]
    public function testAnUnsetOrEmptySignatureIsTheDefault(mixed $stored)
    {
        $this->assertSame(
            "\n\n---\n*Posted by the [CODECHECK plugin for OJS](https://github.com/codecheckers/ojs-codecheck)"
            . ' from [CODECHECK Demo Journal](https://journal.example/index.php/demo).*',
            $this->origin($stored)->signature()
        );
    }

    public function testAJournalsOwnSignatureIsUsedWithItsPlaceholdersFilledIn()
    {
        $this->assertSame(
            "\n\n---\nSent by {$this->origin()->getJournalName()} (https://journal.example/index.php/demo)",
            $this->origin('  Sent by {$journal} ({$journalUrl})  ')->signature()
        );
    }

    /** Only the two placeholders are filled; anything else is the journal's text. */
    public function testOtherBracesAreLeftAlone()
    {
        $this->assertStringEndsWith('{$unknown} {journal}', $this->origin('{$unknown} {journal}')->signature());
    }

    public static function journalUrls(): array
    {
        return [
            'plain' => ['demo', null, 'https://ojs.example/ojs', false, 'https://ojs.example/ojs/index.php/demo'],
            'restful urls' => ['demo', null, 'https://ojs.example/ojs', true, 'https://ojs.example/ojs/demo'],
            'trailing slash on the base' => ['demo', null, 'https://ojs.example/', false, 'https://ojs.example/index.php/demo'],
            'a path with characters to encode' => ['a b', null, 'https://ojs.example', false, 'https://ojs.example/index.php/a%20b'],
            // OJS uses an override as it stands, without the path or index.php.
            'a base_url override' => ['demo', 'https://demo.example/', 'https://ojs.example', false, 'https://demo.example'],
            'an empty override is none' => ['demo', '', 'https://ojs.example', false, 'https://ojs.example/index.php/demo'],
        ];
    }

    /**
     * Built from the configuration, never from the request's Host header, which
     * a user who can trigger a status change could otherwise choose.
     */
    #[DataProvider('journalUrls')]
    public function testTheJournalUrlFollowsTheConfiguration(
        string $path,
        ?string $override,
        string $baseUrl,
        bool $restful,
        string $expected
    ) {
        $this->assertSame($expected, CodecheckPostOrigin::journalUrl($path, $override, $baseUrl, $restful));
    }

    /** A journal name is text in a Markdown link; its own brackets must not break it. */
    public function testMarkdownInTheJournalNameIsEscaped()
    {
        $origin = new CodecheckPostOrigin('Journal [Beta] *1*', 'https://j.example', null, null, null);

        $this->assertStringContainsString('[Journal \\[Beta\\] \\*1\\*](https://j.example)', $origin->signature());
    }
}
