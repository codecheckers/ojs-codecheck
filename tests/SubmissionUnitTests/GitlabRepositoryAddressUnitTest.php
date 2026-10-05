<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\GitlabRepositoryAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SubmissionUnitTests/GitlabRepositoryAddressUnitTest.php
 *
 * @class GitlabRepositoryAddressUnitTest
 *
 * @brief Which GitLab addresses are read, and as what (#36)
 */
class GitlabRepositoryAddressUnitTest extends PKPTestCase
{
    public static function gitlabAddressProvider(): array
    {
        $parts = fn (string $project, ?string $ref = null, string $path = '', ?string $file = null) => compact('project', 'ref', 'path', 'file');

        return [
            'project' => ['https://gitlab.com/codecheckers/Piccolo-2020', $parts('codecheckers/Piccolo-2020')],
            'project in a subgroup' => ['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT', $parts('cdchck/community-codechecks/2022-svaRetro-svaNUMT')],
            'trailing slash' => ['https://gitlab.com/codecheckers/Piccolo-2020/', $parts('codecheckers/Piccolo-2020')],
            'clone address' => ['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT.git', $parts('cdchck/community-codechecks/2022-svaRetro-svaNUMT')],
            'raw file, as GitLab offers it' => ['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT/-/raw/main/codecheck.yml?ref_type=heads&inline=false', $parts('cdchck/community-codechecks/2022-svaRetro-svaNUMT', 'main', '', 'codecheck.yml')],
            'file' => ['https://gitlab.com/codecheckers/Piccolo-2020/-/blob/main/reports/check.yaml', $parts('codecheckers/Piccolo-2020', 'main', 'reports', 'check.yaml')],
            'folder on a branch' => ['https://gitlab.com/codecheckers/Piccolo-2020/-/tree/dev/reports/08', $parts('codecheckers/Piccolo-2020', 'dev', 'reports/08')],
            'branch' => ['https://gitlab.com/codecheckers/Piccolo-2020/-/tree/dev', $parts('codecheckers/Piccolo-2020', 'dev')],
        ];
    }

    #[DataProvider('gitlabAddressProvider')]
    public function testTheAddressIsRead(string $address, array $expected)
    {
        $this->assertSame($expected, GitlabRepositoryAddress::parse($address));
    }

    public static function notAGitlabProjectProvider(): array
    {
        return [
            'a group' => ['https://gitlab.com/codecheckers'],
            'a user page' => ['https://gitlab.com/users/someone/projects'],
            'an issue' => ['https://gitlab.com/codecheckers/Piccolo-2020/-/issues/1'],
            'another host' => ['https://github.com/codecheckers/Piccolo-2020'],
            'plain http' => ['http://gitlab.com/codecheckers/Piccolo-2020'],
            'an older file address, without -/' => ['https://gitlab.com/g/p/blob/main/codecheck.yml'],
            'an older issue address, without -/' => ['https://gitlab.com/g/p/issues/1'],
            'a comma in the project' => ['https://gitlab.com/codecheckers/a,b'],
        ];
    }

    #[DataProvider('notAGitlabProjectProvider')]
    public function testWhatIsNotAGitlabProjectIsNotRead(string $address)
    {
        $this->assertNull(GitlabRepositoryAddress::parse($address));
    }

    public static function downloadProvider(): array
    {
        return [
            'project, read on the register branch' => [
                ['project' => 'cdchck/community-codechecks/x', 'ref' => null, 'path' => '', 'file' => null],
                'https://gitlab.com/cdchck/community-codechecks/x/-/raw/main/codecheck.yml?inline=false',
            ],
            'folder on a branch' => [
                ['project' => 'a/b', 'ref' => 'dev', 'path' => 'reports/my check', 'file' => null],
                'https://gitlab.com/a/b/-/raw/dev/reports/my%20check/codecheck.yml?inline=false',
            ],
            'file' => [
                ['project' => 'a/b', 'ref' => 'main', 'path' => '', 'file' => 'check.yaml'],
                'https://gitlab.com/a/b/-/raw/main/check.yaml?inline=false',
            ],
        ];
    }

    #[DataProvider('downloadProvider')]
    public function testTheDownloadAddress(array $address, string $expected)
    {
        $this->assertSame($expected, GitlabRepositoryAddress::downloadUrl($address));
    }
}
