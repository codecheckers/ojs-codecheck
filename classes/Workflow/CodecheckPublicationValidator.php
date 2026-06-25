<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use \APP\core\Request;
use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;

class CodecheckPublicationValidator {
    private array $validationChecks;
    private Request $request;
    private mixed $context;
    private CodecheckMetadataHandler $codecheckMetadataHandler;
    private bool $validPublication;
    private array $errors;

    public function __construct()
    {
        $this->validationChecks = [
            fn() => $this->validateCodecheckStatus(),
            //fn() => $this->validateYamlStructure()
        ];

        $this->request = Application::get()->getRequest();
        $this->context = $this->request->getContext();
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($this->request, new Client(), new CurlApiClient());
    }

    private function validateCodecheckStatus(): bool {
        $codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->codecheckMetadataHandler->getSubmissionId());
        $codecheckStatusKeysSelected = $this->getSetting($this->context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED);

        if (empty($codecheckStatus)) {
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

    /*private function validateYamlStructure() {
        $yamlValidator = new CodecheckYamlValidator();
    }*/

    public function validatePublication(): true|array {
        foreach ($this->validationChecks as $validationCheck) {
            if (!$validationCheck()) {
                return $this->errors;
            }
        }

        return true;
    }
}