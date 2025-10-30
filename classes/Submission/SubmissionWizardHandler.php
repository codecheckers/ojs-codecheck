<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\core\Application;
use APP\pages\submission\SubmissionHandler;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;

class SubmissionWizardHandler
{
    private CodecheckPlugin $plugin;
    private string $fieldName = 'submission';

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Add CODECHECK section to submission wizard steps
     */
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

        /** @var TemplateManager $templateMgr */
        $templateMgr = $params[0];

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => $this->fieldName,
                    'name' => __('plugins.generic.codecheck.submission.label'),
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

    /**
     * Add CODECHECK template to wizard
     */
    public function addToSubmissionWizardTemplate(string $hookName, array $params): bool
    {
        $smarty = $params[1];
        $output = &$params[2];

        $output .= sprintf(
            '<template v-else-if="section.id === \'%s\'">%s</template>',
            $this->fieldName,
            $smarty->fetch($this->plugin->getTemplateResource($this->fieldName . '/submissionWizard.tpl'))
        );

        return false;
    }

    /**
     * Add CODECHECK review section to wizard
     */
    public function addToSubmissionWizardReviewTemplate(string $hookName, array $params): bool
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step === 'details') {
            $templatePath = $this->plugin->getTemplateResource($this->fieldName . '/submissionWizardReview.tpl');
            $reviewContent = $templateMgr->fetch($templatePath);
            $output .= $reviewContent;
        }

        return false;
    }
}