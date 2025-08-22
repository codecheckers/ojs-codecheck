<?php
/**
 * @file CodecheckPlugin.inc.php
 *
 * Copyright (c) 2024 Technische Universität Dresden
 * Copyright (c) 2024 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckPlugin
 * @brief Plugin class for the CODECHECK plugin.
 */
import('lib.pkp.classes.plugins.GenericPlugin');

class CodecheckPlugin extends GenericPlugin {

	/**
	 * @copydoc GenericPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path);
		if ($success && $this->getEnabled()) {
			// TODO: Register hooks for workflow integration in future iterations
			// HookRegistry::register('EditorAction::recordDecision', [$this, 'handleReviewDecision']);
			// HookRegistry::register('Templates::Reviewer::Review::Step3::ReviewForm', [$this, 'addCodecheckFields']);
			
			// For now: Just register the article page display hook as example
			HookRegistry::register('Templates::Article::Main', [$this, 'displayCodecheckInfo']);
		}
		return $success;
	}

	/**
	 * Provide a name for this plugin
	 */
	public function getDisplayName() {
		return __('plugins.generic.codecheck.displayName');
	}

	/**
	 * Provide a description for this plugin
	 */
	public function getDescription() {
		return __('plugins.generic.codecheck.description');
	}

	/**
	 * Add a settings action to the plugin's entry in the plugins list.
	 */
	public function getActions($request, $actionArgs) {
		$actions = parent::getActions($request, $actionArgs);

		if (!$this->getEnabled()) {
			return $actions;
		}

		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$linkAction = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					[
						'verb' => 'settings',
						'plugin' => $this->getName(),
						'category' => 'generic'
					]
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);

		array_unshift($actions, $linkAction);
		return $actions;
	}

	/**
	 * Show and save the settings form
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				// Load the custom form
				$this->import('CodecheckSettingsForm');
				$form = new CodecheckSettingsForm($this);

				if (!$request->getUserVar('save')) {
					$form->initData();
					return new JSONMessage(true, $form->fetch($request));
				}

				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					return new JSONMessage(true);
				}
		}
		return parent::manage($args, $request);
	}

	/**
	 * Display CODECHECK information on article page (placeholder)
	 * 
	 * TODO: Replace with actual certificate display logic later
	 */
	function displayCodecheckInfo($hookName, $params) {
		// For now: Just add a placeholder
		$context = Application::get()->getRequest()->getContext();
		$enabled = $this->getSetting($context->getId(), 'enabled');

		if (!$enabled) {
			return false;
		}

		$output =& $params[2];
		$output .= '<div class="codecheck-info"><p><strong>CODECHECK:</strong> Code execution verification system (Coming in future updates)</p></div>';

		return false;
	}
}