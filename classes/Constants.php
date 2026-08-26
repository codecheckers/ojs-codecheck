<?php
/**
 * @file classes/Constants.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Constants
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
    /** The green the badge text has always been rendered in. */
    public const CODECHECK_BADGE_TEXT_COLOR_DEFAULT = '#2d7f3e';

    public const CODECHECK_SHOW_DASHBOARD_COLUMN = 'showDashboardColumn';
    
    # Update Github Register Issue
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS = 'codecheckGithubUpdateFields';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE = 'updateTitle';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY = 'updateBody';
    public const CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS = 'updateStatus';
    
    # Codecheck Publication Validation
    # Codecheck Status
    public const CODECHECK_STATUS = 'codecheckStatus';
    public const CODECHECK_STATUSES_SELECTED = 'codecheckStatusesSelected';
    public const CODECHECK_STATUS_KEYS_SELECTED = 'codecheckStatusKeysSelected';
    # Extended Validation
    public const CODECHECK_PUBLICATION_VALIDATION_EXTENDED = 'codecheckPublicationExtendedValidation';

    # Register Deposit (Issue #10)
    public const CODECHECK_REGISTER_DEPOSIT_ENABLED = 'codecheckRegisterDepositEnabled';

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
