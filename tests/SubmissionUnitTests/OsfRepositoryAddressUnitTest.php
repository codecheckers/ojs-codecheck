<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\OsfRepositoryAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SubmissionUnitTests/OsfRepositoryAddressUnitTest.php
 *
 * @class OsfRepositoryAddressUnitTest
 *
 * @brief Which OSF addresses are read, and as what (#36)
 */
class OsfRepositoryAddressUnitTest extends PKPTestCase
{
    public static function osfAddressProvider(): array
    {
        return [
            'project' => ['https://osf.io/ymc3t', 'ymc3t', null],
            'project with a trailing slash' => ['https://osf.io/ymc3t/', 'ymc3t', null],
            'project in capitals' => ['https://osf.io/5SVMT/', '5SVMT', null],
            'files tab' => ['https://osf.io/ymc3t/files/osfstorage', 'ymc3t', null],
            'files tab, older form' => ['https://osf.io/ymc3t/files/', 'ymc3t', null],
            'file page' => ['https://osf.io/ymc3t/files/osfstorage/66f86b012218196732a8604f', 'ymc3t', '66f86b012218196732a8604f'],
            'file by its short identifier' => ['https://osf.io/ymc3t/files/5zu8b', 'ymc3t', '5zu8b'],
            'www' => ['https://www.osf.io/ymc3t/', 'ymc3t', null],
        ];
    }

    #[DataProvider('osfAddressProvider')]
    public function testTheAddressIsRead(string $address, string $node, ?string $file)
    {
        $this->assertSame(compact('node', 'file'), OsfRepositoryAddress::parse($address));
    }

    public static function notAnOsfProjectProvider(): array
    {
        return [
            'a download link' => ['https://osf.io/download/5zu8b/'],
            'the wiki' => ['https://osf.io/ymc3t/wiki/home/'],
            'a longer identifier' => ['https://osf.io/ymc3tx/'],
            'another host' => ['https://zenodo.org/records/14900193'],
            'plain http' => ['http://osf.io/ymc3t/'],
        ];
    }

    #[DataProvider('notAnOsfProjectProvider')]
    public function testWhatIsNotAnOsfProjectIsNotRead(string $address)
    {
        $this->assertNull(OsfRepositoryAddress::parse($address));
    }
}
