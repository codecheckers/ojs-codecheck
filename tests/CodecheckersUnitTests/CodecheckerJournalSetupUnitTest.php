<?php

/**
 * @file tests/CodecheckersUnitTests/CodecheckerJournalSetupUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerJournalSetupUnitTest
 *
 * @brief Which languages the Codechecker role and the invitation template are
 *   named in (#13). Creating them needs the database and is covered by e2e.
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckersUnitTests;

use APP\plugins\generic\codecheck\classes\Codecheckers\CodecheckerJournalSetup;
use PKP\tests\PKPTestCase;

class CodecheckerJournalSetupUnitTest extends PKPTestCase
{
    private const TRANSLATIONS = ['en' => 'Codechecker', 'de' => 'Codechecker:in'];

    private static function texts(array $locales, string $primaryLocale): array
    {
        return CodecheckerJournalSetup::textsIn(
            $locales,
            $primaryLocale,
            fn (string $locale) => self::TRANSLATIONS[$locale] ?? '##missing##',
            fn (string $text) => !str_starts_with($text, '##')
        );
    }

    public function testEachTranslatedLanguageGetsItsOwnText(): void
    {
        $this->assertSame(
            ['en' => 'Codechecker', 'de' => 'Codechecker:in'],
            self::texts(['en', 'de'], 'en')
        );
    }

    public function testAnUntranslatedLanguageIsLeftOut(): void
    {
        $this->assertSame(['en' => 'Codechecker'], self::texts(['en', 'fr'], 'en'));
    }

    public function testAnUntranslatedPrimaryLanguageGetsTheEnglishText(): void
    {
        $this->assertSame(
            ['de' => 'Codechecker:in', 'fr' => 'Codechecker'],
            self::texts(['de'], 'fr')
        );
    }
}
