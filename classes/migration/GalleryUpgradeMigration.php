<?php

/**
 * @file classes/migration/GalleryUpgradeMigration.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class GalleryUpgradeMigration
 *
 * @brief What `upgrade.xml` runs when the plugin is upgraded from the Plugin
 *        Gallery: the install migration, brought up to date on an install that
 *        already has it, and never allowed to fail the upgrade.
 *
 *        Two properties are the reason this class exists rather than the install
 *        migration being named in `upgrade.xml` directly:
 *
 *         - **A failure must not remove the plugin.** `PluginHelper::upgradePlugin()`
 *           deletes the plugin's directory when anything throws, and the
 *           installer catches `Exception` only, so a `TypeError` or an undefined
 *           method — the old plugin object is still in memory and the migration
 *           calls it — would leave a journal with no plugin at all. A failure is
 *           logged instead. **Nothing retries it on its own**: the install
 *           migration runs again when the plugin is enabled, or when OJS itself
 *           is upgraded, so a journal that keeps the plugin enabled stays on the
 *           old schema until an administrator disables and enables it again,
 *           and the log line is the only sign.
 *         - **It leaves an install that never enabled the plugin alone.** Such
 *           an install has no `codecheck_metadata` table, and upgrading the
 *           plugin there must not create one. Where the plugin has been enabled
 *           in any journal this is the whole install migration, which also adds
 *           the `codecheck.yml` genre to every journal of the site, as enabling
 *           it does; the table is the only signal of "enabled somewhere" that
 *           the upgrade has.
 */

namespace APP\plugins\generic\codecheck\classes\migration;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\install\CodecheckSchemaMigration;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GalleryUpgradeMigration extends CodecheckMigration
{
    protected function runUp(): void
    {
        if (!Schema::hasTable('codecheck_metadata')) {
            CodecheckLogger::info('The plugin was never enabled here; nothing to upgrade.');

            return;
        }

        try {
            (new CodecheckSchemaMigration())->up();
        } catch (Throwable $e) {
            CodecheckLogger::error(
                'The upgrade could not bring the database up to date: ' . $e->getMessage()
                . ' Disable and enable the plugin to run it again.'
            );
        }
    }
}
