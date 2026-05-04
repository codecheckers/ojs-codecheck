<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/unittests/CertificateIdentifierListUnitTests.php
 *
 * @class CertificateIdentifierListUnitTest
 *
 * @brief Tests for the CertificateIdentifierList class
 */
class CertificateIdentifierListUnitTest extends PKPTestCase
{
    protected function setUp(): void
	{
		parent::setUp();
	}


    public function testGetRawIdentifierTitleIsEmpty()
    {
        $title = '';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertNull($rawIdentifier);
    }

    public function testGetRawIdentifierTitleIsOnlyWhitespace()
    {
        $title = '     ';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertNull($rawIdentifier);
    }

    public function testGetRawIdentifierTitleHasNoSplitter()
    {
        $title = 'Daniel Nüst 2025-034';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertNull($rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedFormat()
    {
        $title = 'Daniel Nüst | 2025-012';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertSame('2025-012', $rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedFormatLongRunningNumber()
    {
        $title = 'Daniel Nüst | 2025-123456789';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertSame('2025-123456789', $rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedFormatWithWhitespaces()
    {
        $title = 'Daniel Nüst       |              2025-012                                               ';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertSame('2025-012', $rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedSecondSplitter()
    {
        $title = 'Daniel Nüst | 2025-012 |';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertNull($rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedSecondSplitterBeforeIdentifier()
    {
        $title = 'Author | abc | 2025-999';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertSame('2025-999', $rawIdentifier);
    }

    public function testGetRawIdentifierTitleExpectedMultipleIdentifiers()
    {
        $title = 'Author - 2025-001 | 2026-001 - 2026-002';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertNull($rawIdentifier);
    }

    public function testGetRawIdentifierTitleLongExpected()
    {
        $title = 'Daniel Nüst | 2025-012/2025-017';
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        $this->assertSame('2025-012/2025-017', $rawIdentifier);
    }

    public function testFromApiWithMockApiParserWithEmptyFetchedIssues()
    {
        $apiParserMock = $this->createMock(CodecheckGithubRegisterApiClient::class);
        $apiParserMock->method('fetchNewestIssues');
        $identifierList = CertificateIdentifierList::fromApi($apiParserMock, true);
        $this->assertSame("Certificate Identifiers:\n", $identifierList->toStr());
    }

    public function testFromApiWithMockApiParserWithSomeFetchedIssues()
    {
        $apiParser = $this->createMock(CodecheckGithubRegisterApiClient::class);

        $apiParser->method('fetchNewestIssues');
        $apiParser->method('getIssues')
                ->willReturn([
                    [
                        'title' => 'Daniel Nüst | 2024-012',
                        'html_url' => 'example.com',
                        'number' => 1
                    ],
                    [
                        'title' => 'Example Authors et al. | 2024-012/2024-013',
                        'html_url' => 'something.net',
                        'number' => 2
                    ],
                    [
                        'title' => 'Daniel Nüst | 2024-012 | ',
                        'html_url' => 'https://github.com/codecheckers/issue/3',
                        'number' => 3
                    ]
                ]);

        $identifierList = CertificateIdentifierList::fromApi($apiParser, true);
        $this->assertSame(
            "Certificate Identifiers:\n2024-012\n2024-013\n",
            $identifierList->toStr()
        );
    }

    public function testFilledCertificateIdentifierListCount()
    {
        $identifierList = new CertificateIdentifierList();
        $identifierList->appendToCertificateIdList('2024-012', ['html_url' => "something", 'number' => 1]);
        $identifierListCount = $identifierList->getNumberOfIdentifiers();
        $this->assertSame(1, $identifierListCount);
    }

    public function testFilledCertificateIdentifierListToStr()
    {
        $identifierList = new CertificateIdentifierList();
        $identifierList->appendToCertificateIdList('2022-012', ['html_url' => "something", 'number' => 1]);
        $this->assertSame(
            "Certificate Identifiers:\n2022-012\n",
            $identifierList->toStr()
        );
    }

    public function testFilledCertificateIdentifierListGetNewestIdentifier()
    {
        $identifierList = new CertificateIdentifierList();
        $identifierList->appendToCertificateIdList('2022-012/2022-014', ['html_url' => "something", 'number' => 1]);
        $actualIdentifier = $identifierList->getNewestIdentifier();
        $expectedIdentifier = new CertificateIdentifier(2022, 14);
        $this->assertSame($expectedIdentifier->toStr(), $actualIdentifier->toStr());
    }

    public function testFilledCertificateIdentifierListSortDesc()
    {
        $identifierList = new CertificateIdentifierList();
        $identifierList->appendToCertificateIdList('2022-012/2022-014', ['html_url' => "something", 'number' => 1]);
        $identifierList->appendToCertificateIdList('2025-014', ['html_url' => "another.url", 'number' => 2]);
        $identifierList->sortDesc();
        $this->assertSame(
            "Certificate Identifiers:\n2025-014\n2022-014\n2022-013\n2022-012\n",
            $identifierList->toStr()
        );
    }

    public function testFilledCertificateIdentifierListSortAsc()
    {
        $identifierList = new CertificateIdentifierList();
        $identifierList->appendToCertificateIdList('2022-012/2022-014', ['html_url' => "something", 'number' => 1]);
        $identifierList->appendToCertificateIdList('2025-014', ['html_url' => "another.url", 'number' => 2]);
        $identifierList->sortAsc();
        $this->assertSame(
            "Certificate Identifiers:\n2022-012\n2022-013\n2022-014\n2025-014\n",
            $identifierList->toStr()
        );
    }
}