<?php

/**
 * @file classes/migration/upgrade/I93_RenameVersionToSpecVersion.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class I93_RenameVersionToSpecVersion
 *
 * @brief Issue #93 — `codecheck_metadata.version` holds the CODECHECK
 *        configuration specification the check was recorded against, not a
 *        version of the record, of the plugin or of the submission. It is
 *        `spec_version` now, which is what it has always meant.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I93_RenameVersionToSpecVersion extends CodecheckMigration
{
    protected function runUp(): void
    {
        // Nothing to do if the table doesn't exist — install migration handles creation
        if (!Schema::hasTable('codecheck_metadata')) {
            return;
        }

        // A fresh install already creates the column under its new name, and
        // an upgrade that has run once has nothing left to rename.
        if (!Schema::hasColumn('codecheck_metadata', 'version')) {
            return;
        }

        // Both at once is not a state this migration produces, and renaming
        // into an occupied name would fail. Leave the data alone and say so:
        // whichever column the readers use, they use one of them.
        if (Schema::hasColumn('codecheck_metadata', 'spec_version')) {
            CodecheckLogger::warning(
                'codecheck_metadata has both `version` and `spec_version`; leaving both alone. '
                . 'The plugin reads `spec_version`; move any value across by hand and drop `version`.'
            );

            return;
        }

        Schema::table('codecheck_metadata', function (Blueprint $table) {
            $table->renameColumn('version', 'spec_version');
        });
    }
}
