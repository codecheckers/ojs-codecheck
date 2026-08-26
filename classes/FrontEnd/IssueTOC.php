<?php
namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\template\TemplateManager;
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
        $badge = new Badge($this->plugin, $context->getId());

        $badgeTemplateManager = TemplateManager::getManager($request);
        $badgeTemplateManager->assign([
            'certificateLink' => $codecheckData->getCertificateLink(),
            'badgeUrl'        => $badge->getUrl(),
            'badgeText'       => $badge->getText(),
            'badgeTextColor'  => $badge->getTextColor(),
            'badgeStyle'      => $badge->getStyle(),
        ]);

        $badgeHtml = $badgeTemplateManager->fetch($this->plugin->getTemplateResource('frontend/objects/codecheck_badge.tpl'));

        $output .= $badgeHtml;

        return false;
    }
}
