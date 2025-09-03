<?php
/**
 * @file classes/Settings/SettingsForm.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsForm
 * @brief Settings form class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\Settings;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SettingsForm extends Form
{
    /** @var CodecheckPlugin */
    public CodecheckPlugin $plugin;

    /**
     * Defines the settings form's template and adds validation checks.
     *
     * Always add POST and CSRF validation to secure your form.
     */
    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;

        parent::__construct($this->plugin->getTemplateResource(Constants::SETTINGS_TEMPLATE));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load settings already saved in the database
     *
     * Settings are stored by context, so that each journal, press,
     * or preprint server can have different settings.
     */
    public function initData(): void
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        // Load CODECHECK-specific settings
        $this->setData(
            Constants::CODECHECK_ENABLED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_ENABLED
            )
        );

        $this->setData(
            Constants::CODECHECK_API_ENDPOINT,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_API_ENDPOINT
            )
        );

        $this->setData(
            Constants::CODECHECK_API_KEY,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_API_KEY
            )
        );

        parent::initData();
    }

    /**
     * Load data that was submitted with the form
     */
    public function readInputData(): void
    {
        $this->readUserVars([
            Constants::CODECHECK_ENABLED,
            Constants::CODECHECK_API_ENDPOINT,
            Constants::CODECHECK_API_KEY
        ]);

        parent::readInputData();
    }

    /**
     * Fetch any additional data needed for your form.
     *
     * Data assigned to the form using $this->setData() during the
     * initData() or readInputData() methods will be passed to the
     * template.
     */
    public function fetch($request, $template = null, $display = false): ?string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the plugin settings and notify the user
     * that the save was successful
     */
    public function execute(...$functionArgs): mixed
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        // Save CODECHECK-specific settings
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_ENABLED,
            $this->getData(Constants::CODECHECK_ENABLED)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_API_ENDPOINT,
            $this->getData(Constants::CODECHECK_API_ENDPOINT)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_API_KEY,
            $this->getData(Constants::CODECHECK_API_KEY)
        );

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }
}