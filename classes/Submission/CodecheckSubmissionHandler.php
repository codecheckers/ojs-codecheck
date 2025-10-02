<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

class CodecheckSubmissionHandler
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Add CODECHECK fields to submission templates
     */
    public function addCodecheckFields($templateMgr, $submission = null): string
    {
        $request = \Application::get()->getRequest();
        
        // Get existing data if submission exists
        $codecheckData = [];
        if ($submission) {
            $dao = new CodecheckSubmissionDAO();
            $data = $dao->getBySubmissionId($submission->getId());
            if ($data) {
                $codecheckData = [
                    'optIn' => $data->getOptIn(),
                    'codeRepository' => $data->getCodeRepository(),
                    'dataRepository' => $data->getDataRepository(),
                    'dependencies' => $data->getDependencies(),
                    'executionInstructions' => $data->getExecutionInstructions()
                ];
            }
        }

        // Check if user opted in initially
        $initialOptIn = $request->getUserVar('codecheckOptIn') ?? $codecheckData['optIn'] ?? false;

        $templateMgr->assign([
            'codecheckOptIn' => $initialOptIn,
            'showCodecheckDetails' => $initialOptIn,
            'codeRepository' => $codecheckData['codeRepository'] ?? '',
            'dataRepository' => $codecheckData['dataRepository'] ?? '',
            'dependencies' => $codecheckData['dependencies'] ?? '',
            'executionInstructions' => $codecheckData['executionInstructions'] ?? ''
        ]);

        return $templateMgr->fetch($this->plugin->getTemplateResource('submission/codecheck-fields.tpl'));
    }
}