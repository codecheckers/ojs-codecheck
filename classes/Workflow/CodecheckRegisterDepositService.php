<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\GithubUrlParseException;
use \Github\Client;

/**
 * Assembles the register.csv row for a published CODECHECK and deposits it
 * to the CODECHECK Register (see ojs-codecheck#10).
 *
 * This class is intentionally read-mostly with respect to existing plugin
 * state: it reuses `CodecheckMetadataHandler` for all data access instead
 * of querying `codecheck_metadata` directly, and reuses
 * `CodecheckGithubRegisterApiClient::parseGithubUrl()` for GitHub URL
 * parsing rather than re-implementing it.
 */
class CodecheckRegisterDepositService
{
    private CodecheckPlugin $plugin;
    private Request $request;
    private CodecheckMetadataHandler $codecheckMetadataHandler;
    private array $errors = [];

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
        $this->request = Application::get()->getRequest();
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($this->request, new Client());
    }

    /**
     * Entry point: build the register row for a submission and open a PR
     * against the configured register repository.
     *
     * @param int $submissionId
     * @return array{success: bool, prUrl?: string, row?: array, error?: string}
     */
    public function depositForSubmission(int $submissionId): array
    {
        $this->errors = [];

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return $this->fail("Submission #$submissionId not found.");
        }

        $metadataResult = $this->codecheckMetadataHandler->getMetadata($this->request, $submissionId);

        if (isset($metadataResult['error']) || empty($metadataResult['codecheck'])) {
            return $this->fail('No CODECHECK metadata found for submission #' . $submissionId . '.');
        }

        $codecheckMetadata = $metadataResult['codecheck'];

        // Certificate is required to deposit at all.
        $certificate = $codecheckMetadata['certificate'] ?? null;
        if (empty($certificate)) {
            return $this->fail('No Certificate Identifier has been reserved for submission #' . $submissionId . '.');
        }

        // Resolve which repository the codechecker marked as containing codecheck.yml.
        $repositoryUrl = $this->resolveSelectedRepositoryUrl($codecheckMetadata);
        if ($repositoryUrl === null) {
            return $this->fail(
                'No repository containing the codecheck.yml file was selected for submission #' . $submissionId . '. ' .
                'The codechecker must check "Contains codecheck.yml file" for one of the listed repositories before publication.'
            );
        }

        // Re-run the same import/fetch check already used elsewhere in the plugin
        // (checkbox-time validation, extended publication validation) so a stale
        // or unreachable URL never reaches the public register, independent of
        // whether the journal has "extended validation" turned on.
        $importResponse = $this->codecheckMetadataHandler->importMetadataFromRepository($repositoryUrl);
        if (!$importResponse->isSuccess()) {
            $payload = $importResponse->getPayloadArray();
            return $this->fail(
                'Could not verify the codecheck.yml at "' . $repositoryUrl . '": ' . ($payload['error'] ?? 'unknown error')
            );
        }

        // Convert the URL into the register's `Repository` column format
        // (e.g. github::org/repo, zenodo::id, osf::id, gitlab::path).
        try {
            $formattedRepository = $this->formatRepositoryForRegister($repositoryUrl);
        } catch (\Throwable $e) {
            return $this->fail('Could not format repository "' . $repositoryUrl . '" for the register: ' . $e->getMessage());
        }

        $row = $this->buildRegisterRow($submission, $codecheckMetadata, $certificate, $formattedRepository);

        $context = $this->request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository,
            (string) $submissionId,
            $context,
        );

        try {
            $pullRequest = $codecheckGithubRegisterApiClient->depositRegisterRow($row, $certificate);
        } catch (\Throwable $e) {
            CodecheckLogger::error('Register deposit failed for submission #' . $submissionId . ': ' . $e->getMessage());
            return $this->fail('Failed to open the register deposit Pull Request: ' . $e->getMessage());
        }

        CodecheckLogger::info('Register deposit PR opened for submission #' . $submissionId . ': ' . $pullRequest['html_url']);

        return [
            'success' => true,
            'prUrl' => $pullRequest['html_url'],
            'row' => $row,
        ];
    }

    /**
     * Resolve `repoWithCodecheckYaml` (an index into the `repositories` list)
     * into the actual URL string.
     */
    private function resolveSelectedRepositoryUrl(array $codecheckMetadata): ?string
    {
        $repositoryData = $codecheckMetadata['repository'] ?? null;

        if (!is_array($repositoryData) || empty($repositoryData['repositories'])) {
            return null;
        }

        $repositories = $repositoryData['repositories'];
        if (!is_array($repositories)) {
            return null;
        }

        $index = $repositoryData['repoWithCodecheckYaml'] ?? null;
        if (!is_int($index) || !isset($repositories[$index])) {
            return null;
        }

        $url = $repositories[$index]['url'] ?? null;

        return is_string($url) && $url !== '' ? trim($url) : null;
    }

    /**
     * Converts a raw repository URL into the register.csv `Repository`
     * column format, e.g.:
     *   https://github.com/codecheckers/certificate-2025-029        -> github::codecheckers/certificate-2025-029
     *   https://github.com/org/repo/blob/main/reports/08/codecheck.yml -> github::org/repo|reports/08
     *   https://zenodo.org/records/12345678                          -> zenodo::12345678
     *   https://osf.io/abcde/                                        -> osf::abcde
     *   https://gitlab.com/cdchck/community-codechecks/some-check    -> gitlab::some-check
     */
    private function formatRepositoryForRegister(string $repository): string
    {
        if (preg_match('#^https://github\.com/#', $repository)) {
            $parts = CodecheckGithubRegisterApiClient::parseGithubUrl($repository);
            $formatted = "github::{$parts['owner']}/{$parts['repo']}";
            if (!empty($parts['path'])) {
                // Register convention for a sub-path within a shared repository,
                // e.g. github::reproducible-agile/reviews-2025|reports/08
                $formatted .= '|' . $parts['path'];
            }
            return $formatted;
        }

        if (preg_match('#^https://zenodo\.org/records/(\d+)/?$#', $repository, $matches)) {
            return "zenodo::{$matches[1]}";
        }

        if (preg_match('#^https://osf\.io/([A-Za-z0-9]{5})/?$#', $repository, $matches)) {
            return "osf::{$matches[1]}";
        }

        if (preg_match('#^https://gitlab\.com/cdchck/community-codechecks/([^/]+)/?$#', $repository, $matches)) {
            return "gitlab::{$matches[1]}";
        }

        throw new GithubUrlParseException("Repository URL \"$repository\" does not match any register-supported format.");
    }

    /**
     * Assemble the 5-column register.csv row: Certificate, Repository, Type, Venue, Issue.
     */
    private function buildRegisterRow($submission, array $codecheckMetadata, string $certificate, string $formattedRepository): array
    {
        $context = $this->request->getContext();
        $issueData = $codecheckMetadata['issue'] ?? [];
        $issueNumber = $issueData['number'] ?? null;

        return [
            'Certificate' => $certificate,
            'Repository' => $formattedRepository,
            'Type' => 'journal',
            'Venue' => $context?->getLocalizedName() ?? 'Unknown Journal',
            'Issue' => $issueNumber !== null ? (string) $issueNumber : 'NA',
        ];
    }

    private function fail(string $message): array
    {
        $this->errors[] = $message;
        CodecheckLogger::error('CODECHECK Register Deposit: ' . $message);

        return [
            'success' => false,
            'error' => $message,
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}