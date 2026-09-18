<?php

/**
 * @file tests/FakeTranslator.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FakeTranslator
 * @brief Minimal translator so `__()` works in unit tests.
 *
 * `__()` resolves through Laravel's container, which OJS populates when the
 * application boots. The unit tests deliberately do not boot the application —
 * that would need a database and turn them into integration tests — so the
 * container has no `translator` binding and any code calling `__()` dies with
 * "Target class [translator] does not exist".
 *
 * This returns the locale key itself rather than a translation. Tests should
 * assert on structure and identifiers, not on translated text; if a test needs
 * real strings it wants the e2e suite instead.
 *
 * Parameter substitution mimics PKP's `{$name}` syntax rather than Laravel's
 * `:name`, so a key rendered here looks like it would in the application.
 */

namespace APP\plugins\generic\codecheck\tests;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;

class FakeTranslator implements TranslatorContract
{
    private string $locale = 'en';

    public function get($key, array $replace = [], $locale = null)
    {
        foreach ($replace as $name => $value) {
            $key = str_replace('{$' . $name . '}', (string) $value, $key);
        }

        return $key;
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        return $this->get($key, $replace, $locale);
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }
}
