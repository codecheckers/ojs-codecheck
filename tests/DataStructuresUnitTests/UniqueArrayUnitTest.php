<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/RetrieveReserveIdentifiersUnitTests/UniqueArrayUnitTest.php
 *
 * @class UniqueArrayUnitTest
 *
 * @brief Tests for the UniqueArray class
 */
class UniqueArrayUnitTest extends PKPTestCase
{
    /**
     * Set up the test environment
     */
    protected function setUp(): void
	{
		parent::setUp();
	}

    public function testUniqueArrayRemove()
    {
        $uniqueArray = new UniqueArray();
        $uniqueArray->add(1);
        $uniqueArray->add(2);
        $this->assertCount(2, $uniqueArray->toArray());
        $uniqueArray->remove(1);
        $this->assertCount(1, $uniqueArray->toArray());
        $this->assertEquals([1], $uniqueArray->toArray());
    }

    public function testUniqueArrayFrom()
    {
        $array = ['some', 'array', 'content', 4];
        $uniqueArray = UniqueArray::from($array);
        $this->assertCount(4, $uniqueArray->toArray());
        $this->assertEquals($array, $uniqueArray->toArray());
    }
}