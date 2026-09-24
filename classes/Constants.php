<?php

/**
 * @file classes/Constants.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class Constants
 *
 * @brief Constants used in the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes;

class Constants
{
    /**
     * The file name of the settings template
     */
    public const SETTINGS_TEMPLATE = 'settings.tpl';

    /**
     * The possible Codecheck Statuses
     */
    public const CODECHECK_STATUS_PENDING = 'plugins.generic.codecheck.status.pending';
    public const CODECHECK_STATUS_NEEDS_CODECHECKER = 'plugins.generic.codecheck.status.needsCodechecker';
    public const CODECHECK_STATUS_ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';
    public const CODECHECK_STATUS_STALLED_AUTHOR = 'plugins.generic.codecheck.status.stalled.author';
    public const CODECHECK_STATUS_STALLED_CODECHECKER = 'plugins.generic.codecheck.status.stalled.codechecker';
    public const CODECHECK_STATUS_COMPLETED_UNSUCCESSFUL = 'plugins.generic.codecheck.status.completed.unsuccessful';
    public const CODECHECK_STATUS_COMPLETED_PARTIAL_REPRODUCTION = 'plugins.generic.codecheck.status.completed.partialReproduction';
    public const CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION = 'plugins.generic.codecheck.status.completed.fullReproduction';
    public const CODECHECK_STATUS_PUBLISHED_PARTIAL_REPRODUCTION = 'plugins.generic.codecheck.status.publishedCertificate.partialReproduction';
    public const CODECHECK_STATUS_PUBLISHED_FULL_REPRODUCTION = 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction';

    public const CODECHECK_STATUSES = [
        Constants::CODECHECK_STATUS_NEEDS_CODECHECKER,
        Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER,
        Constants::CODECHECK_STATUS_STALLED_AUTHOR,
        Constants::CODECHECK_STATUS_STALLED_CODECHECKER,
        Constants::CODECHECK_STATUS_COMPLETED_UNSUCCESSFUL,
        Constants::CODECHECK_STATUS_COMPLETED_PARTIAL_REPRODUCTION,
        Constants::CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION,
        Constants::CODECHECK_STATUS_PUBLISHED_PARTIAL_REPRODUCTION,
        Constants::CODECHECK_STATUS_PUBLISHED_FULL_REPRODUCTION,
    ];

    public const CODECHECK_SHOW_ARTICLE_SIDEBAR = 'showArticleSidebar';
    public const CODECHECK_SHOW_IN_TOC = 'showInTOC';

    # Data and software availability statement on the article landing page
    public const CODECHECK_SHOW_AVAILABILITY_STATEMENT = 'showAvailabilityStatement';
    public const CODECHECK_AVAILABILITY_STATEMENT_HEADING = 'availabilityStatementHeading';
    public const CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT = 'hideEmptyAvailabilityStatement';

    public const CODECHECK_AUTHOR_ANONYMITY = 'authorAnonymity';
    public const CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN = 'githubPersonalAccessToken';
    public const CODECHECK_GITHUB_REGISTER_ORGANIZATION = 'githubRegisterOrganization';
    public const CODECHECK_GITHUB_REGISTER_REPOSITORY = 'githubRegisterRepository';
    public const CODECHECK_GITHUB_CUSTOM_LABELS = 'githubCustomLabels';
    public const CODECHECK_MODE = 'codecheckMode';

    public const CODECHECK_BADGE_TYPE = 'codecheckBadgeType';
    public const CODECHECK_BADGE_CUSTOM_URL = 'codecheckBadgeCustomUrl';
    public const CODECHECK_BADGE_HEIGHT = 'codecheckBadgeHeight';
    # Shown where the image would be when the badge type is 'none'
    public const CODECHECK_BADGE_TEXT = 'codecheckBadgeText';
    public const CODECHECK_BADGE_TEXT_COLOR = 'codecheckBadgeTextColor';

    # Where the badge takes a reader: the certificate's DOI, or its landing
    # page in the CODECHECK register
    public const CODECHECK_BADGE_LINK_TARGET = 'codecheckBadgeLinkTarget';
    public const CODECHECK_BADGE_LINK_TARGET_REGISTER = 'register';
    public const CODECHECK_BADGE_LINK_TARGET_DOI = 'doi';
    public const CODECHECK_BADGE_LINK_TARGETS = [
        self::CODECHECK_BADGE_LINK_TARGET_REGISTER,
        self::CODECHECK_BADGE_LINK_TARGET_DOI,
    ];

    /** Where a certificate's landing page lives in the register. */
    public const CODECHECK_REGISTER_CERTIFICATE_URL = 'https://codecheck.org.uk/register/certs/';

    /**
     * The register landing page for a certificate identifier, or an empty
     * string when the value is not an identifier this can build a URL from.
     *
     * Identifiers are stored as they appear in the register, `YYYY-NNN`; a
     * `CODECHECK-` prefix is tolerated because older records carry one.
     */
    /**
     * May this address be put in an `href` on a page we render?
     *
     * Only `http://` and `https://`. `filter_var(…, FILTER_VALIDATE_URL)` is not
     * a substitute: it validates the *syntax* `scheme:…` and says nothing about
     * the scheme, so `javascript://x%0Aalert(1)` passes it. Verified on PHP 8.2.
     *
     * The same rule decides whether a repository is rendered as a link
     * (`CodecheckSubmissionDAO::getPublicRepositories()`) and whether one is
     * refused on save (`CodecheckRepositories::newUnusableUrls()`, which judges
     * what a save introduces); it lives here so there is one definition to
     * audit rather than four. `resources/js/isWebUrl.js` mirrors it for the
     * submission wizard's field.
     */
    public static function isWebUrl(?string $url): bool
    {
        return (bool) preg_match('#^https?://#i', trim((string) $url));
    }

    public static function getRegisterCertificateUrl(string $certificate): string
    {
        $identifier = preg_replace('/^CODECHECK-/', '', trim($certificate));

        return preg_match('/^\d{4}-\d+$/', $identifier)
            ? self::CODECHECK_REGISTER_CERTIFICATE_URL . $identifier . '/'
            : '';
    }
    /** The green the badge text has always been rendered in. */
    public const CODECHECK_BADGE_TEXT_COLOR_DEFAULT = '#2d7f3e';

    /** The height the badge image has always been rendered at, in pixels. */
    public const CODECHECK_BADGE_HEIGHT_DEFAULT = 24;

    /**
     * What the settings form offers, and what `normalizeBadgeHeight()` holds a
     * stored value to. The form renders these as the number field's `min` and
     * `max`, which is presentation only: PKP posts the form through its own
     * handler, so the browser never gets to refuse anything.
     */
    public const CODECHECK_BADGE_HEIGHT_MIN = 10;
    public const CODECHECK_BADGE_HEIGHT_MAX = 200;

    /**
     * The badge height as a usable number of pixels.
     *
     * "Not a height" — nothing recorded, an emptied field, zero, negative, or
     * not a number at all — is the default, and a number outside the range the
     * form offers is the nearest end of it. This is the only place that says
     * so, and it has to: the value is written into a `style` attribute on the
     * article page and in the issue table of contents, and the `min`/`max` on
     * the form field is advisory — PKP submits the form itself, so nothing
     * stopped a journal from storing a height of 100000. Two readers used to answer differently: the settings form stored
     * `(int) ''`, which is `0`, and showed that back, while the article page
     * read `0 ?: 24` and rendered 24 (#178).
     *
     * It is applied on save and on read, as the badge text colour's rule is,
     * rather than through `CODECHECK_SETTING_DEFAULTS`. A recorded default
     * abolishes the *unset* state, which is not the problem here: a row written
     * by an earlier version holds that `0`, so a reader has to judge the stored
     * value whatever the map says — and a written row would make a later change
     * to a cosmetic pixel value need an upgrade migration.
     */
    public static function normalizeBadgeHeight(mixed $height): int
    {
        if (!is_numeric($height) || (int) $height <= 0) {
            return self::CODECHECK_BADGE_HEIGHT_DEFAULT;
        }

        return max(
            self::CODECHECK_BADGE_HEIGHT_MIN,
            min(self::CODECHECK_BADGE_HEIGHT_MAX, (int) $height)
        );
    }

    public const CODECHECK_SHOW_DASHBOARD_COLUMN = 'showDashboardColumn';

    // Update Github Register Issue
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS = 'codecheckGithubUpdateFields';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE = 'updateTitle';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY = 'updateBody';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS = 'updateStatus';

    // Codecheck Status
    public const CODECHECK_STATUS = 'codecheckStatus';
    public const CODECHECK_STATUSES_SELECTED = 'codecheckStatusesSelected';
    public const CODECHECK_STATUS_KEYS_SELECTED = 'codecheckStatusKeysSelected';

    // Extended Validation
    public const CODECHECK_PUBLICATION_VALIDATION_EXTENDED = 'codecheckPublicationExtendedValidation';

    // Register Deposit (Issue #10)
    public const CODECHECK_REGISTER_DEPOSIT_ENABLED = 'codecheckRegisterDepositEnabled';

    /** Deposit to the CODECHECK Register unless a journal says otherwise. */
    public const CODECHECK_REGISTER_DEPOSIT_ENABLED_DEFAULT = true;

    /**
     * The settings that get a written row, and the value written (#177, #178).
     *
     * `CodecheckPlugin::getSettingWithDefault()` reads through this map and
     * `writeDefaultSettings()` writes from it, so the value a reader resolves
     * and the value stored cannot be two different things — which is the whole
     * of #177: the settings form rendered the deposit checkbox ticked while the
     * deposit itself read the same missing row as off.
     *
     * A default recorded here belongs to the setting, not to any one reader:
     * the value is written once, here, and read through
     * `getSettingWithDefault()` — no PHP read site states it a second time.
     * (`resources/js/main.js` carries its own fallback for the dashboard
     * column, as the Vue layer cannot read a PHP constant; it is unreachable
     * while `callbackTemplateManagerDisplay()` injects the config on every
     * dashboard view, which is why it does.)
     *
     * Changing a value here does not reach a journal that already has the row:
     * the writers only ever fill a gap, so a real default change needs an
     * upgrade migration. **A setting whose default is expected to change does
     * not belong here at all** — `CODECHECK_ENABLED_CONFIG_VERSIONS` follows
     * the current stable specification, so a row frozen at today's value is
     * exactly what it must not have, and it keeps its default in
     * `CodecheckPlugin::getEnabledConfigVersions()`, its single reader.
     */
    public const CODECHECK_SETTING_DEFAULTS = [
        self::CODECHECK_REGISTER_DEPOSIT_ENABLED => self::CODECHECK_REGISTER_DEPOSIT_ENABLED_DEFAULT,
        // Present until a journal switches it off: each predates its setting,
        // so a journal that never configured one keeps what it already had.
        self::CODECHECK_SHOW_AVAILABILITY_STATEMENT => true,
        self::CODECHECK_SHOW_DASHBOARD_COLUMN => true,
        self::CODECHECK_SHOW_IN_TOC => true,
    ];

    // ORCID integration settings
    public const ORCID_ENABLED = 'orcidEnabled';
    public const ORCID_API_TYPE = 'orcidApiType';
    public const ORCID_CLIENT_ID = 'orcidClientId';
    public const ORCID_CLIENT_SECRET = 'orcidClientSecret';
    public const ORCID_CITY = 'orcidCity';

    // ORCID API type values
    public const ORCID_API_TYPE_SANDBOX = 'memberSandbox';
    public const ORCID_API_TYPE_PRODUCTION = 'member';

    // ORCID API base URLs
    public const ORCID_URL_SANDBOX = 'https://sandbox.orcid.org';
    public const ORCID_URL_PRODUCTION = 'https://orcid.org';
    public const ORCID_API_URL_SANDBOX = 'https://api.sandbox.orcid.org/v3.0';
    public const ORCID_API_URL_PRODUCTION = 'https://api.orcid.org/v3.0';

    // OAuth scope needed to deposit peer-review items
    public const ORCID_ACTIVITIES_SCOPE = '/activities/update';

    // Deposit status values stored in codecheck_orcid_tokens
    public const ORCID_DEPOSIT_STATUS_PENDING = 'pending';
    public const ORCID_DEPOSIT_STATUS_SUCCESS = 'success';
    public const ORCID_DEPOSIT_STATUS_FAILED = 'failed';

    # CODECHECK config file specification versions offered in the metadata form
    public const CODECHECK_ENABLED_CONFIG_VERSIONS = 'codecheckEnabledConfigVersions';

    /**
     * Every config version the plugin knows about, newest first. A journal may
     * narrow this to a subset; see CODECHECK_ENABLED_CONFIG_VERSIONS.
     */
    public const CODECHECK_CONFIG_VERSIONS = [
        'latest',
        '1.0',
    ];

    /**
     * What a journal offers before it has chosen: the current stable
     * specification only. A journal that wants the moving target adds
     * 'latest' in the settings form.
     */
    public const CODECHECK_DEFAULT_CONFIG_VERSIONS = [
        '1.0',
    ];

    /** Where the specification for a given config version is published. */
    public const CODECHECK_CONFIG_SPEC_URL = 'https://codecheck.org.uk/spec/config/';

    /**
     * Builds the specification URL for a config version. Kept here so the PHP
     * side and CodecheckMetadataForm.vue cannot drift apart.
     */
    public static function getConfigSpecUrl(string $version): string
    {
        return self::CODECHECK_CONFIG_SPEC_URL . $version . '/';
    }
}
