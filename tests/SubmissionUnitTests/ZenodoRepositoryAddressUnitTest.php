<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\ZenodoRepositoryAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SubmissionUnitTests/ZenodoRepositoryAddressUnitTest.php
 *
 * @class ZenodoRepositoryAddressUnitTest
 *
 * @brief Which Zenodo addresses are read, and as what (#36)
 */
class ZenodoRepositoryAddressUnitTest extends PKPTestCase
{
    public static function zenodoAddressProvider(): array
    {
        return [
            'record' => ['https://zenodo.org/records/14900193', '14900193', null, false],
            'record with a trailing slash' => ['https://zenodo.org/records/14900193/', '14900193', null, false],
            'older record, shorter id' => ['https://zenodo.org/records/3674056', '3674056', null, false],
            'older address form' => ['https://zenodo.org/record/3674056', '3674056', null, false],
            'record with a preview query' => ['https://zenodo.org/records/14900193?preview=1', '14900193', null, false],
            'file download' => ['https://zenodo.org/records/14900193/files/codecheck.yml?download=1', '14900193', 'codecheck.yml', false],
            'file content' => ['https://zenodo.org/records/14900193/files/codecheck.yml/content', '14900193', 'codecheck.yml', false],
            'file with an encoded name' => ['https://zenodo.org/records/14900193/files/my%20check.yml', '14900193', 'my check.yml', false],
            'sandbox' => ['https://sandbox.zenodo.org/records/123', '123', null, true],
        ];
    }

    #[DataProvider('zenodoAddressProvider')]
    public function testTheAddressIsRead(string $address, string $record, ?string $file, bool $sandbox)
    {
        $this->assertSame(compact('record', 'file', 'sandbox'), ZenodoRepositoryAddress::parse($address));
    }

    public static function notAZenodoRecordProvider(): array
    {
        return [
            'a community' => ['https://zenodo.org/communities/codecheck'],
            'a search' => ['https://zenodo.org/search?q=codecheck'],
            'plain http' => ['http://zenodo.org/records/14900193'],
        ];
    }

    #[DataProvider('notAZenodoRecordProvider')]
    public function testWhatIsNotAZenodoRecordIsNotRead(string $address)
    {
        $this->assertNull(ZenodoRepositoryAddress::parse($address));
    }

    public function testWithoutAFileTheDownloadIsTheCodecheckYml()
    {
        $this->assertSame(
            'https://zenodo.org/records/123/files/codecheck.yml?download=1',
            ZenodoRepositoryAddress::downloadUrl(['record' => '123', 'file' => null, 'sandbox' => false])
        );
    }

    public function testTheDownloadIsServedFromTheRecordsHost()
    {
        $this->assertSame(
            'https://sandbox.zenodo.org/records/123/files/my%20check.yml?download=1',
            ZenodoRepositoryAddress::downloadUrl(['record' => '123', 'file' => 'my check.yml', 'sandbox' => true])
        );
    }
}
