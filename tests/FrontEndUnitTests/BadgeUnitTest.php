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

    public function testTheBadgeLinksToTheRegisterUntilTheJournalChoosesOtherwise()
    {
        $badge = $this->badgeWithSettings([]);

        $this->assertSame(Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER, $badge->getLinkTarget());
        $this->assertSame(
            'https://codecheck.org.uk/register/certs/2020-001/',
            $badge->getCertificateUrl('2020-001', 'https://doi.org/10.5281/zenodo.123')
        );
    }

    public function testAJournalCanSendReadersToTheDoiInstead()
    {
        $badge = $this->badgeWithSettings([
            Constants::CODECHECK_BADGE_LINK_TARGET => Constants::CODECHECK_BADGE_LINK_TARGET_DOI,
        ]);

        $this->assertSame(
            'https://doi.org/10.5281/zenodo.123',
            $badge->getCertificateUrl('2020-001', 'https://doi.org/10.5281/zenodo.123')
        );
    }

    public function testTheOtherTargetStandsInWhenThePreferredOneIsMissing()
    {
        // A badge linking nowhere is worse than one linking to the second
        // choice, and either value can be absent on a real record.
        $doiPreferred = $this->badgeWithSettings([
            Constants::CODECHECK_BADGE_LINK_TARGET => Constants::CODECHECK_BADGE_LINK_TARGET_DOI,
        ]);
        $this->assertSame(
            'https://codecheck.org.uk/register/certs/2020-001/',
            $doiPreferred->getCertificateUrl('2020-001', '')
        );

        $this->assertSame(
            'https://doi.org/10.5281/zenodo.123',
            $this->badgeWithSettings([])->getCertificateUrl('', 'https://doi.org/10.5281/zenodo.123')
        );
    }

    public function testACertificateThatIsNotAWebAddressIsNotUsedAsOne()
    {
        // The certificate is editor-supplied free text and lands in an href on
        // the article page and in the issue table of contents. It used to be
        // admitted by filter_var(), which accepts `javascript://…`; anything
        // that is not http(s) now falls through to the register URL builder,
        // which returns '' for a value that is not a YYYY-NNN identifier.
        $badge = $this->badgeWithSettings([]);

        $this->assertSame('', $badge->getCertificateUrl('javascript://x%0Aalert(1)', ''));
        $this->assertSame('', $badge->getCertificateUrl('JaVaScRiPt://x%0Aalert(1)', ''));
        $this->assertSame('', $badge->getCertificateUrl('data:text/html,<script>', ''));

        // A real URL is still honoured, and a register identifier still becomes
        // its landing page.
        $this->assertSame(
            'https://codecheck.org.uk/register/certs/2020-001/',
            $badge->getCertificateUrl('https://codecheck.org.uk/register/certs/2020-001/', '')
        );
        $this->assertSame(
            'https://codecheck.org.uk/register/certs/2020-001/',
            $badge->getCertificateUrl('2020-001', '')
        );
    }

    public function testWithNeitherATargetThereIsNoLink()
    {
        // The caller renders the badge unlinked rather than with href="".
        $this->assertSame('', $this->badgeWithSettings([])->getCertificateUrl('', ''));
        $this->assertSame('', $this->badgeWithSettings([])->getCertificateUrl('not an identifier', ''));
    }

    public function testACertificateRecordedAsAUrlIsUsedAsIs()
    {
        $this->assertSame(
            'https://example.org/certificates/7',
            $this->badgeWithSettings([])->getCertificateUrl('https://example.org/certificates/7', '')
        );
    }

    public function testAnUnknownStoredTargetFallsBackToTheRegister()
    {
        $badge = $this->badgeWithSettings([Constants::CODECHECK_BADGE_LINK_TARGET => 'somewhere else']);

        $this->assertSame(Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER, $badge->getLinkTarget());
    }

    public function testTheHeightDefaultsToTwentyFourPixels()
    {
        $this->assertSame('height:24px; width:auto;', $this->badgeWithSettings([])->getStyle());

        $this->assertSame(
            'height:40px; width:auto;',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_HEIGHT => '40'])->getStyle()
        );

        // The rule for what is not a height lives in Constants and is pinned
        // there; here it only has to be reached. A row written before #178
        // holds the zero a cleared field used to store.
        $this->assertSame(
            'height:24px; width:auto;',
            $this->badgeWithSettings([Constants::CODECHECK_BADGE_HEIGHT => 0])->getStyle()
        );
    }
}
