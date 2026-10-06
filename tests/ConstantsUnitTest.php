<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * The config version list and the specification URL built from it are mirrored
 * in CodecheckMetadataForm.vue, so both sides are pinned rather than assumed.
 */
class ConstantsUnitTest extends PKPTestCase
{
    #[DataProvider('webUrlProvider')]
    public function testOnlyWebAddressesMayBeLinked(string $url, bool $expected, string $why)
    {
        $this->assertSame($expected, Constants::isWebUrl($url), $why);
    }

    public static function webUrlProvider(): array
    {
        return [
            ['https://github.com/codecheckers/repo', true,  'an ordinary https address'],
            ['http://doi.org/10.5281/zenodo.3750741', true, 'http is still a web address; real records use it'],
            ['HTTPS://EXAMPLE.ORG', true,  'the scheme is case-insensitive'],
            ['  https://example.org  ', true, 'surrounding whitespace is not meaningful'],

            // The whole reason this helper exists: filter_var() accepts the next
            // two, which is how a javascript: URL reached an href on the public
            // article page and the issue table of contents.
            ['javascript://x%0Aalert(1)', false, 'the filter_var bypass must be refused'],
            ['JaVaScRiPt://x%0Aalert(1)', false, 'and in any casing'],
            ['javascript:alert(1)', false, 'the plain form too'],
            ['data:text/html;base64,PHNjcmlwdD4=', false, 'data: is not a web address'],
            ['ftp://example.org/x', false, 'nor ftp'],
            ['//example.org', false, 'a scheme-relative address has no scheme to check'],
            ['/relative/path', false, 'nor a path'],
            ['2025-001', false, 'a register identifier is not a URL'],
            ['', false, 'empty is not a URL'],
        ];
    }

    public function testFilterVarWouldNotBeEnough()
    {
        // Pinned deliberately: if a future PHP tightens FILTER_VALIDATE_URL this
        // test fails and the comments explaining why we do not use it can go.
        $this->assertNotFalse(
            filter_var('javascript://x%0Aalert(1)', FILTER_VALIDATE_URL),
            'FILTER_VALIDATE_URL still accepts a javascript: URL, so isWebUrl() is still needed'
        );
        $this->assertFalse(Constants::isWebUrl('javascript://x%0Aalert(1)'));
    }

    public function testConfigSpecUrlIsBuiltFromTheVersion()
    {
        $this->assertSame(
            'https://codecheck.org.uk/spec/config/2.0/',
            Constants::getConfigSpecUrl('2.0')
        );
    }

    public function testEveryKnownConfigVersionHasASpecUrl()
    {
        $this->assertNotEmpty(Constants::CODECHECK_CONFIG_VERSIONS);

        foreach (Constants::CODECHECK_CONFIG_VERSIONS as $version) {
            $this->assertStringStartsWith(
                Constants::CODECHECK_CONFIG_SPEC_URL,
                Constants::getConfigSpecUrl($version)
            );
            $this->assertStringEndsWith('/', Constants::getConfigSpecUrl($version));
        }
    }

    public function testOnlyConcreteVersionsAreKnown()
    {
        // `latest` named 1.0 until the specification moved it to 2.0, and every
        // record on it began declaring a version it was not filled in against.
        // The plugin implements 2.0 alone; 1.0 went before any journal used it.
        $this->assertSame(['2.0'], Constants::CODECHECK_CONFIG_VERSIONS);
        $this->assertNotContains('latest', Constants::CODECHECK_CONFIG_VERSIONS);
    }

    public function testTheDefaultIsTheCurrentStableSpecificationOnly()
    {
        // Mirrored by CODECHECK_DEFAULT_CONFIG_VERSIONS in
        // CodecheckMetadataForm.vue.
        $this->assertSame(['2.0'], Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS);
    }

    /**
     * A record the upgrade missed, or a version an API client posted, reads as
     * the default rather than declaring a specification the plugin does not
     * implement.
     */
    public function testAConfigVersionThePluginDoesNotKnowResolvesToTheDefault()
    {
        $this->assertSame('2.0', Constants::resolveConfigVersion('2.0'));

        foreach (['latest', '1.0', '', ' 2.0', '2', null] as $version) {
            $this->assertSame(
                Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS[0],
                Constants::resolveConfigVersion($version),
                var_export($version, true)
            );
        }
    }

    /**
     * 1.0 and `latest` are not special: like a version a later release adds,
     * they are simply not known, which is what makes the save refuse them.
     */
    public function testOnlyAnImplementedConfigVersionIsKnown()
    {
        $this->assertTrue(Constants::isKnownConfigVersion('2.0'));

        foreach (['latest', '1.0', '2.1', '', ' 2.0', '2', 2.0, null, true, ['2.0']] as $version) {
            $this->assertFalse(Constants::isKnownConfigVersion($version), var_export($version, true));
        }
    }

    /**
     * A record may stay on a version the journal has stopped offering, and
     * nothing may be moved onto one it does not offer. Written against a second
     * version, `2.1`, which the plugin does not know yet: the rule has to hold
     * the day it does, and today only the known half can be seen end to end.
     */
    public function testAVersionMustBeKnownAndOfferedOrAlreadyStored()
    {
        $this->assertTrue(Constants::isConfigVersionAllowed('2.0', ['2.0'], null), 'offered');
        $this->assertTrue(Constants::isConfigVersionAllowed('2.0', ['2.0'], '2.0'), 'offered and stored');
        $this->assertTrue(Constants::isConfigVersionAllowed('2.0', [], '2.0'), 'stored, no longer offered');
        $this->assertFalse(Constants::isConfigVersionAllowed('2.0', [], null), 'known, not offered, not stored');
        $this->assertFalse(Constants::isConfigVersionAllowed('2.0', ['2.1'], '2.1'), 'known, not offered, record on another');
        $this->assertFalse(Constants::isConfigVersionAllowed('2.1', ['2.1'], '2.1'), 'offered and stored but not known to the plugin');
        $this->assertFalse(Constants::isConfigVersionAllowed('latest', ['latest'], 'latest'), 'unknown, whatever else is true');

        foreach ([null, 2.0, ['2.0'], true] as $posted) {
            $this->assertFalse(Constants::isConfigVersionAllowed($posted, ['2.0'], '2.0'), var_export($posted, true));
        }
    }

    public function testTheDefaultOnlyNamesVersionsThePluginKnows()
    {
        $this->assertNotEmpty(Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS);

        $this->assertSame(
            [],
            array_diff(
                Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS,
                Constants::CODECHECK_CONFIG_VERSIONS
            )
        );
    }

    /**
     * The value written into `plugin_settings` and the value a reader resolves
     * are the same constant, so the two cannot drift — which is #177.
     */
    public function testTheRegisterDepositDefaultIsRecordedOnce()
    {
        $this->assertTrue(Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED_DEFAULT);
        $this->assertSame(
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED_DEFAULT,
            Constants::CODECHECK_SETTING_DEFAULTS[Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED]
        );
    }

    /**
     * #178: the display switches and the version list used to resolve their
     * default at each read site, two readers apiece. The map is now the one
     * record of what an absent row means.
     */
    public function testEveryDisplaySwitchHasARecordedDefault()
    {
        foreach ([
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            Constants::CODECHECK_SHOW_IN_TOC,
        ] as $name) {
            $this->assertSame(true, Constants::CODECHECK_SETTING_DEFAULTS[$name], $name);
        }
    }

    /**
     * The colour reaches a `style` attribute on a public page, and the rule
     * for it used to be written out four times — two copies of this pattern
     * and two weaker `?:` fallbacks that disagreed with them about a stored
     * value that is not a colour.
     *
     * @param mixed $stored what a journal has in `plugin_settings`
     */
    #[DataProvider('badgeTextColorProvider')]
    public function testNormalizeBadgeTextColor(mixed $stored, string $expected)
    {
        $this->assertSame($expected, Constants::normalizeBadgeTextColor($stored));
    }

    public static function badgeTextColorProvider(): array
    {
        $default = Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT;

        return [
            'a hex colour' => ['#123abc', '#123abc'],
            'upper case' => ['#12AB34', '#12AB34'],
            'surrounding whitespace' => ['  #123abc  ', '#123abc'],
            'nothing recorded' => [null, $default],
            'the field cleared' => ['', $default],
            'a colour name' => ['red', $default],
            'three-digit shorthand' => ['#abc', $default],
            'too few digits' => ['#12345', $default],
            'too many digits' => ['#1234567', $default],
            'not hex digits' => ['#12g456', $default],
            'something that closes the attribute' => ['green; content:"x"', $default],
        ];
    }

    /**
     * #178 left the badge height with two readers: the settings form stored
     * `(int) ''` — zero — for a cleared field and showed that back, while the
     * article page read `0 ?: 24`. The rule lives here now, and it judges the
     * stored value rather than only its absence — which is why the height is
     * deliberately *not* in the map: a stored `0` is what has to be caught,
     * and a written row would make a later change to this pixel value need an
     * upgrade migration.
     */
    public function testTheBadgeHeightDefaultIsNotWrittenIntoJournals()
    {
        $this->assertSame(24, Constants::CODECHECK_BADGE_HEIGHT_DEFAULT);
        $this->assertArrayNotHasKey(
            Constants::CODECHECK_BADGE_HEIGHT,
            Constants::CODECHECK_SETTING_DEFAULTS
        );
    }

    /**
     * @param mixed $stored what a journal has in `plugin_settings`, or typed
     *                      into the settings form
     */
    #[DataProvider('badgeHeightProvider')]
    public function testNormalizeBadgeHeight(mixed $stored, int $expected)
    {
        $this->assertSame($expected, Constants::normalizeBadgeHeight($stored));
    }

    public static function badgeHeightProvider(): array
    {
        return [
            'a plain number' => [40, 40],
            'a number as a string, as plugin_settings stores it' => ['40', 40],
            'nothing recorded' => [null, 24],
            'the field cleared' => ['', 24],
            'the zero a cleared field used to store' => [0, 24],
            'the same zero as a string' => ['0', 24],
            'a negative height' => [-10, 24],
            'not a number at all' => ['tall', 24],
            // The form's min/max is advisory — PKP posts the form itself — and
            // the value lands in a style attribute on a public page.
            'below what the form offers' => [4, 10],
            'above what the form offers' => [100000, 200],
            'the ends of the range themselves' => [200, 200],
        ];
    }

    /**
     * #164: a journal's own wording is stored per locale, and a reader whose
     * language has none gets the primary language's before the plugin's.
     *
     * @param mixed $stored what a journal has in `plugin_settings`
     */
    #[DataProvider('localizedTextProvider')]
    public function testLocalizedText(mixed $stored, ?string $expected)
    {
        $this->assertSame($expected, Constants::localizedText($stored, 'de', 'en'));
    }

    public static function localizedTextProvider(): array
    {
        return [
            "the reader's language" => [['en' => 'Availability', 'de' => 'Verfügbarkeit'], 'Verfügbarkeit'],
            'surrounding whitespace' => [['de' => '  Verfügbarkeit '], 'Verfügbarkeit'],
            'the primary language when the reader\'s has none' => [['en' => 'Availability'], 'Availability'],
            'the primary language when the reader\'s is blank' => [['en' => 'Availability', 'de' => '  '], 'Availability'],
            'neither language' => [['fr' => 'Disponibilité'], null],
            'nothing recorded' => [null, null],
            'an empty list' => [[], null],
            'the unreleased single-language shape' => ['Availability', null],
            'not a string' => [['de' => ['x'], 'en' => 42], null],
        ];
    }

    /** What is saved: trimmed, the form's languages only, empty ones dropped. */
    public function testCleanLocalizedTextKeepsOnlyTheFormsLanguagesWithText()
    {
        $this->assertSame(
            ['en' => 'Availability'],
            Constants::cleanLocalizedText(
                ['en' => ' Availability ', 'de' => '  ', 'fr' => 'Disponibilité'],
                ['en', 'de']
            )
        );
    }

    /**
     * A recorded default is *written* into a row, and the writers never
     * reconcile, so a setting whose default is expected to change must stay
     * out of the map: a journal enabled today would otherwise keep being
     * offered 1.0 long after 1.1 became the stable specification.
     */
    public function testTheConfigVersionDefaultIsNotWrittenIntoJournals()
    {
        $this->assertArrayNotHasKey(
            Constants::CODECHECK_ENABLED_CONFIG_VERSIONS,
            Constants::CODECHECK_SETTING_DEFAULTS
        );
    }

    /** Only the two choices are stored as such; anything else reads as daily (#65). */
    public function testNormalizeListsRefresh()
    {
        $this->assertSame('weekly', Constants::normalizeListsRefresh('weekly'));
        $this->assertSame('daily', Constants::normalizeListsRefresh('daily'));
        foreach ([null, '', 'hourly', 'Weekly', ['weekly'], 7] as $stored) {
            $this->assertSame('daily', Constants::normalizeListsRefresh($stored));
        }
    }

    public static function bareDoiProvider(): array
    {
        return [
            'bare' => ['10.5281/zenodo.1', '10.5281/zenodo.1'],
            'prefixed' => ['doi: 10.5281/zenodo.1', '10.5281/zenodo.1'],
            'a link' => ['https://doi.org/10.5281/zenodo.1', '10.5281/zenodo.1'],
            'an old link' => ['http://dx.doi.org/10.5281/zenodo.1', '10.5281/zenodo.1'],
            'a link without scheme' => ['doi.org/10.5281/zenodo.1', '10.5281/zenodo.1'],
            'surrounding space' => ['  10.5281/zenodo.1 ', '10.5281/zenodo.1'],
            'a pasted non-breaking space' => ["10.5281/zenodo.1\u{00A0}", '10.5281/zenodo.1'],
            'no suffix' => ['10.5281', null],
            'a space inside' => ['10.5281/zenodo 1', null],
            'another address' => ['https://zenodo.org/records/1', null],
            'nothing' => [null, null],
        ];
    }

    /** The one DOI rule for what is typed in (#190), shared with the resolver. */
    #[DataProvider('bareDoiProvider')]
    public function testReadsTheDoiAnAddressNames(?string $value, ?string $expected)
    {
        $this->assertSame($expected, Constants::bareDoi($value));
    }
}
