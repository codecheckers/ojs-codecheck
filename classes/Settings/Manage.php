<?php

/**
 * @file classes/Settings/Manage.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class Manage
 *
 * @brief Settings show and saving class for the CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\Settings;

use APP\core\Request;
use APP\plugins\generic\codecheck\classes\Codecheckers\CodecheckerJournalSetup;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\core\JSONMessage;

class Manage
{
    public CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Load a form when the `settings` button is clicked and
     * save the form when the user saves it.
     *
     */
    public function execute(array $args, Request $request): JSONMessage
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':

                // Load the custom form
                $form = new SettingsForm($this->plugin);

                // Fetch the form the first time it loads, before
                // the user has tried to save it
                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }

                // Validate and save the form data
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
                break;

                // Recreates the Codechecker role or the invitation template a
                // manager deleted (#13). PKP's `manage` op checks no CSRF token,
                // so this verb does.
            case 'recreateCodecheckerSetup':
                $context = $request->getContext();
                if (!$context || !$request->isPost() || !$request->checkCSRF()) {
                    return new JSONMessage(false);
                }

                // The status afterwards; the page says so when anything is
                // still missing, which recreateMissing() has logged.
                return new JSONMessage(true, (new CodecheckerJournalSetup($this->plugin))->recreateMissing($context));
        }

        return new JSONMessage(false);
    }
}
