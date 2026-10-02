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
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SettingsForm extends Form
{
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

        // Per locale (#164). A locale with nothing in it means "use the
        // primary locale's, else the localised default", which the article
        // page substitutes rather than rendering an empty heading.
        $this->setData(
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING,
            $this->inFormLocales($this->plugin->getSetting($context->getId(), Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING))
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

        // Shown as stored: an empty field is the default, which the field's
        // description spells out, so a journal that never wrote its own text
        // follows the default when it changes.
        $this->setData(
            Constants::CODECHECK_GITHUB_SIGNATURE,
            (string) $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_SIGNATURE)
        );

        $this->setData(
            Constants::CODECHECK_BADGE_TYPE,
            $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TYPE) ?? 'codeworks'
        );

        // Per locale, like the heading: a locale with nothing in it falls
        // back rather than rendering nothing where the image would be.
        $this->setData(
            Constants::CODECHECK_BADGE_TEXT,
            $this->inFormLocales($this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TEXT))
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
            Constants::normalizeBadgeTextColor(
                $this->plugin->getSetting($context->getId(), Constants::CODECHECK_BADGE_TEXT_COLOR)
            )
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

        foreach (Constants::CODECHECK_DOI_DEPOSIT_SETTINGS as $name) {
            $this->setData($name, (bool) $this->plugin->getSetting($context->getId(), $name));
        }

        $this->setData(
            Constants::CODECHECK_CERTIFICATE_REFERENCE,
            $this->plugin->getCertificateReferenceMode($context->getId())
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
            Constants::CODECHECK_GITHUB_SIGNATURE,
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
            ...Constants::CODECHECK_DOI_DEPOSIT_SETTINGS,
            Constants::CODECHECK_CERTIFICATE_REFERENCE,
            Constants::ORCID_ENABLED,
            Constants::ORCID_API_TYPE,
            Constants::ORCID_CLIENT_ID,
            Constants::ORCID_CLIENT_SECRET,
            Constants::ORCID_CITY,
        ]);

        parent::readInputData();
    }

    /**
     * A journal-worded text for the languages this form offers — the
     * journal's form locales — and no others; see Constants::cleanLocalizedText().
     */
    private function inFormLocales(mixed $text): array
    {
        return Constants::cleanLocalizedText($text, array_keys($this->supportedLocales));
    }

    /**
     * What a save stores for a journal-worded text: what the form posted for
     * its languages, and what was stored for any other. A journal's reader
     * languages need not be form languages, and a save must not drop wording
     * the form never showed.
     */
    private function localizedTextToSave(int $contextId, string $name): array
    {
        $stored = $this->plugin->getSetting($contextId, $name);
        $notOffered = is_array($stored) ? array_diff_key($stored, $this->supportedLocales) : [];

        return array_merge($notOffered, $this->inFormLocales($this->getData($name)));
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
        $templateMgr->assign('codecheckCertificateReferenceModes', Constants::CODECHECK_CERTIFICATE_REFERENCE_MODES);
        // The journal's own References switch: off means nobody can see the list.
        $templateMgr->assign('codecheckJournalCollectsReferences', (bool) $request->getContext()?->getData('citations'));
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
        $templateMgr->assign('codecheckBadgeTextColor', $this->getData(Constants::CODECHECK_BADGE_TEXT_COLOR));
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

        // Passed in, not written into the message: `{$journal}` in a locale
        // string would be read as one of the message's own parameters.
        $templateMgr->assign('githubSignatureDefault', Constants::CODECHECK_GITHUB_SIGNATURE_DEFAULT);
        $templateMgr->assign('githubSignaturePlaceholders', ['journal' => '{$journal}', 'journalUrl' => '{$journalUrl}']);

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
            $this->localizedTextToSave($context->getId(), Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING)
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

        $registerWarnings = $registerChanged
            ? $this->checkRegisterRepository($organization, $repository)
            : [];

        $notificationMgr = new NotificationManager();
        foreach ($registerWarnings as $registerWarning) {
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
            Constants::CODECHECK_GITHUB_SIGNATURE,
            trim((string) $this->getData(Constants::CODECHECK_GITHUB_SIGNATURE))
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
            $this->localizedTextToSave($context->getId(), Constants::CODECHECK_BADGE_TEXT)
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
        // attribute on the article page — see Constants::normalizeBadgeTextColor().
        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_BADGE_TEXT_COLOR,
            Constants::normalizeBadgeTextColor($this->getData(Constants::CODECHECK_BADGE_TEXT_COLOR))
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

        foreach (Constants::CODECHECK_DOI_DEPOSIT_SETTINGS as $name) {
            $this->plugin->updateSetting($context->getId(), $name, (bool) $this->getData($name));
        }

        $this->plugin->updateSetting(
            $context->getId(),
            Constants::CODECHECK_CERTIFICATE_REFERENCE,
            Constants::normalizeCertificateReferenceMode($this->getData(Constants::CODECHECK_CERTIFICATE_REFERENCE))
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
     * Checks what the configured GitHub register repository has to carry, so a
     * misconfigured target is caught at settings-save time instead of silently
     * failing on the next publish, reservation or status change: `register.csv`
     * at its root for register deposits, the `id assigned` label, which is what
     * certificate identifiers are found by (#129), and the labels a status
     * change moves (#174). A missing label is not harmless: GitHub creates an
     * unknown label when an issue is given one, so the plugin would otherwise
     * invent labels in someone else's repository.
     *
     * The requests are unauthenticated and count against GitHub's 60/hour
     * per-IP limit — one per required label plus one for the file, so four — and
     * this therefore runs only when the register actually changed; see the
     * caller.
     *
     * @return string[] One warning per missing requirement, or a single warning
     *                  when the repository could not be read at all; empty when
     *                  it carries both.
     */
    private function checkRegisterRepository(string $organization, string $repository): array
    {
        if (empty($organization) || empty($repository)) {
            return []; // nothing to check yet
        }

        $messageParams = ['organization' => $organization, 'repository' => $repository];

        // The labels the plugin needs: the one it finds identifiers by, and the
        // ones it moves as a check progresses (#174). Probed with the same call
        // the reservation and the status sync use, so they cannot disagree about
        // whether the register is usable. Building the client can fail on its own
        // — an incomplete `vendor/` leaves php-http with no discoverable client —
        // and that must warn rather than abandon the whole settings save.
        $requiredLabels = array_merge(
            [Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL],
            Constants::CODECHECK_REGISTER_MANAGED_LABELS
        );

        $missingLabels = [];

        try {
            $client = GithubHttp::client();

            foreach ($requiredLabels as $label) {
                $hasLabel = CodecheckGithubRegisterApiClient::repositoryHasLabel(
                    $organization,
                    $repository,
                    $label,
                    $client
                );

                // Nothing could be read, so no requirement can be reported as
                // missing: one honest warning rather than several wrong ones.
                if ($hasLabel === null) {
                    return [__('plugins.generic.codecheck.settings.github.registerRepository.unreadableWarning', $messageParams)];
                }

                if ($hasLabel === false) {
                    $missingLabels[] = $label;
                }
            }
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not check the register repository: ' . $e->getMessage());

            return [__('plugins.generic.codecheck.settings.github.registerRepository.unreadableWarning', $messageParams)];
        }

        $warnings = [];

        foreach ($missingLabels as $label) {
            $warnings[] = __('plugins.generic.codecheck.settings.github.registerRepository.missingLabelWarning', $messageParams + [
                'label' => $label,
            ]);
        }

        try {
            $client->api('repo')->contents()->show($organization, $repository, 'register.csv');
        } catch (\Throwable $e) {
            $warnings[] = __('plugins.generic.codecheck.settings.github.registerRepository.missingCsvWarning', $messageParams);
        }

        return $warnings;
    }
}
