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
use Github\Client;

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
            Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR
            )
        );

        // Default to true — the availability statement shows unless switched off
        $showAvailabilityStatement = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT
        );
        $this->setData(
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            $showAvailabilityStatement === null ? true : (bool) $showAvailabilityStatement
        );

        // Default to false — an article without a statement says so rather
        // than dropping the section.
        $this->setData(
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT,
            (bool) $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT
            )
        );

        // Empty means "use the localised default", which the article page
        // substitutes rather than rendering an empty heading.
        $this->setData(
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING
            ) ?? ''
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
            Constants::CODECHECK_BADGE_TYPE,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks'
        );

        $this->setData(
            Constants::CODECHECK_BADGE_CUSTOM_URL,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_CUSTOM_URL)
        );

        $this->setData(
            Constants::CODECHECK_BADGE_HEIGHT,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_HEIGHT) ?? '24'
        );

        $this->setData(
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_STATUS_KEYS_SELECTED
            ) ?? []
        );

        // Default to true — register deposit runs unless explicitly disabled
        $registerDepositEnabled = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED
        );
        $this->setData(
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED,
            $registerDepositEnabled === null ? true : (bool) $registerDepositEnabled
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
        $this->setData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $updateFields));

        $this->setData(
            Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED
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
            Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR,
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING,
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT,
            Constants::CODECHECK_MODE,
            Constants::CODECHECK_AUTHOR_ANONYMITY,
            Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN,
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            Constants::CODECHECK_BADGE_TYPE,
            Constants::CODECHECK_BADGE_CUSTOM_URL,
            Constants::CODECHECK_BADGE_HEIGHT,
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS,
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS,
            Constants::CODECHECK_STATUS,
            Constants::CODECHECK_STATUSES_SELECTED,
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED,
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED,
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
        
        $templateMgr->assign('codecheckBadgeType', $this->getData(Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks');
        $templateMgr->assign('codecheckBadgeCustomUrl', $this->getData(Constants::CODECHECK_BADGE_CUSTOM_URL) ?? '');
        $templateMgr->assign('codecheckBadgeHeight', $this->getData(Constants::CODECHECK_BADGE_HEIGHT) ?? '24');
        
        $templateMgr->assign(
            'showDashboardColumn',
            $this->getData(Constants::CODECHECK_SHOW_DASHBOARD_COLUMN)
        );

        $templateMgr->assign(
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            $this->getData(Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT)
        );

        $templateMgr->assign(
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT,
            $this->getData(Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT)
        );

        $templateMgr->assign(
            Constants::CODECHECK_STATUSES_SELECTED,
            (array) $this->getData(Constants::CODECHECK_STATUSES_SELECTED) ?? []
        );
        $templateMgr->assign('codecheckStatuses', Constants::CODECHECK_STATUSES);

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
            Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR,
            (bool) $this->getData(Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            (bool) $this->getData(Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING,
            trim((string) $this->getData(Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING))
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT,
            (bool) $this->getData(Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT)
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

        // Remember what the register pointed at before this save, so the
        // repository is only looked up when it actually changed.
        $previousOrganization = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION
        );
        $previousRepository = $this->plugin->getSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY
        );

        $organization = $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $repository = $this->getData(Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            $organization
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            $repository
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED,
            (bool) $this->getData(Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED)
        );

        // Only reach out to GitHub when the register actually changed. This is
        // an unauthenticated request against a 60/hour per-IP limit, and every
        // unrelated settings save used to spend one.
        $registerChanged = $organization !== $previousOrganization
            || $repository !== $previousRepository;

        $registerWarning = $registerChanged
            ? $this->validateRegisterFileExists($organization, $repository)
            : null;

        if ($registerWarning !== null) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                Application::get()->getRequest()->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_WARNING,
                ['contents' => $registerWarning]
            );
        }

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
            Constants::CODECHECK_BADGE_TYPE,
            $this->getData(Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks'
        );
        
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            (array) $this->getData(Constants::CODECHECK_STATUS_KEYS_SELECTED)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_CUSTOM_URL,
            $this->getData(Constants::CODECHECK_BADGE_CUSTOM_URL)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_HEIGHT,
            (int) ($this->getData(Constants::CODECHECK_BADGE_HEIGHT) ?? 24)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            (bool) $this->getData(Constants::CODECHECK_SHOW_DASHBOARD_COLUMN)
        );
        
        $updateFields = array_values(array_filter([
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE) ? Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE : null,
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY) ? Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY : null,
            $this->getData(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS) ? Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS : null,
        ]));

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS,
            $updateFields
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED,
            $this->getData(Constants::CODECHECK_PUBLICATION_VALIDATION_EXTENDED)
        );

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }

    /**
     * Checks whether `register.csv` exists at the root of the configured
     * GitHub register repository, so a misconfigured target is caught at
     * settings-save time instead of silently failing on the next publish.
     */
    private function validateRegisterFileExists(string $organization, string $repository): ?string
    {
        if (empty($organization) || empty($repository)) {
            return null; // nothing to check yet
        }

        try {
            $client = new Client();
            $client->api('repo')->contents()->show($organization, $repository, 'register.csv');
            return null; // found, no warning needed
        } catch (\Throwable $e) {
            return __('plugins.generic.codecheck.settings.github.registerRepository.missingCsvWarning', [
                'organization' => $organization,
                'repository' => $repository,
            ]);
        }
    }
}
