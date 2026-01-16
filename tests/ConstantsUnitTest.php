<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Constants;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/ConstantsUnitTest.php
 *
 * @class ConstantsUnitTest
 *
 * @brief Tests for the Constants class
 */
class ConstantsUnitTest extends PKPTestCase
{
    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSettingsTemplateConstant()
    {
        $this->assertSame('settings.tpl', Constants::SETTINGS_TEMPLATE);
    }

    public function testSettingEnableCodecheckConstant()
    {
        $this->assertSame('enableCodecheck', Constants::SETTING_ENABLE_CODECHECK);
    }

    public function testCodecheckEnabledConstant()
    {
        $this->assertSame('enabled', Constants::CODECHECK_ENABLED);
    }

    public function testCodecheckApiEndpointConstant()
    {
        $this->assertSame('codecheckApiEndpoint', Constants::CODECHECK_API_ENDPOINT);
    }

    public function testCodecheckApiKeyConstant()
    {
        $this->assertSame('codecheckApiKey', Constants::CODECHECK_API_KEY);
    }

    public function testAllConstantsAreStrings()
    {
        $reflection = new \ReflectionClass(Constants::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $value) {
            $this->assertIsString($value, "Constant {$name} should be a string");
        }
    }

    public function testAllConstantsAreUnique()
    {
        $reflection = new \ReflectionClass(Constants::class);
        $constants = $reflection->getConstants();
        $values = array_values($constants);

        $this->assertSame(
            count($values),
            count(array_unique($values)),
            "All constant values should be unique"
        );
    }
}