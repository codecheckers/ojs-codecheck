<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use \APP\core\Request;
use APP\core\Application;
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
        $this->validationChecks = [
            fn() => $this->validateCodecheckStatus(),
            fn() => $this->validateYamlStructure(),
            fn() => $this->validateRepository(),
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
            $this->errors[] = __('plugins.generic.codecheck.status.validation.failed.noStatusSet');
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

    private function validateRepository(): bool {
        $codecheckMetadata = $this->codecheckMetadataHandler->getMetadata($this->request, $this->codecheckMetadataHandler->getSubmissionId());
        
        CodecheckLogger::debug(print_r($codecheckMetadata, true));
        if(isset($codecheckMetadata['error']) || !is_array($codecheckMetadata['codecheck']) || !isset($codecheckMetadata['codecheck']['repository'])) {
            $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                'repositoryError' => __('plugins.generic.codecheck.publication.validation.metadataDBLoadError')
            ]);
            return false;
        }
        
        $repositories = explode(",", preg_replace('/\s+/', '', $codecheckMetadata['codecheck']['repository']));
        foreach ($repositories as $repository) {
            $response = $this->codecheckMetadataHandler->importMetadataFromRepository($repository);
            if($response->isSuccess()) { return true; }

            CodecheckLogger::debug("Repository Error: " . $response->getPayloadArray()['error']);
            $this->errors[] = __('plugins.generic.codecheck.publication.validation.invalidRepository', [
                'repositoryError' => $response->getPayloadArray()['error']
            ]);
        }

        return false;
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
}