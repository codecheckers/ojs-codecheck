<?php
/**
 * @file classes/FrontEnd/ArticleDetails.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleDetails
 * @brief Front end article details page class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class ArticleDetails
{
    /** @var CodecheckPlugin */
    public CodecheckPlugin $plugin;

    /** @param CodecheckPlugin $plugin */
    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Add CODECHECK information to the article sidebar.
     */
    public function addCodecheckInfo(string $hookName, array $params): bool
    {
        $templateMgr = $params[1];
        $output = &$params[2];

        // Get the CODECHECK settings for this journal or press
        $context = Application::get()->getRequest()->getContext();
        $codecheckEnabled = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_ENABLED);

        // Do not modify the output if CODECHECK is not enabled
        if (!$codecheckEnabled) {
            return false;
        }

        // Get the current article/submission
        $article = $templateMgr->getTemplateVars('article');
        if (!$article) {
            return false;
        }

        // Only show CODECHECK info if user opted in
        if (!$article->getData('codecheckOptIn')) {
            return false;
        }

        // Get CODECHECK data for this submission
        $dao = new CodecheckSubmissionDAO();
        $codecheckData = $dao->getBySubmissionId($article->getId());

        if (!$codecheckData) {
            return false;
        }

        // Generate and add the CODECHECK display
        $codecheckHtml = $this->generateSidebarDisplay($codecheckData, $templateMgr, $article);

        if ($codecheckHtml) {
            $output .= $codecheckHtml;
        }

        return false;
    }

    /**
     * Generate sidebar display for CODECHECK certificate
     */
    private function generateSidebarDisplay(CodecheckSubmission $codecheckData, $templateMgr, $article): string
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $badgeType = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks';
        $badgeHeight = (int) ($this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_HEIGHT) ?? 24);
        $badgeStyle = 'height:' . $badgeHeight . 'px; width:auto;';

        $templateMgr->assign([
            'logoUrl'      => $this->getBadgeUrl(),
            'badgeStyle'   => $badgeStyle,
            'orcidIconUrl' => $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/assets/img/orcid.svg',
            'articleId'    => $article->getId(),
        ]);

        if ($codecheckData->hasCompletedCheck()) {
            $templateMgr->assign([
                'codecheckStatus'   => 'completed',
                'certificateLink'   => $codecheckData->getCertificateLink(),
                'doiLink'           => $codecheckData->getDoiLink(),
                'linkText'          => $codecheckData->getCertificate(),
                'codecheckers'      => $codecheckData->getCodecheckers(),
                'certificateDate'   => $codecheckData->getCertificateDate(),
                'summary'           => $codecheckData->getSummary(),
                'repository' => implode(', ', $codecheckData->getRepositories()),
                'manifest'          => $codecheckData->getManifest(),
                'additionalContent' => $codecheckData->getAdditionalContent(),
            ]);
        } elseif ($codecheckData->hasAssignedChecker()) {
            $templateMgr->assign([
                'codecheckStatus' => 'pending',
                'codeRepo' => implode(', ', $codecheckData->getRepositories()),
                'dataRepo'        => $codecheckData->getDataRepository(),
            ]);
        } else {
            return '';
        }

        return $templateMgr->fetch($this->plugin->getTemplateResource('frontend/objects/article_codecheck.tpl'));
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
