<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\plugins\generic\codecheck\classes\Exceptions\UnsupportedRepositoryAddressException;
use APP\plugins\generic\codecheck\classes\Workflow\RegisterRepositoryName;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/WorkflowUnitTests/RegisterRepositoryNameUnitTest.php
 *
 * @class RegisterRepositoryNameUnitTest
 *
 * @brief Which addresses register.csv can name, and as what (#36)
 */
class RegisterRepositoryNameUnitTest extends PKPTestCase
{
    public static function nameableProvider(): array
    {
        return [
            'GitHub repository' => ['https://github.com/codecheckers/certificate-2025-029', 'github::codecheckers/certificate-2025-029'],
            'GitHub folder on the default branch' => ['https://github.com/reproducible-agile/reviews-2025/tree/trunk/reports/08', 'github::reproducible-agile/reviews-2025|reports/08'],
            'GitHub codecheck.yml on the default branch' => ['https://github.com/org/repo/blob/trunk/reports/08/codecheck.yml', 'github::org/repo|reports/08'],
            'GitHub raw link, full ref' => ['https://raw.githubusercontent.com/org/repo/refs/heads/trunk/codecheck.yml', 'github::org/repo'],
            'GitHub HEAD' => ['https://raw.githubusercontent.com/org/repo/HEAD/codecheck.yml', 'github::org/repo'],
            'OSF project' => ['https://osf.io/ymc3t/', 'osf::ymc3t'],
            'OSF files tab' => ['https://osf.io/ymc3t/files/osfstorage', 'osf::ymc3t'],
            'Zenodo record' => ['https://zenodo.org/records/14900193', 'zenodo::14900193'],
            'Zenodo record, shorter id' => ['https://zenodo.org/record/3674056', 'zenodo::3674056'],
            'Zenodo codecheck.yml' => ['https://zenodo.org/records/14900193/files/codecheck.yml?download=1', 'zenodo::14900193'],
            'Zenodo sandbox record' => ['https://sandbox.zenodo.org/records/123', 'zenodo-sandbox::123'],
            'GitLab project' => ['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT', 'gitlab::cdchck/community-codechecks/2022-svaRetro-svaNUMT'],
        ];
    }

    #[DataProvider('nameableProvider')]
    public function testTheRegisterNamesTheAddress(string $address, string $expected)
    {
        $this->assertSame($expected, RegisterRepositoryName::for($address, fn () => 'trunk'));
    }

    public static function notNameableProvider(): array
    {
        return [
            'GitHub file of another name' => ['https://github.com/org/repo/blob/trunk/codecheck.yaml', 'githubFileName'],
            'GitHub folder on another branch' => ['https://github.com/org/repo/tree/check-2025/reports/08', 'githubBranch'],
            'GitHub tag' => ['https://raw.githubusercontent.com/org/repo/refs/tags/trunk/codecheck.yml', 'githubBranch'],
            'OSF file page' => ['https://osf.io/ymc3t/files/osfstorage/66f86b012218196732a8604f', 'osfFile'],
            'OSF file by its short identifier' => ['https://osf.io/ymc3t/files/5zu8b', 'osfFile'],
            'Zenodo file of another name' => ['https://zenodo.org/records/14900193/files/paper.yaml', 'zenodoFileName'],
        ];
    }

    #[DataProvider('notNameableProvider')]
    public function testTheRegisterRefusesWhatItWouldReadElsewhere(string $address, string $reason)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("plugins.generic.codecheck.register.repository.{$reason}");

        RegisterRepositoryName::for($address, fn () => 'trunk');
    }

    public function testAnAddressOfNoKindTheRegisterNamesIsToldApart()
    {
        // Publication validation leaves these to the deposit.
        $this->expectException(UnsupportedRepositoryAddressException::class);

        RegisterRepositoryName::for('https://example.org/check', fn () => 'trunk');
    }

    public function testABranchGithubCannotConfirmIsRefused()
    {
        $this->expectExceptionMessage('plugins.generic.codecheck.register.repository.githubDefaultBranchUnknown');

        RegisterRepositoryName::for('https://github.com/org/repo/tree/trunk', fn () => null);
    }

    public function testABranchGithubCannotConfirmIsAcceptedWhenAskedTo()
    {
        $this->assertSame(
            'github::org/repo|reports/08',
            RegisterRepositoryName::for('https://github.com/org/repo/tree/check-2025/reports/08', fn () => null, acceptUnconfirmedBranch: true)
        );
    }

    public function testABranchGithubConfirmsIsRefusedEvenWhenUnconfirmedOnesAreAccepted()
    {
        $this->expectExceptionMessage('plugins.generic.codecheck.register.repository.githubBranch');

        RegisterRepositoryName::for('https://github.com/org/repo/tree/check-2025', fn () => 'trunk', acceptUnconfirmedBranch: true);
    }

    public function testARefusalFromGithubIsPassedOn()
    {
        $this->expectExceptionMessage('GitHub did not answer');

        RegisterRepositoryName::for('https://github.com/org/repo/tree/trunk', fn () => throw new \UnexpectedValueException('GitHub did not answer'));
    }

    public function testARefusalFromGithubIsAcceptedWhenUnconfirmedBranchesAre()
    {
        $this->assertSame(
            'github::org/repo',
            RegisterRepositoryName::for('https://github.com/org/repo/tree/trunk', fn () => throw new \UnexpectedValueException('GitHub did not answer'), acceptUnconfirmedBranch: true)
        );
    }

    public function testGithubIsOnlyAskedWhenTheAddressNamesABranch()
    {
        $asked = 0;
        $defaultBranchOf = function () use (&$asked) {
            $asked++;
            return 'trunk';
        };

        RegisterRepositoryName::for('https://github.com/org/repo', $defaultBranchOf);
        RegisterRepositoryName::for('https://github.com/org/repo/tree/HEAD/reports', $defaultBranchOf);
        RegisterRepositoryName::for('https://osf.io/ymc3t/', $defaultBranchOf);
        $this->assertSame(0, $asked);

        RegisterRepositoryName::for('https://github.com/org/repo/tree/trunk', $defaultBranchOf);
        $this->assertSame(1, $asked);
    }
}
