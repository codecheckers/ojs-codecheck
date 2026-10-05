<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\plugins\generic\codecheck\classes\Submission\GithubRepositoryAddress;
use APP\plugins\generic\codecheck\classes\Submission\OsfRepositoryAddress;

/**
 * How `register.csv` names the repository holding a check's `codecheck.yml`,
 * and the one answer to whether it can name it at all (#36).
 *
 * The register names a GitHub repository or a folder in it, read on its
 * default branch, and an OSF project, read at its top level; in each it reads
 * the file called `codecheck.yml`. An import takes more than that — a branch,
 * a file of another name, a file anywhere in an OSF project — so an address
 * the register would resolve to a different file is refused rather than
 * written as the nearest thing it can name. Publication validation and the
 * "contains codecheck.yml" checkbox ask this for the message, the deposit for
 * the guarantee.
 */
final class RegisterRepositoryName
{
    /**
     * The register's `Repository` value for an address, e.g.
     *   https://github.com/codecheckers/certificate-2025-029           -> github::codecheckers/certificate-2025-029
     *   https://github.com/org/repo/blob/main/reports/08/codecheck.yml -> github::org/repo|reports/08 (main the default branch)
     *   https://zenodo.org/records/12345678                            -> zenodo::12345678
     *   https://osf.io/abcde/                                          -> osf::abcde
     *   https://gitlab.com/cdchck/community-codechecks/some-check      -> gitlab::cdchck/community-codechecks/some-check
     *
     * @param callable(string $owner, string $repo): ?string $defaultBranchOf
     *        GitHub's default branch for a repository, or null when GitHub
     *        could not say; asked only for an address that names a branch, and
     *        may refuse in its own words by throwing
     * @param bool $acceptUnconfirmedBranch Whether a branch GitHub could not
     *        compare with the default is accepted rather than refused: for a
     *        check that must not stop on GitHub's availability, behind one
     *        that refuses
     *
     * @throws \UnexpectedValueException With the reason, for the editor, when the register cannot name the address
     */
    public static function for(string $repository, callable $defaultBranchOf, bool $acceptUnconfirmedBranch = false): string
    {
        $github = GithubRepositoryAddress::parse($repository);
        if ($github !== null) {
            if ($github['file'] !== null && $github['file'] !== 'codecheck.yml') {
                throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.githubFileName', ['file' => htmlspecialchars($github['file'])]));
            }
            // `HEAD` is GitHub's own name for the default branch.
            if ($github['ref'] !== null && $github['ref'] !== 'HEAD') {
                $branch = preg_replace('#^refs/heads/#', '', $github['ref']);
                $defaultBranch = $defaultBranchOf($github['owner'], $github['repo']);
                if ($defaultBranch === null && $acceptUnconfirmedBranch) {
                    $defaultBranch = $branch;
                }
                if ($defaultBranch === null) {
                    throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.githubDefaultBranchUnknown', ['ref' => htmlspecialchars($github['ref'])]));
                }
                if ($branch !== $defaultBranch) {
                    throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.githubBranch', ['ref' => htmlspecialchars($github['ref']), 'defaultBranch' => htmlspecialchars($defaultBranch)]));
                }
            }

            // A folder within a shared repository follows a pipe, e.g.
            // github::reproducible-agile/reviews-2025|reports/08
            return "github::{$github['owner']}/{$github['repo']}" . ($github['path'] !== '' ? '|' . $github['path'] : '');
        }

        $osf = OsfRepositoryAddress::parse($repository);
        if ($osf !== null) {
            if ($osf['file'] !== null) {
                throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.osfFile', ['project' => "https://osf.io/{$osf['node']}/"]));
            }
            return "osf::{$osf['node']}";
        }

        if (preg_match('#^https://zenodo\.org/records/(\d+)/?$#', $repository, $matches)) {
            return "zenodo::{$matches[1]}";
        }

        // The register names a GitLab project by its whole path.
        if (preg_match('#^https://gitlab\.com/(cdchck/community-codechecks/[^/]+)/?$#', $repository, $matches)) {
            return "gitlab::{$matches[1]}";
        }

        throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.unsupported'));
    }
}
