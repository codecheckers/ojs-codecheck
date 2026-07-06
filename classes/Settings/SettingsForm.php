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

        $this->setData(
            Constants::CODECHECK_ENABLED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_ENABLED
            )
        );

        $this->setData(
            Constants::CODECHECK_MODE,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_MODE
            )
        );

        $this->setData(
            Constants::CODECHECK_AUTHOR_ANONYMITY,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_AUTHOR_ANONYMITY
            )
        );

        $this->setData(
            Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN
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

        $this->setData(
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION
            )
        );

        $this->setData(
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY
            )
        );

        $this->setData(
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_GITHUB_CUSTOM_LABELS
            ) ?? []
        );

        $this->setData(
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_STATUS_KEYS_SELECTED
            ) ?? []
        );

        // Default to true — show the dashboard column unless explicitly disabled
        $showDashboardColumn = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN
        );
        $this->setData(
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            $showDashboardColumn === null ? true : (bool) $showDashboardColumn
        );

        $updateFields = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS
        ) ?? [];

        // Unpack so each checkbox gets its own template variable
        $this->setData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE, in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE, $updateFields));
        $this->setData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY, in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY, $updateFields));

        // ORCID integration settings
        $this->setData(
            Constants::ORCID_ENABLED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::ORCID_ENABLED
            )
        );

        $this->setData(
            Constants::ORCID_API_TYPE,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::ORCID_API_TYPE
            ) ?? Constants::ORCID_API_TYPE_SANDBOX
        );

        $this->setData(
            Constants::ORCID_CLIENT_ID,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::ORCID_CLIENT_ID
            )
        );

        $this->setData(
            Constants::ORCID_CLIENT_SECRET,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::ORCID_CLIENT_SECRET
            )
        );

        $this->setData(
            Constants::ORCID_CITY,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::ORCID_CITY
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
            Constants::CODECHECK_MODE,
            Constants::CODECHECK_AUTHOR_ANONYMITY,
            Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN,
            Constants::CODECHECK_API_ENDPOINT,
            Constants::CODECHECK_API_KEY,
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS,
            Constants::CODECHECK_STATUS,
            Constants::CODECHECK_STATUSES_SELECTED,
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            Constants::ORCID_ENABLED,
            Constants::ORCID_API_TYPE,
            Constants::ORCID_CLIENT_ID,
            Constants::ORCID_CLIENT_SECRET,
            Constants::ORCID_CITY,
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
        $templateMgr->assign(
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            $this->getData(Constants::CODECHECK_GITHUB_CUSTOM_LABELS) ?? []
        );
        $templateMgr->assign('codecheckModes', [
            'opt-in'    => __('plugins.generic.codecheck.settings.mode.opt.in'),
            'opt-out'   => __('plugins.generic.codecheck.settings.mode.opt.out'),
            'mandatory' => __('plugins.generic.codecheck.settings.mode.mandatory'),
        ]);

        $templateMgr->assign(
            'showDashboardColumn',
            $this->getData(Constants::CODECHECK_SHOW_DASHBOARD_COLUMN)
        );

        $templateMgr->assign(
            Constants::CODECHECK_STATUSES_SELECTED,
            (array) $this->getData(Constants::CODECHECK_STATUSES_SELECTED) ?? []
        );
        $templateMgr->assign('codecheckStatuses', Constants::CODECHECK_STATUSES);

        $templateMgr->assign('orcidApiTypes', [
            Constants::ORCID_API_TYPE_SANDBOX    => __('plugins.generic.codecheck.orcid.apiType.sandbox'),
            Constants::ORCID_API_TYPE_PRODUCTION => __('plugins.generic.codecheck.orcid.apiType.production'),
        ]);

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

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_ENABLED,
            $this->getData(Constants::CODECHECK_ENABLED)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_MODE,
            $this->getData(Constants::CODECHECK_MODE)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_AUTHOR_ANONYMITY,
            $this->getData(Constants::CODECHECK_AUTHOR_ANONYMITY)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN,
            $this->getData(Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN)
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

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            array_values(array_filter(
                (array) $this->getData(Constants::CODECHECK_GITHUB_CUSTOM_LABELS),
                fn ($label) => !empty($label)
            ))
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            (array) $this->getData(Constants::CODECHECK_STATUS_KEYS_SELECTED)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            (bool) $this->getData(Constants::CODECHECK_SHOW_DASHBOARD_COLUMN)
        );

        $updateFields = array_values(array_filter([
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE) ? Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE : null,
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY) ? Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY : null,
        ]));

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS,
            $updateFields
        );

        // Save ORCID integration settings
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::ORCID_ENABLED,
            $this->getData(Constants::ORCID_ENABLED)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::ORCID_API_TYPE,
            $this->getData(Constants::ORCID_API_TYPE)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::ORCID_CLIENT_ID,
            $this->getData(Constants::ORCID_CLIENT_ID)
        );

        // Only update secret if a new value was provided
        $newSecret = $this->getData(Constants::ORCID_CLIENT_SECRET);
        if (!empty($newSecret)) {
            $this->plugin->updateSetting(
                $context->getId(),
                Constants::ORCID_CLIENT_SECRET,
                $newSecret
            );
        }

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::ORCID_CITY,
            $this->getData(Constants::ORCID_CITY)
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