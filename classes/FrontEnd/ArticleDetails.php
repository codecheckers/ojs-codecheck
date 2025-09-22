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
use PKP\core\PKPString;

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

        // Get CODECHECK data for this submission
        $dao = new CodecheckSubmissionDAO();
        $codecheckData = $dao->getBySubmissionId($article->getId());

        // Only show CODECHECK info if user opted in
        if (!$codecheckData || !$codecheckData->getOptIn()) {
            return false;
        }

        // Generate and add the CODECHECK display
        $codecheckHtml = $this->generateSidebarDisplay($codecheckData);
        
        if ($codecheckHtml) {
            $output .= $codecheckHtml;
        }

        return false;
    }

    /**
     * Generate sidebar display for CODECHECK certificate
     */
    private function generateSidebarDisplay(CodecheckSubmission $codecheckData): string
    {
        $request = Application::get()->getRequest();
        $baseUrl = $request->getBaseUrl();
        $pluginPath = $this->plugin->getPluginPath();
        
        // Path to your CODECHECK logo
        $logoUrl = $baseUrl . '/' . $pluginPath . '/assets/img/codeworks-badge.png';

        if ($codecheckData->hasCompletedCheck()) {
            return $this->generateCompletedSidebarDisplay($codecheckData, $logoUrl);
        } else {
            return $this->generatePendingSidebarDisplay($codecheckData, $logoUrl);
        }
    }

    /**
     * Generate completed certificate sidebar display
     */
    private function generateCompletedSidebarDisplay(CodecheckSubmission $codecheckData, string $logoUrl): string
    {
        $certificateLink = $codecheckData->getCertificateLink();
        $getDoiLink = $codecheckData->getDoiLink();
        $linkText = $codecheckData->getFormattedCertificateLinkText();
        $codecheckerNames = $codecheckData->getCodecheckerNames();
        $certificateDate = $codecheckData->getCertificateDate();

        $html = '
        <div class="item certificate" style="padding: 15px; margin: 5px 0;">
            <div style="display: flex; align-items: center;">
                <img src="' . htmlspecialchars($logoUrl) . '" alt="CODECHECK" style="height: 18px; margin-right: 3px;">
                <span style=" padding: 3px 8px; border-radius: 12px; font-size: 0.8em;">
                    <strong style="font-size:1.1em;">Authors:</strong><br>' . htmlspecialchars($codecheckerNames) . '
                </span>
            </div>
            
            <h4 style="margin: 0 0 0px 0; font-size: 0.9em; font-weight: 600;">
                Certificate
            </h4>';

        if ($certificateLink) {
            $html .= '<p style="margin: 0 0 -4px 0; font-size: 0.8em;">
                <a href="' . htmlspecialchars($certificateLink) . '" target="_blank" 
                   style="color: #006798; text-decoration: underline; font-weight: 500;">
                    ' . htmlspecialchars($linkText) . '
                </a>
            </p>';
        }
        if ($getDoiLink) {
            $html .= '<p style="margin: 0 0 8px 0; font-size: 0.8em;">
                <a href="' . htmlspecialchars($getDoiLink) . '" target="_blank" 
                   style="color: #006798; text-decoration: underline; font-weight: 500;">
                    ' . htmlspecialchars($getDoiLink) . '
                </a>
            </p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate pending check sidebar display
     */
    private function generatePendingSidebarDisplay(CodecheckSubmission $codecheckData, string $logoUrl): string
    {
        $codeRepo = $codecheckData->getCodeRepository();
        $dataRepo = $codecheckData->getDataRepository();

        $html = '
        <div class="item codecheck-pending" style="background: #fff8e1; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <img src="' . htmlspecialchars($logoUrl) . '" alt="CODECHECK" style="height: 24px; margin-right: 8px;">
                <span style="background: #ffc107; color: #212529; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold;">
                    ⏳ In Progress
                </span>
            </div>
            
            <h4 style="color: #bf8f00; margin: 0 0 10px 0; font-size: 1.1em; font-weight: bold;">
                CODECHECK
            </h4>
            
            <p style="margin: 0 0 10px 0; font-size: 0.85em; color: #666; line-height: 1.4;">
                Computational reproducibility verification in progress.
            </p>';

        if ($codeRepo || $dataRepo) {
            $html .= '<div style="font-size: 0.8em; color: #666;">';
            if ($codeRepo) {
                $html .= '<p style="margin: 2px 0; word-break: break-all;">
                    <strong>Code:</strong> <a href="' . htmlspecialchars($codeRepo) . '" target="_blank" style="color: #bf8f00;">' . 
                    htmlspecialchars(strlen($codeRepo) > 30 ? substr($codeRepo, 0, 30) . '...' : $codeRepo) . '</a>
                </p>';
            }
            if ($dataRepo) {
                $html .= '<p style="margin: 2px 0; word-break: break-all;">
                    <strong>Data:</strong> <a href="' . htmlspecialchars($dataRepo) . '" target="_blank" style="color: #bf8f00;">' . 
                    htmlspecialchars(strlen($dataRepo) > 30 ? substr($dataRepo, 0, 30) . '...' : $dataRepo) . '</a>
                </p>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}