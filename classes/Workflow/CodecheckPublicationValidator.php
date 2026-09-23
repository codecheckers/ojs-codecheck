<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class CodecheckPublicationValidator
{
    private array $validationChecks;
    private Request $request;
    private mixed $context;
    private CodecheckMetadataHandler $codecheckMetadataHandler;
    private array $errors;
    private CodecheckPlugin $plugin;

    /** The submission being published, as the hook hands it over. */
    private mixed $submission;

    /** The stored CODECHECK record, loaded on first use by getCodecheckMetadata(). */
    private ?array $metadata = null;

    /**
     * @param mixed $submission the submission from the `Publication::validatePublish`
     *  hook. Required in practice: publishing happens through the REST API, where
     *  there is no page handler to ask for the authorized submission — the router
     *  returns null there and asking it anything is fatal.
     */
    public function __construct(CodecheckPlugin $plugin, mixed $submission = null)
    {
        $this->submission = $submission;
        $this->errors = [];
        $this->validationChecks = [
            // First, because the loop below stops at the first failing check
            // and `validateCodecheckStatus()` can fail without recording an
            // error — a publish would then go through with this one never
            // having run (issue #169).
            fn () => $this->validateSelectedRepositoryIsPublic(),
            fn () => $this->validateCodecheckStatus(),
            fn () => $this->validateYamlStructure(),
            // If this is not an extended Publication Validation, just return valid (and except that the metadata might be invalid, but ignore it since the user set the configuration setting to fail silently in this case)
            fn () => !$this->isExtendedValidation() || $this->validateMetadataFromRepository(),
        ];

        $this->request = Application::get()->getRequest();
        $this->context = $this->request->getContext();
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($this->request);
        $this->plugin = $plugin;
    }

    /**
     * The submission being validated: the one the hook handed over, or — for a
     * caller that did not pass one — whatever the page handler has authorized.
     *
     * The handler is null under the API router, which is how publishing
     * actually happens, so that fallback is guarded rather than assumed.
     */
    private function getSubmission(): mixed
    {
        if ($this->submission) {
            return $this->submission;
        }

        $handler = $this->request->getRouter()?->getHandler();

        return $handler ? $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION) : null;
    }

    private function isOptedInToCodecheck(): bool
    {
        $submission = $this->getSubmission();

        return $submission && $submission->getData('codecheckOptIn');
    }

    /**
     * The submission the checks below run against.
     *
     * Not the metadata handler's own id: that reads a `submissionId` request
     * variable, which the publish endpoint does not carry — its id is in the
     * URL path.
     */
    private function getSubmissionId(): int
    {
        $submission = $this->getSubmission();

        return $submission ? (int) $submission->getId() : (int) $this->codecheckMetadataHandler->getSubmissionId();
    }

    /**
     * The stored CODECHECK record, read once per publish attempt.
     *
     * `getMetadata()` reloads the submission, its publication, its authors and
     * the `codecheck_metadata` row each time it is asked, and three checks here
     * want a different corner of the same answer. The validator lives for one
     * request and the checks run back to back, so one read serves those three.
     * `validateYamlStructure()` is not among them: it builds its own handler
     * by way of `CodecheckYamlValidator::fromRequest()` and reads again.
     */
    private function getCodecheckMetadata(): array
    {
        return $this->metadata ??= $this->codecheckMetadataHandler->getMetadata($this->request, $this->getSubmissionId());
    }

    private function validateCodecheckStatus(): bool
    {
        $codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->getSubmissionId());
        // Null when the settings form has never been saved. `in_array(…, null)`
        // is a TypeError, and PKP swallows what a hook throws — so every check
        // in this class would go silently missing on exactly the unconfigured
        // install they are there to protect. Empty rather than permissive, to
        // match `SettingsForm::initData()`, which reads the same absent row as
        // "no status accepted".
        $codecheckStatusKeysSelected = $this->plugin->getSetting($this->context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED) ?? [];

        if (empty($codecheckStatus)) {
            if ($this->isExtendedValidation()) {
                $this->errors[] = __('plugins.generic.codecheck.status.validation.failed.noStatusSet');
            }
            return false;
        }

        if (!in_array($codecheckStatus->status, $codecheckStatusKeysSelected)) {
            $this->errors[] = __('plugins.generic.codecheck.status.validation.failed', [
                'codecheckStatus' => __($codecheckStatus->status)
            ]);
            return false;
        }

        return true;
    }

    private function validateYamlStructure(): bool
    {
        try {
            $yamlValidator = CodecheckYamlValidator::fromRequest($this->request);
            $yamlValidator->validateYaml();
        } catch (\Throwable $e) {
            $this->errors[] = __('plugins.generic.codecheck.yaml.invalid', [
                'errorMessage' => $e->getMessage()
            ]);
            return false;
        }

        return true;
    }

    /**
     * The repository holding the `codecheck.yml` must be one a reader may see:
     * publishing names it in the public register (issue #169).
     *
     * Runs whatever the extended-validation setting says, but only where the
     * disclosure can happen: a journal that does not deposit to the register
     * publishes that address nowhere, so blocking it would deadlock a journal
     * codechecking embargoed material, with a message naming a consequence that
     * cannot occur (#177). The deposit refuses the same case unconditionally,
     * which is what guarantees nothing private is published on the routes that
     * never run publication validation at all.
     *
     * A submission with no repository marked at all is the extended check's
     * subject, not this one.
     */
    private function validateSelectedRepositoryIsPublic(): bool
    {
        if (!$this->plugin->isRegisterDepositEnabled($this->context->getId())) {
            return true;
        }

        $repositoryData = $this->getCodecheckMetadata()['codecheck']['repository'] ?? null;

        if (!CodecheckRepositories::selectedIsPrivate($repositoryData)) {
            return true;
        }

        // The label is itself a locale key, so it is passed in rather than
        // written into the sentence — a translator would otherwise coin a third
        // name for a checkbox that already has two.
        $this->errors[] = __('plugins.generic.codecheck.publication.validation.selectedRepositoryIsPrivate', [
            'hideLabel' => __('plugins.generic.codecheck.repository.markAsHidden'),
        ]);

        return false;
    }

    public function validateMetadataFromRepository(string|null $repository = null): bool
    {
        if (empty($repository)) {
            $codecheckMetadata = $this->getCodecheckMetadata();

            if (isset($codecheckMetadata['error']) || !is_array($codecheckMetadata['codecheck']) || !isset($codecheckMetadata['codecheck']['repository']) || !isset($codecheckMetadata['codecheck']['repository']['repositories'])) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.metadataDBLoadError')
                ]);
                return false;
            }

            $repository = CodecheckRepositories::selectedUrl($codecheckMetadata['codecheck']['repository']);
            if ($repository === null) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.noRepositoryWithCodecheckYmlSelected')
                ]);
                return false;
            }
        }
        $response = $this->codecheckMetadataHandler->importMetadataFromRepository($repository);
        $responseArray = $response->getPayloadArray();
        if ($response->isSuccess()) {
            if (!$this->validatePaperTitle($responseArray['metadata'])) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.invalidPaperTitle')
                ]);
                return false;
            }

            /*
            if(!$this->validateCodechecker($responseArray['metadata'])) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.invalidCodecheckers')
                ]);
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => $responseArray['error']
                ]);
                return false;
            }
            */
            return true;
        }
        $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
            'repositoryError' => $responseArray['error']
        ]);
        return false;
    }

    /*private function validateCodechecker(array $codecheckMetadata): bool {
        $codecheckersFromRepository = $codecheckMetadata['codechecker'];
        $codecheckersFromOjsSubmission = $this->codecheckMetadataHandler->getMetadata($this->request, $this->getSubmissionId());

        foreach ($codecheckersFromRepository as $codecheckerFromRepository) {
            foreach ($codecheckersFromOjsSubmission as $codecheckerFromOjsSubmission) {
                if(!isset($codecheckerFromRepository['orcid']) || !isset($codecheckerFromOjsSubmission['orcid'])) {
                    continue;
                }

                if($codecheckerFromRepository['orcid'] !== $codecheckerFromOjsSubmission['orcid']) {

                }
            }
        }

        $paperTitle = $codecheckMetadata['paper']['title'];
        error_log($paperTitle);
        return true;
    }*/

    private function validatePaperTitle(array $codecheckMetadata): bool
    {
        $metadataFromOjsSubmission = $this->getCodecheckMetadata();
        return $codecheckMetadata['paper']['title'] === $metadataFromOjsSubmission['submission']['title'];
    }

    private function isExtendedValidation(): bool
    {
        // Same hazard as the status list above: unset on a journal that has
        // never saved the settings form, and returning null from a `: bool`
        // function is a TypeError PKP swallows — which would take every check
        // in this class with it.
        $codecheckExtendPublicationValidation = $this->plugin->getSetting($this->context->getId(), Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED) ?? false;
        return (bool) $codecheckExtendPublicationValidation;
    }

    public function validatePublication(): true|array
    {
        // Every check below reads a journal setting, and two of them do it
        // without a null guard — so one missing context would be a TypeError
        // thrown inside the hook, which PKP swallows as "failed to handle the
        // hook" and every check goes silently missing. Answer once, here.
        if ($this->context === null) {
            CodecheckLogger::warning('No context while validating a publication; CODECHECK checks were skipped.');

            return true;
        }

        if ($this->isOptedInToCodecheck()) {
            foreach ($this->validationChecks as $validationCheck) {
                CodecheckLogger::debug('Validation Check!');
                if (!$validationCheck()) {
                    return $this->errors;
                }
            }
        }

        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
