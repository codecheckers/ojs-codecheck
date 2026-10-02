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
 *           logged instead, and the migration runs again the next time the
 *           plugin is enabled.
 *         - **It upgrades what exists, and creates nothing.** The install
 *           migration also creates the tables and writes a genre into every
 *           journal, which is for a journal that enables the plugin. An install
 *           that never enabled it has no `codecheck_metadata` table, and an
 *           upgrade of it leaves the database alone.
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
                . ' It runs again when the plugin is enabled.'
            );
        }
    }
}
