<?php
/**
 * @file CodecheckPlugin.php
 *
 * Copyright (c) 2024 Technische Universität Dresden
 * Copyright (c) 2024 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckPlugin
 * @brief Plugin class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck;

use APP\core\Request;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CodecheckPlugin extends GenericPlugin
{
    /** @copydoc GenericPlugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            // Display CODECHECK information on the article details page
            $articleDetails = new ArticleDetails($this);
            Hook::add('Templates::Article::Main', $articleDetails->addCodecheckInfo(...));
			
            // TODO: Register hooks for workflow integration in future iterations
            // Hook::add('EditorAction::recordDecision', $this->handleReviewDecision(...));
            // Hook::add('Templates::Reviewer::Review::Step3::ReviewForm', $this->addCodecheckFields(...));
        }

        return $success;
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.codecheck.displayName');
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.codecheck.description');
    }

    /**
     * Add a settings action to the plugin's entry in the plugins list.
     *
     * @param Request $request
     * @param array $actionArgs
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /**
     * Load a form when the `settings` button is clicked and
     * save the form when the user saves it.
     *
     * @param array $args
     * @param Request $request
     */
    public function manage($args, $request): JSONMessage
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    /**
     * TODO: Future method for handling review decisions
     * This will be implemented in future iterations to integrate
     * CODECHECK workflow with the editorial process.
     *
     * @param string $hookName
     * @param array $params
     */
    public function handleReviewDecision(string $hookName, array $params): bool
    {
        // TODO: Implement CODECHECK workflow integration
        // This could trigger CODECHECK process when certain editorial decisions are made
        return false;
    }

    /**
     * TODO: Future method for adding CODECHECK fields to reviewer forms
     * This will be implemented in future iterations.
     *
     * @param string $hookName
     * @param array $params
     */
    public function addCodecheckFields(string $hookName, array $params): bool
    {
        // TODO: Add CODECHECK-specific fields to reviewer forms
        // This could include options for reviewers to request code checking
        return false;
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}