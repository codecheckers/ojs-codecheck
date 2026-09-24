<?php

/**
 * @file classes/Settings/SettingsForm.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class SettingsForm
 *
 * @brief Settings form class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\Settings;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;
use Github\Client;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SettingsForm extends Form
{
    /**  */
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

        $this->setData(
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            (bool) $this->plugin->getSettingWithDefault(
                $context->getId(),
                Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT
            )
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

        // The versions on offer, asked of the plugin so the form cannot show a
        // version the metadata form would not accept.
        $this->setData(
            Constants::CODECHECK_ENABLED_CONFIG_VERSIONS,
            $this->plugin->getEnabledConfigVersions($context->getId())
        );

        $this->setData(
            Constants::CODECHECK_SHOW_IN_TOC,
            (bool) $this->plugin->getSettingWithDefault(
                $context->getId(),
                Constants::CODECHECK_SHOW_IN_TOC
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

        // Empty means "use the localised default", which the badge substitutes
        // rather than rendering nothing where the image would be.
        $this->setData(
            Constants::CODECHECK_BADGE_TEXT,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TEXT) ?? ''
        );

        $this->setData(
            Constants::CODECHECK_BADGE_LINK_TARGET,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_LINK_TARGET)
                ?: Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER
        );

        // A colour input needs a value to open on, so unset means the default
        // rather than an empty string.
        $this->setData(
            Constants::CODECHECK_BADGE_TEXT_COLOR,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TEXT_COLOR)
                ?: Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT
        );

        $this->setData(
            Constants::CODECHECK_BADGE_CUSTOM_URL,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_CUSTOM_URL)
        );

        $this->setData(
            Constants::CODECHECK_BADGE_HEIGHT,
            Constants::normalizeBadgeHeight(
                $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_HEIGHT)
            )
        );

        $this->setData(
            Constants::CODECHECK_STATUS_KEYS_SELECTED,
            $this->plugin->getSetting(
                $context->getId(),
                Constants::CODECHECK_STATUS_KEYS_SELECTED
            ) ?? []
        );

        // Asked of the plugin, never resolved here — see #177.
        $this->setData(
            Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED,
            $this->plugin->isRegisterDepositEnabled($context->getId())
        );

        $this->setData(
            Constants::CODECHECK_SHOW_DASHBOARD_COLUMN,
            (bool) $this->plugin->getSettingWithDefault(
                $context->getId(),
                Constants::CODECHECK_SHOW_DASHBOARD_COLUMN
            )
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
            Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR,
            Constants::CODECHECK_SHOW_IN_TOC,
            Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT,
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING,
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT,
            Constants::CODECHECK_ENABLED_CONFIG_VERSIONS,
            Constants::CODECHECK_MODE,
            Constants::CODECHECK_AUTHOR_ANONYMITY,
            Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN,
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION,
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY,
            Constants::CODECHECK_GITHUB_CUSTOM_LABELS,
            Constants::CODECHECK_BADGE_TYPE,
            Constants::CODECHECK_BADGE_TEXT,
            Constants::CODECHECK_BADGE_TEXT_COLOR,
            Constants::CODECHECK_BADGE_LINK_TARGET,
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
     *
     * @param null|mixed $template
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
            'opt-in' => __('plugins.generic.codecheck.settings.mode.opt.in'),
            'opt-out' => __('plugins.generic.codecheck.settings.mode.opt.out'),
            'mandatory' => __('plugins.generic.codecheck.settings.mode.mandatory'),
        ]);

        $templateMgr->assign('codecheckBadgeType', $this->getData(Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks');
        $templateMgr->assign('codecheckBadgeText', $this->getData(Constants::CODECHECK_BADGE_TEXT) ?? '');

        // The select renders label => value pairs, like the CODECHECK mode above.
        $templateMgr->assign('codecheckBadgeLinkTargets', array_combine(
            Constants::CODECHECK_BADGE_LINK_TARGETS,
            array_map(
                fn ($target) => __('plugins.generic.codecheck.settings.badge.linkTarget.' . $target),
                Constants::CODECHECK_BADGE_LINK_TARGETS
            )
        ));
        $templateMgr->assign(
            'codecheckBadgeLinkTarget',
            $this->getData(Constants::CODECHECK_BADGE_LINK_TARGET) ?: Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER
        );
        $templateMgr->assign(
            'codecheckBadgeTextColor',
            $this->getData(Constants::CODECHECK_BADGE_TEXT_COLOR) ?: Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT
        );
        $templateMgr->assign('codecheckBadgeCustomUrl', $this->getData(Constants::CODECHECK_BADGE_CUSTOM_URL) ?? '');
        $templateMgr->assign('codecheckBadgeHeight', $this->getData(Constants::CODECHECK_BADGE_HEIGHT));
        $templateMgr->assign('codecheckBadgeHeightMin', Constants::CODECHECK_BADGE_HEIGHT_MIN);
        $templateMgr->assign('codecheckBadgeHeightMax', Constants::CODECHECK_BADGE_HEIGHT_MAX);

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

        $templateMgr->assign('codecheckConfigVersions', Constants::CODECHECK_CONFIG_VERSIONS);
        $templateMgr->assign(
            Constants::CODECHECK_ENABLED_CONFIG_VERSIONS,
            (array) $this->getData(Constants::CODECHECK_ENABLED_CONFIG_VERSIONS)
        );

        $templateMgr->assign(
            Constants::CODECHECK_STATUSES_SELECTED,
            (array) $this->getData(Constants::CODECHECK_STATUSES_SELECTED) ?? []
        );
        $templateMgr->assign('codecheckStatuses', Constants::CODECHECK_STATUSES);

        $templateMgr->assign('orcidApiTypes', [
            Constants::ORCID_API_TYPE_SANDBOX => __('plugins.generic.codecheck.orcid.apiType.sandbox'),
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
            Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR,
            (bool) $this->getData(Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_SHOW_IN_TOC,
            (bool) $this->getData(Constants::CODECHECK_SHOW_IN_TOC)
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

        // Stored as selected, narrowed by the plugin's own rule so nothing the
        // form did not offer is written. An empty selection is stored empty and
        // resolved when it is read (#178) — the default is not spelled out
        // here a second time.
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_ENABLED_CONFIG_VERSIONS,
            CodecheckPlugin::narrowConfigVersions(
                (array) $this->getData(Constants::CODECHECK_ENABLED_CONFIG_VERSIONS)
            )
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
            Constants::CODECHECK_BADGE_TEXT,
            trim((string) $this->getData(Constants::CODECHECK_BADGE_TEXT))
        );

        // Only one of the two known targets is ever stored.
        $linkTarget = (string) $this->getData(Constants::CODECHECK_BADGE_LINK_TARGET);
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_LINK_TARGET,
            in_array($linkTarget, Constants::CODECHECK_BADGE_LINK_TARGETS, true)
                ? $linkTarget
                : Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER
        );

        // Store only a real hex colour, so nothing else can end up in a style
        // attribute on the article page.
        $badgeTextColor = trim((string) $this->getData(Constants::CODECHECK_BADGE_TEXT_COLOR));
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_TEXT_COLOR,
            preg_match('/^#[0-9a-fA-F]{6}$/', $badgeTextColor)
                ? $badgeTextColor
                : Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_CUSTOM_URL,
            $this->getData(Constants::CODECHECK_BADGE_CUSTOM_URL)
        );

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_HEIGHT,
            // Clearing the field stores the default rather than the `0` an
            // empty string casts to — see Constants::normalizeBadgeHeight().
            Constants::normalizeBadgeHeight($this->getData(Constants::CODECHECK_BADGE_HEIGHT))
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
