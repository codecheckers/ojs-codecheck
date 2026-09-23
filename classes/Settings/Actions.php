<?php

/**
 * @file classes/Settings/Actions.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class Actions
 *
 * @brief Settings actions class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\Settings;

use APP\core\Request;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class Actions
{
    /**  */
    public CodecheckPlugin $plugin;

    /**  */
    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Add a settings action to the plugin's entry in the plugins list.
     *
     */
    public function execute(Request $request, array $actionArgs, array $parentActions): array
    {
        // Only add the settings action when the plugin is enabled
        if (!$this->plugin->getEnabled()) {
            return $parentActions;
        }

        // Create a LinkAction that will make a request to the
        // plugin's `manage` method with the `settings` verb.
        $router = $request->getRouter();

        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->plugin->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->plugin->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        // Add the LinkAction to the existing actions.
        // Make it the first action to be consistent with
        // other plugins.
        array_unshift($parentActions, $linkAction);

        return $parentActions;
    }
}
