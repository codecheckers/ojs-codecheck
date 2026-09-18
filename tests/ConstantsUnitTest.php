<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Constants;
use PKP\tests\PKPTestCase;

/**
 * The config version list and the specification URL built from it are mirrored
 * in CodecheckMetadataForm.vue, so both sides are pinned rather than assumed.
 */
class ConstantsUnitTest extends PKPTestCase
{
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
}
