<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueNames;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CodecheckVenueNamesUnitTest.php
 *
 * @class CodecheckVenueNamesUnitTest
 *
 * @brief Tests for the CodecheckVenueNames class
 */
class CodecheckVenueNamesUnitTest extends PKPTestCase
{
    protected function setUp(): void
	{
		parent::setUp();
	}

    public function testVenueNames()
    {
        $jsonApiMockVenueTypes = $this->createMock(CodecheckApiClient::class);
        $jsonApiMockVenueTypes->expects($this->once())
                                ->method('fetch')
                                ->with('https://codecheck.org.uk/register/venues/index.json');

        $jsonApiMockVenueTypes->method('getData')->willReturn([
            ['Venue type' => 'journal'],
            ['Venue type' => 'community'],
        ]);

        $venueTypes = new CodecheckVenueTypes($jsonApiMockVenueTypes);

        $jsonApiMockVenueNames = $this->createMock(CodecheckApiClient::class);
        $jsonApiMockVenueNames->expects($this->once())
                                ->method('fetch')
                                ->with('https://codecheck.org.uk/register/venues/index.json');
                                
        $jsonApiMockVenueNames->method('getData')->willReturn([
            ["Issue label" => 'journal'],
            ["Issue label" => 'lifecycle journal'],
            ["Issue label" => 'community'],
            ["Issue label" => 'conference'],
            ["Issue label" => 'check-nl'],
            ["Issue label" => 'preprint'],
            ["Issue label" => 'development'],
        ]);

        $venueNames = new CodecheckVenueNames($jsonApiMockVenueNames, $venueTypes);
        $result = $venueNames->get()->toArray();

        $this->assertEquals(
            ['lifecycle journal', 'conference', 'check-nl', 'preprint'],
            $result
        );
    }

    public function testVenueNamesCurlReadExceptionCheckThatErrorAndErrnoAreCurlSpecific()
    {
        $testCurlHandle = curl_init();

        $clientMock = $this->createMock(CodecheckApiClient::class);
        $clientMock->method('fetch')->will($this->throwException(new CurlReadException($testCurlHandle)));

        $this->expectException(CurlReadException::class);
        $this->expectExceptionMessage(curl_error($testCurlHandle));
        $this->expectExceptionCode(curl_errno($testCurlHandle));

        new CodecheckVenueNames($clientMock);
    }
}