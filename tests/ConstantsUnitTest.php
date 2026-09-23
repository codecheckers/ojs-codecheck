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
            'https://codecheck.org.uk/spec/config/latest/',
            Constants::getConfigSpecUrl('latest')
        );
        $this->assertSame(
            'https://codecheck.org.uk/spec/config/1.0/',
            Constants::getConfigSpecUrl('1.0')
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

    public function testLatestIsListedFirst()
    {
        // The settings form renders the list in order, newest first.
        $this->assertSame('latest', Constants::CODECHECK_CONFIG_VERSIONS[0]);
    }

    public function testTheDefaultIsTheCurrentStableSpecificationOnly()
    {
        // A journal that has not chosen offers 1.0 rather than the moving
        // target, so a check records the specification it was actually done
        // against. Mirrored by CODECHECK_DEFAULT_CONFIG_VERSIONS in
        // CodecheckMetadataForm.vue.
        $this->assertSame(['1.0'], Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS);
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
}
