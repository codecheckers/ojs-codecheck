<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\submission\Submission;
use PKP\context\Context;

/**
 * Assembles the register.csv row for a published CODECHECK and deposits it
 * to the CODECHECK Register (see ojs-codecheck#10).
 *
 * This class is intentionally read-mostly with respect to existing plugin
 * state: it reuses `CodecheckMetadataHandler` for all data access instead
 * of querying `codecheck_metadata` directly, and reuses
 * `RegisterRepositoryName` for the register's name of the repository
 * rather than re-implementing it.
 */
class CodecheckRegisterDepositService
{
    private CodecheckPlugin $plugin;
    private CodecheckMetadataHandler $codecheckMetadataHandler;
    private array $errors = [];

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
        // The handler wants a request to be built; nothing here asks it for
        // the journal, which is the article's (#188).
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler(Application::get()->getRequest(), GithubHttp::client());
    }

    /**
     * Entry point: build the register row for a submission and open a PR
     * against the configured register repository.
     *
     * The journal is the article's, never the request's: the scheduled task
     * publishes on the command line, where there is none (#188).
     *
     * @return array{success: bool, prUrl?: string, row?: array, error?: string}
     */
    public function depositForSubmission(Submission $submission): array
    {
        $this->errors = [];
        $submissionId = (int) $submission->getId();

        $context = Application::getContextDAO()->getById((int) $submission->getData('contextId'));
        if (!$context) {
            return $this->fail("Submission #{$submissionId} belongs to no journal.");
        }

        $metadataResult = $this->codecheckMetadataHandler->getMetadata(null, $submissionId, true);

        if (isset($metadataResult['error']) || empty($metadataResult['codecheck'])) {
            return $this->fail('No CODECHECK metadata found for submission #' . $submissionId . '.');
        }

        $codecheckMetadata = $metadataResult['codecheck'];

        // Certificate is required to deposit at all.
        $certificate = $codecheckMetadata['certificate'] ?? null;
        if (empty($certificate)) {
            return $this->fail('No Certificate Identifier has been reserved for submission #' . $submissionId . '.');
        }

        // Which repository the codechecker marked as containing codecheck.yml —
        // asked of the repositories a reader may see, because this URL goes into
        // the `Repository` column of a row committed to a public pull request.
        // Publication validation refuses a private selection outright; it
        // refuses a missing one only when extended validation is on, so the
        // second branch below is an ordinary state, not a bypass (issue #169).
        $repositoryData = $codecheckMetadata['repository'] ?? null;
        $repositoryUrl = CodecheckRepositories::publicSelectedUrl($repositoryData);
        if ($repositoryUrl === null) {
            return $this->fail(
                CodecheckRepositories::selectedIsPrivate($repositoryData)
                    ? 'The repository containing the codecheck.yml file for submission #' . $submissionId
                        . ' is hidden from the public record, so it cannot be named in the public register.'
                    : 'No repository containing the codecheck.yml file was selected for submission #' . $submissionId . '. '
                        . 'The codechecker must check "Contains codecheck.yml file" for one of the listed repositories before publication.'
            );
        }

        // Convert the URL into the register's `Repository` column format
        // (e.g. github::org/repo, zenodo::id, osf::id, gitlab::path) first, so
        // an address the register cannot name is refused before the file is
        // fetched; only a GitHub address naming a branch costs a request.
        try {
            $formattedRepository = $this->codecheckMetadataHandler->registerRepositoryName($repositoryUrl);
        } catch (\Throwable $e) {
            return $this->fail('Could not format repository "' . $repositoryUrl . '" for the register: ' . $e->getMessage());
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

        $row = $this->buildRegisterRow($context, $codecheckMetadata, $certificate, $formattedRepository);

        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        // A journal with no GitHub configuration has nothing to deposit with.
        // The client's parameters are typed `string`, and it is built before
        // the try below, so a null would leave a TypeError to escape this
        // method and the hook — where PKP swallows it as "failed to handle the
        // hook" and abandons every remaining Publication::publish callback,
        // including other plugins'. That is the opposite of the best-effort
        // contract this service is supposed to keep. Reachable since the
        // deposit setting defaults to on (#177).
        if (empty($githubPersonalAccessToken) || empty($githubRegisterOrganization) || empty($githubRegisterRepository)) {
            return $this->fail(
                'The CODECHECK Register deposit is enabled for this journal, but its GitHub access token, '
                . 'register organization or register repository is not configured; skipping submission #' . $submissionId . '.'
            );
        }

        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository,
            (string) $submissionId,
            CodecheckPostOrigin::fromContext($this->plugin, $context),
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
     * Assemble the 5-column register.csv row: Certificate, Repository, Type, Venue, Issue.
     */
    private function buildRegisterRow(Context $context, array $codecheckMetadata, string $certificate, string $formattedRepository): array
    {
        $issueData = $codecheckMetadata['issue'] ?? [];
        $issueNumber = $issueData['number'] ?? null;

        return [
            'Certificate' => $certificate,
            'Repository' => $formattedRepository,
            'Type' => 'journal',
            // The journal's own language: the run's interface language is the
            // editor's on the web and the site's on the command line, and one
            // journal must not be two venues in the register.
            'Venue' => $context->getLocalizedName($context->getPrimaryLocale()) ?? 'Unknown Journal',
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
