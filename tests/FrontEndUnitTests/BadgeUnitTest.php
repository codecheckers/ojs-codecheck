<?php

namespace APP\plugins\generic\codecheck\tests\FrontEndUnitTests;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\FrontEnd\Badge;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\tests\PKPTestCase;

/**
 * The article sidebar and the issue table of contents render the same badge
 * from the same settings, so the rules live in one place and are pinned here.
 *
 * `getUrl()` is left to the e2e suite: it builds an absolute URL from the
 * request's base URL, which needs a booted application.
 *
 * `__()` returns the locale key in these tests (see tests/FakeTranslator.php),
 * so the fallback is asserted as a key rather than as English text.
 */
class BadgeUnitTest extends PKPTestCase
{
    private const CONTEXT_ID = 1;

    /** Builds a badge whose plugin answers settings from $settings. */
    private function badgeWithSettings(array $settings): Badge
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $plugin->method('getSetting')->willReturnCallback(
            fn ($contextId, $name) => $settings[$name] ?? null
        );

        return new Badge($plugin, self::CONTEXT_ID);
    }

    public function testTheTypeDefaultsToTheCodeWorksBadge()
    {
        $this->assertSame('codeworks', $this->badgeWithSettings([])->getType());

        $this->assertSame(
            'none',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_TYPE => 'none'])->getType()
        );
    }

    public function testTheTextIsWhateverTheJournalSet()
    {
        $this->assertSame(
            'Reproducible',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_TEXT => 'Reproducible'])->getText()
        );
    }

    public function testTheTextFallsBackToTheLocalisedDefaultWhenCleared()
    {
        foreach ([[], [Constants::CODECHECK_BADGE_TEXT => '   ']] as $settings) {
            $this->assertSame(
                'plugins.generic.codecheck.badge.textOnly',
                $this->badgeWithSettings($settings)->getText()
            );
        }
    }

    public function testTheTextColourDefaultsToTheCodecheckGreen()
    {
        $this->assertSame(
            Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT,
            $this->badgeWithSettings([])->getTextColor()
        );

        $this->assertSame(
            '#123abc',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_TEXT_COLOR => '#123abc'])->getTextColor()
        );
    }

    public function testAnythingThatIsNotAHexColourFallsBackToTheDefault()
    {
        // The value ends up inside a style attribute on a public page, so a
        // stored value that is not a colour must never reach it.
        foreach (['', '   ', 'red', '#12345', '#1234567', 'green; content:"x"', '#12g456'] as $stored) {
            $this->assertSame(
                Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT,
                $this->badgeWithSettings([Constants::CODECHECK_BADGE_TEXT_COLOR => $stored])->getTextColor(),
                'stored value: ' . var_export($stored, true)
            );
        }
    }

    public function testTheHeightDefaultsToTwentyFourPixels()
    {
        $this->assertSame('height:24px; width:auto;', $this->badgeWithSettings([])->getStyle());

        $this->assertSame(
            'height:40px; width:auto;',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_HEIGHT => '40'])->getStyle()
        );

        // A height cleared to an empty string is not a height of zero.
        $this->assertSame(
            'height:24px; width:auto;',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_HEIGHT => ''])->getStyle()
        );
    }
}
