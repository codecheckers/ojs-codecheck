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
     * Basic plugin setting
     */
    public const SETTING_ENABLE_CODECHECK = 'enableCodecheck';

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
    
    /**
     * Plugin settings keys - NEW ADDITIONS
     */
    public const CODECHECK_ENABLED = 'codecheckEnabled';
    public const CODECHECK_AUTHOR_ANONYMITY = 'authorAnonymity';
    public const CODECHECK_API_ENDPOINT = 'codecheckApiEndpoint';
    public const CODECHECK_API_KEY = 'codecheckApiKey';
    public const CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN = 'githubPersonalAccessToken';
    public const CODECHECK_GITHUB_REGISTER_ORGANIZATION = 'githubRegisterOrganization';
    public const CODECHECK_GITHUB_REGISTER_REPOSITORY = 'githubRegisterRepository';
    public const CODECHECK_GITHUB_CUSTOM_LABELS = 'githubCustomLabels';
    public const CODECHECK_MODE = 'codecheckMode';

    public const CODECHECK_BADGE_TYPE = 'codecheckBadgeType';
    public const CODECHECK_BADGE_CUSTOM_URL = 'codecheckBadgeCustomUrl';
    public const CODECHECK_BADGE_HEIGHT = 'codecheckBadgeHeight';

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
}
