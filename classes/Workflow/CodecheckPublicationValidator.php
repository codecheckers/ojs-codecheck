<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use \APP\core\Request;
use APP\core\Application;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;

class CodecheckPublicationValidator {
    private array $validationChecks;
    private Request $request;
    private mixed $context;
    private CodecheckMetadataHandler $codecheckMetadataHandler;
    private bool $validPublication;
    private array $errors;
    private CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->errors = [];
        $this->validationChecks = [
            fn() => $this->validateCodecheckStatus(),
            fn() => $this->validateYamlStructure(),
            // If this is not an extended Publication Validation, just return valid (and except that the metadata might be invalid, but ignore it since the user set the configuration setting to fail silently in this case)
            fn() => !$this->isExtendedValidation() || $this->validateMetadataFromRepository(),
        ];

        $this->request = Application::get()->getRequest();
        $this->context = $this->request->getContext();
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($this->request);
        $this->plugin = $plugin;
    }

    private function isOptedInToCodecheck(): bool {
        $submission = $this->request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        return $submission && $submission->getData('codecheckOptIn');
    }

    private function validateCodecheckStatus(): bool {
        $codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->codecheckMetadataHandler->getSubmissionId());
        $codecheckStatusKeysSelected = $this->plugin->getSetting($this->context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED);

        if(empty($codecheckStatus)) {
            if($this->isExtendedValidation()) {
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

    private function validateYamlStructure(): bool {
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

    public function validateMetadataFromRepository(string|null $repository = null): bool {
        if(empty($repository)) {
            $codecheckMetadata = $this->codecheckMetadataHandler->getMetadata($this->request, $this->codecheckMetadataHandler->getSubmissionId());
        
            if(isset($codecheckMetadata['error']) || !is_array($codecheckMetadata['codecheck']) || !isset($codecheckMetadata['codecheck']['repository']) || !isset($codecheckMetadata['codecheck']['repository']['repositories'])) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.metadataDBLoadError')
                ]);
                return false;
            }

            $repositoryData = $codecheckMetadata['codecheck']['repository'];
            $repositories = is_array($repositoryData['repositories']) ? $repositoryData['repositories'] : [];
            $repositoryWithCodecheckYml = $repositoryData['repoWithCodecheckYaml'];
            if(!is_int($repositoryWithCodecheckYml)) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.noRepositoryWithCodecheckYmlSelected')
                ]);
                return false;
            }
            if (!array_key_exists($repositoryWithCodecheckYml, $repositories)) {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.repositoryWithCodecheckYmlSelectedDoesntExist')
                ]);
                return false;
            }
            $repository = $repositories[$repositoryWithCodecheckYml]['url'] ?? null;
            if (!is_string($repository) || $repository === '') {
                $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                    'repositoryError' => __('plugins.generic.codecheck.publication.validation.repositoryWithCodecheckYmlSelectedDoesntExist')
                ]);
                return false;
            }
        }
        $response = $this->codecheckMetadataHandler->importMetadataFromRepository($repository);
        $responseArray = $response->getPayloadArray();
        if($response->isSuccess()) { 
            if(!$this->validatePaperTitle($responseArray['metadata'])) {
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
        $codecheckersFromOjsSubmission = $this->codecheckMetadataHandler->getMetadata($this->request, $this->codecheckMetadataHandler->getSubmissionId());

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

    private function validatePaperTitle(array $codecheckMetadata): bool {
        $metadataFromOjsSubmission = $this->codecheckMetadataHandler->getMetadata($this->request, $this->codecheckMetadataHandler->getSubmissionId());
        return $codecheckMetadata['paper']['title'] === $metadataFromOjsSubmission['submission']['title'];
    }

    private function isExtendedValidation(): bool {
        $codecheckExtendPublicationValidation = $this->plugin->getSetting($this->context->getId(), Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED);
        return $codecheckExtendPublicationValidation;
    }

    public function validatePublication(): true|array {
        if($this->isOptedInToCodecheck()) {
            foreach ($this->validationChecks as $validationCheck) {
                CodecheckLogger::debug("Validation Check!");
                if (!$validationCheck()) {
                    return $this->errors;
                }
            }
        }

        return true;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}