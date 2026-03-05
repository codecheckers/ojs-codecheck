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