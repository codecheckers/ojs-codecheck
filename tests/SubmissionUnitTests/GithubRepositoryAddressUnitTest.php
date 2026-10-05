<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\GithubRepositoryAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SubmissionUnitTests/GithubRepositoryAddressUnitTest.php
 *
 * @class GithubRepositoryAddressUnitTest
 *
 * @brief Which GitHub addresses are read, and as what (#36)
 */
class GithubRepositoryAddressUnitTest extends PKPTestCase
{
    public static function githubAddressProvider(): array
    {
        $parts = fn (string $owner, string $repo, ?string $ref = null, string $path = '', ?string $file = null) => compact('owner', 'repo', 'ref', 'path', 'file');

        return [
            'root' => ['https://github.com/codecheckers/certificate-2025-029', $parts('codecheckers', 'certificate-2025-029')],
            'root with a trailing slash' => ['https://github.com/codecheckers/certificate-2025-029/', $parts('codecheckers', 'certificate-2025-029')],
            'clone address' => ['https://github.com/codecheckers/Piccolo-2020.git', $parts('codecheckers', 'Piccolo-2020')],
            'www' => ['https://www.github.com/codecheckers/Piccolo-2020', $parts('codecheckers', 'Piccolo-2020')],
            'fragment' => ['https://github.com/codecheckers/Piccolo-2020#readme', $parts('codecheckers', 'Piccolo-2020')],
            'branch' => ['https://github.com/codecheckers/Piccolo-2020/tree/dev', $parts('codecheckers', 'Piccolo-2020', 'dev')],
            'folder' => ['https://github.com/reproducible-agile/reviews-2025/tree/main/reports/08/', $parts('reproducible-agile', 'reviews-2025', 'main', 'reports/08')],
            'folder through blob' => ['https://github.com/reproducible-agile/reviews-2025/blob/main/reports/08', $parts('reproducible-agile', 'reviews-2025', 'main', 'reports/08')],
            'file' => ['https://github.com/org/repo/blob/main/reports/08/codecheck.yml', $parts('org', 'repo', 'main', 'reports/08', 'codecheck.yml')],
            'file at the root' => ['https://github.com/org/repo/blob/main/codecheck.yml', $parts('org', 'repo', 'main', '', 'codecheck.yml')],
            'file with a query' => ['https://github.com/org/repo/blob/main/codecheck.yaml?plain=1', $parts('org', 'repo', 'main', '', 'codecheck.yaml')],
            'raw link' => ['https://github.com/org/repo/raw/main/7/codecheck.yml', $parts('org', 'repo', 'main', '7', 'codecheck.yml')],
            'raw host' => ['https://raw.githubusercontent.com/org/repo/main/7/codecheck.yml', $parts('org', 'repo', 'main', '7', 'codecheck.yml')],
            'raw host, full branch ref' => ['https://raw.githubusercontent.com/org/repo/refs/heads/main/7/codecheck.yml', $parts('org', 'repo', 'refs/heads/main', '7', 'codecheck.yml')],
            'raw host, tag ref' => ['https://raw.githubusercontent.com/org/repo/refs/tags/v1/codecheck.yml', $parts('org', 'repo', 'refs/tags/v1', '', 'codecheck.yml')],
            'file through tree' => ['https://github.com/org/repo/tree/main/7/codecheck.yml', $parts('org', 'repo', 'main', '7', 'codecheck.yml')],
            'an encoded space' => ['https://github.com/org/repo/tree/main/my%20check', $parts('org', 'repo', 'main', 'my check')],
            'a folder named like a ref' => ['https://github.com/org/repo/tree/main/refs', $parts('org', 'repo', 'main', 'refs')],
        ];
    }

    #[DataProvider('githubAddressProvider')]
    public function testTheAddressIsRead(string $address, array $expected)
    {
        $this->assertSame($expected, GithubRepositoryAddress::parse($address));
    }

    public static function notAGithubRepositoryProvider(): array
    {
        return [
            'an issue' => ['https://github.com/codecheckers/register/issues/5'],
            'an owner' => ['https://github.com/codecheckers'],
            'another host' => ['https://gitlab.com/codecheckers/register'],
            'plain http' => ['http://github.com/codecheckers/register'],
            'a line break in a folder' => ['https://github.com/o/r/tree/main/x%0A2099-999'],
            'a quote in a folder' => ['https://github.com/o/r/tree/main/x%22'],
            'a comma in a folder' => ['https://github.com/o/r/tree/main/a%2Cb'],
            'the register separator in a folder' => ['https://github.com/o/r/tree/main/a%7Cb'],
            'the register separator, unencoded' => ['https://github.com/o/r/tree/main/a|b'],
            'a hash in a folder' => ['https://github.com/o/r/tree/main/C%23'],
            'bytes outside UTF-8' => ['https://github.com/o/r/blob/main/%FF.yml'],
        ];
    }

    #[DataProvider('notAGithubRepositoryProvider')]
    public function testWhatIsNotARepositoryIsNotRead(string $address)
    {
        $this->assertNull(GithubRepositoryAddress::parse($address));
    }
}
