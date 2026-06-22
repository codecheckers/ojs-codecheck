<?php
namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class IssueTOC
{
    private CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function addCodecheckBadge(string $hookName, array $params): bool
    {
        $templateMgr = $params[1];
        $output = &$params[2];

        $article = $templateMgr->getTemplateVars('article');

        if (!$article || !$article->getData('codecheckOptIn')) {
            return false;
        }

        $dao = new CodecheckSubmissionDAO();
        $codecheckData = $dao->getBySubmissionId($article->getId());

        if (!$codecheckData || !$codecheckData->hasCompletedCheck()) {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $badgeType = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks';
        $badgeStyle = $badgeType === 'codecheck_logo' ? 'height:36px; width:auto;' : 'height:18px; width:auto;';

        $badgeTemplateManager = TemplateManager::getManager($request);
        $badgeTemplateManager->assign([
            'certificateLink' => $codecheckData->getCertificateLink(),
            'badgeUrl'        => $this->getBadgeUrl(),
            'badgeStyle'      => $badgeStyle,
        ]);

        $badgeHtml = $badgeTemplateManager->fetch($this->plugin->getTemplateResource('frontend/objects/codecheck_badge.tpl'));

        $output .= $badgeHtml;

        return false;
    }

    /**
     * Get the badge image URL based on the journal's badge type setting.
     *
     * @return string|null The URL to the badge image, or null if badge type is 'none' (text only).
     */
    private function getBadgeUrl(): ?string
    {
        $context = Application::get()->getRequest()->getContext();
        $badgeType = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks';
        $base = Application::get()->getRequest()->getBaseUrl() . '/' . $this->plugin->getPluginPath();

        return match ($badgeType) {
            'codecheck_logo' => $base . '/assets/img/codecheck_logo.svg',
            'custom'         => $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_CUSTOM_URL) ?: null,
            'none'           => null,
            default          => $base . '/assets/img/codeworks-badge.png',
        };
    }
}
