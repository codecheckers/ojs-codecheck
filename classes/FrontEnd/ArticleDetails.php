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
     * Add CODECHECK information to the article details page.
     *
     * @param string $hookName
     * @param array $params [
     *   @option array Additional parameters passed with the hook
     *   @option TemplateManager
     *   @option string The HTML output
     * ]
     */
    public function addCodecheckInfo(string $hookName, array $params): bool
    {
        // Get the CODECHECK settings for this journal or press
        $context = Application::get()->getRequest()->getContext();
        $codecheckEnabled = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_ENABLED);

        // Do not modify the output if CODECHECK is not enabled
        if (!$codecheckEnabled) {
            return false;
        }

        // TODO: In future iterations, replace this with actual certificate display logic
        // For now, just show a placeholder indicating CODECHECK is enabled
        $output = &$params[2];
        $codecheckInfo = '<div class="codecheck-info alert alert-info">' .
            '<h4><strong>CODECHECK</strong></h4>' .
            '<p>This article supports computational reproducibility through CODECHECK. ' .
            'Code execution verification system integration coming in future updates.</p>' .
            '</div>';

        $output .= $codecheckInfo;

        return false;
    }

    /**
     * Future method: Display actual CODECHECK certificate
     * 
     * @param array $article Article data
     * @return string HTML for certificate display
     */
    public function displayCertificate(array $article): string
    {
        // TODO: Implement certificate retrieval and display logic
        // This will fetch and display actual CODECHECK certificates
        return '';
    }
}