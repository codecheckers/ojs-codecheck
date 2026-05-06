<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CertificateIdentifierUnitTests.php
 *
 * @class CertificateIdentifierUnitTest
 *
 * @brief Tests for the CertificateIdentifier class
 */
class CertificateIdentifierUnitTest extends PKPTestCase
{
    protected function setUp(): void
	{
		parent::setUp();
	}

    public function testIdentifierWithYearAndRunningNumber()
    {
        $year = 2025;
        $number = 1;
        $identifier = new CertificateIdentifier($year, $number);
        $this->assertSame("2025-001", $identifier->toStr());
    }

    public function testIdentifierFromStr()
    {
        $identifier_str = '2025-001';
        $identifier = CertificateIdentifier::fromStr($identifier_str);
        $this->assertSame("2025-001", $identifier->toStr());
    }

    public function testIdentifierSetYear()
    {
        $year = 2025;
        $number = 1;
        $identifier = new CertificateIdentifier($year, $number);
        $identifier->setYear(2024);
        $this->assertSame("2024-001", $identifier->toStr());
    }

    public function testIdentifierSetRunningNumber()
    {
        $year = 2025;
        $number = 1;
        $identifier = new CertificateIdentifier($year, $number);
        $identifier->setNumber(2624);
        $this->assertSame("2025-2624", $identifier->toStr());
    }

    public function testIdentifierNewUniqueIdentifierFromIdentifierList()
    {
        $year = (int) date('Y');
        $apiParser = $this->createMock(CodecheckGithubRegisterApiClient::class);
        $apiParser->expects($this->once())
                    ->method('fetchNewestIssues');
        $apiParser->method('getIssues')
              ->willReturn([
                    [
                        'title' => "Example Authors et al. | $year-001/$year-003",
                        'html_url' => "something",
                        'number' => 1
                    ],
              ]);

        $identifierList = CertificateIdentifierList::fromApi($apiParser, true);
        $newUniqueIdentifier = CertificateIdentifier::newUniqueIdentifier($identifierList);

        $this->assertSame("$year-004", $newUniqueIdentifier->toStr());
    }

    public function testIdentifierNewUniqueIdentifierFromIdentifierListBrandNewYear()
    {
        $apiParser = $this->createMock(CodecheckGithubRegisterApiClient::class);
        $apiParser->expects($this->once())
                    ->method('fetchNewestIssues');
        $apiParser->method('getIssues')
              ->willReturn([
                    [
                        'title' => 'Example Authors et al. | 2024-001/2024-003',
                        'html_url' => "something",
                        'number' => 1
                    ],
              ]);

        $identifierList = CertificateIdentifierList::fromApi($apiParser, true);
        $newUniqueIdentifier = CertificateIdentifier::newUniqueIdentifier($identifierList);
        $currentYear = (int) date("Y");

        $this->assertSame("$currentYear-001", $newUniqueIdentifier->toStr());
    }
}