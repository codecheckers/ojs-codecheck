<?php

namespace APP\plugins\generic\codecheck\classes\Base;

use APP\core\Application;
use APP\pages\submission\SubmissionHandler;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;

abstract class SubmissionWizard
{
    public CodecheckPlugin $plugin;
    public string $fieldName = '';

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function addToSubmissionWizardSteps(string $hookName, array $params): bool
    {
        $request = Application::get()->getRequest();

        if ($request->getRequestedPage() !== 'submission') return false;
        if ($request->getRequestedOp() === 'saved') return false;

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) return false;

        error_log("CODECHECK DEBUG: Adding wizard section");

        /** @var TemplateManager $templateMgr */
        $templateMgr = $params[0];

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => $this->fieldName,
                    'name' => __('plugins.generic.codecheck.' . $this->fieldName . '.label'),
                    'description' => '',
                    'type' => SubmissionHandler::SECTION_TYPE_TEMPLATE,
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState([
            $this->fieldName => [],
            'steps' => $steps,
        ]);

        return false;
    }

    public function addToSubmissionWizardTemplate(string $hookName, array $params): bool
    {
        $smarty = $params[1];
        $output = &$params[2];

        error_log("CODECHECK DEBUG: Adding wizard template for fieldName: " . $this->fieldName);

        $output .= sprintf(
            '<template v-else-if="section.id === \'%s\'">%s</template>',
            $this->fieldName,
            $smarty->fetch($this->plugin->getTemplateResource($this->fieldName . '/submissionWizard.tpl'))
        );

        return false;
    }

    public function addToSubmissionWizardReviewTemplate(string $hookName, array $params): bool
    {
        error_log("CODECHECK DEBUG: Review hook called!");
        
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        error_log("CODECHECK DEBUG: Step is: " . $step);

        // CHANGED: Show on 'details' step only (where our fields are)
        if ($step === 'details') {
            error_log("CODECHECK DEBUG: Step is 'details', fetching review template");
            
            $templatePath = $this->plugin->getTemplateResource($this->fieldName . '/submissionWizardReview.tpl');
            error_log("CODECHECK DEBUG: Template path: " . $templatePath);
            
            $reviewContent = $templateMgr->fetch($templatePath);
            error_log("CODECHECK DEBUG: Review content length: " . strlen($reviewContent));
            
            $output .= $reviewContent;
            
            error_log("CODECHECK DEBUG: Added review template to output");
        } else {
            error_log("CODECHECK DEBUG: Step is '$step', NOT 'details', skipping");
        }

        return false;
    }
}