<?php

namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;

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

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        // `getSettingWithDefault()` takes a null context; a fatal here would be
        // swallowed by PKP as "failed to handle the hook".
        if (!$this->plugin->getSettingWithDefault($context?->getId(), Constants::CODECHECK_SHOW_IN_TOC)) {
            return false;
        }

        $article = $templateMgr->getTemplateVars('article');

        if (!$article || !$article->getData('codecheckOptIn')) {
            return false;
        }

        $dao = new CodecheckSubmissionDAO();
        $codecheckData = $dao->getBySubmissionId($article->getId());

        if (!$codecheckData || !$codecheckData->hasCompletedCheck()) {
            return false;
        }

        $badge = new Badge($this->plugin, $context->getId());

        $badgeTemplateManager = TemplateManager::getManager($request);
        $badgeTemplateManager->assign([
            'certificateLink' => $badge->getCertificateUrl(
                $codecheckData->getCertificate(),
                $codecheckData->getDoiLink()
            ),
            'badgeUrl' => $badge->getUrl(),
            'badgeText' => $badge->getText(),
            'badgeTextColor' => $badge->getTextColor(),
            'badgeStyle' => $badge->getStyle(),
        ]);

        $badgeHtml = $badgeTemplateManager->fetch($this->plugin->getTemplateResource('frontend/objects/codecheck_badge.tpl'));

        $output .= $badgeHtml;

        return false;
    }
}
