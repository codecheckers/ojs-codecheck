<?php
/**
 * @file classes/Constants.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
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
     * Plugin settings keys
     */
    public const CODECHECK_ENABLED              = 'codecheckEnabled';
    public const CODECHECK_AUTHOR_ANONYMITY      = 'authorAnonymity';
    public const CODECHECK_API_ENDPOINT          = 'codecheckApiEndpoint';
    public const CODECHECK_API_KEY               = 'codecheckApiKey';
    public const CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN = 'githubPersonalAccessToken';
    public const CODECHECK_GITHUB_REGISTER_ORGANIZATION = 'githubRegisterOrganization';
    public const CODECHECK_GITHUB_REGISTER_REPOSITORY   = 'githubRegisterRepository';
    public const CODECHECK_GITHUB_CUSTOM_LABELS         = 'githubCustomLabels';
    public const CODECHECK_MODE                 = 'codecheckMode';

    // ORCID integration settings
    public const ORCID_ENABLED       = 'orcidEnabled';
    public const ORCID_API_TYPE      = 'orcidApiType';
    public const ORCID_CLIENT_ID     = 'orcidClientId';
    public const ORCID_CLIENT_SECRET = 'orcidClientSecret';

    // ORCID API type values
    public const ORCID_API_TYPE_SANDBOX    = 'memberSandbox';
    public const ORCID_API_TYPE_PRODUCTION = 'member';

    // ORCID API base URLs
    public const ORCID_URL_SANDBOX        = 'https://sandbox.orcid.org';
    public const ORCID_URL_PRODUCTION     = 'https://orcid.org';
    public const ORCID_API_URL_SANDBOX    = 'https://api.sandbox.orcid.org/v3.0';
    public const ORCID_API_URL_PRODUCTION = 'https://api.orcid.org/v3.0';

    // OAuth scope needed to deposit peer-review items
    public const ORCID_ACTIVITIES_SCOPE = '/activities/update';

    // Deposit status values stored in codecheck_orcid_tokens
    public const ORCID_DEPOSIT_STATUS_PENDING = 'pending';
    public const ORCID_DEPOSIT_STATUS_SUCCESS = 'success';
    public const ORCID_DEPOSIT_STATUS_FAILED  = 'failed';
}