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
     * Plugin settings keys - NEW ADDITIONS
     */
    public const CODECHECK_ENABLED = 'enabled';
    public const CODECHECK_API_ENDPOINT = 'codecheckApiEndpoint';
    public const CODECHECK_API_KEY = 'codecheckApiKey';
}