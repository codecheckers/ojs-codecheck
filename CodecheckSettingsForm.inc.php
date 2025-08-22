<?php
import('lib.pkp.classes.form.Form');

class CodecheckSettingsForm extends Form {
	/** @var CodecheckPlugin  */
	public $plugin;

	/**
	 * @copydoc Form::__construct()
	 */
	public function __construct($plugin) {
		// Define the settings template and store a copy of the plugin object
		parent::__construct($plugin->getTemplateResource('settings.tpl'));
		$this->plugin = $plugin;
		// Always add POST and CSRF validation to secure your form.
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Load settings already saved in the database
	 */
	public function initData() {
		$context = Application::get()->getRequest()->getContext();
		$this->setData('enableCodecheck', $this->plugin->getSetting($context->getId(), 'enableCodecheck'));
		parent::initData();
	}

	/**
	 * Load data that was submitted with the form
	 */
	public function readInputData() {
		$this->readUserVars(['enableCodecheck']);
		parent::readInputData();
	}

	/**
	 * Fetch any additional data needed for your form.
	 */
	public function fetch($request, $template = null, $display = false) {
		// Pass the plugin name to the template
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->plugin->getName());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save the settings
	 */
	public function execute(...$functionArgs) {
		$context = Application::get()->getRequest()->getContext();
		$this->plugin->updateSetting($context->getId(), 'enableCodecheck', $this->getData('enableCodecheck'));
		
		// Tell the user that the save was successful.
		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification(
			Application::get()->getRequest()->getUser()->getId(),
			NOTIFICATION_TYPE_SUCCESS,
			['contents' => __('common.changesSaved')]
		);
		return parent::execute(...$functionArgs);
	}
}