<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CodecheckVenueTypesUnitTest.php
 *
 * @class CodecheckVenueTypesUnitTest
 *
 * @brief Tests for the CodecheckVenueTypes class
 */
class CodecheckVenueTypesUnitTest extends PKPTestCase
{
    protected function setUp(): void
	{
		parent::setUp();
	}

    public function testVenueTypes()
    {
        $apiCallerMock = $this->createMock(CodecheckApiClient::class);
        $apiCallerMock->method('fetch');

        $venueTypesArray = [
            ['Venue type' => 'journal'],
            ['Venue type' => 'community'],
            ['Venue type' => 'conference'],
            ['Venue type' => 'institution'],
        ];

        $apiCallerMock->method('getData')->willReturn($venueTypesArray);
        $venueTypes = new CodecheckVenueTypes($apiCallerMock);
        $expectedVenueTypesArray = array_column($venueTypesArray, 'Venue type');

        $this->assertSame($expectedVenueTypesArray, $venueTypes->get()->toArray());
    }

    public function testVenueTypesCurlReadExceptionCheckThatErrorAndErrnoAreCurlSpecific()
    {
        $testCurlHandle = curl_init();

        $clientMock = $this->createMock(CodecheckApiClient::class);
        $clientMock->method('fetch')->will($this->throwException(new CurlReadException($testCurlHandle)));

        $venueTypesArray = [
            ['Venue type' => 'journal'],
            ['Venue type' => 'community'],
            ['Venue type' => 'conference'],
            ['Venue type' => 'institution'],
        ];

        $clientMock->method('getData')->willReturn($venueTypesArray);

        $this->expectException(CurlReadException::class);
        $this->expectExceptionMessage(curl_error($testCurlHandle));
        $this->expectExceptionCode(curl_errno($testCurlHandle));

        new CodecheckVenueTypes($clientMock);
    }
}