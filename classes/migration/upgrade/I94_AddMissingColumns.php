<?php

/**
 * @file classes/migration/upgrade/I94_AddMissingColumns.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class I94_AddMissingColumns
 *
 * @brief Issue #94 — Add columns that were introduced after the initial release
 *        to installations that already have the table. All checks are idempotent.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I94_AddMissingColumns extends CodecheckMigration
{
    protected function runUp(): void
    {
        // Nothing to do if the table doesn't exist — install migration handles creation
        if (!Schema::hasTable('codecheck_metadata')) {
            return;
        }

        Schema::table('codecheck_metadata', function (Blueprint $table) {
            // `issue` column was added after initial release to track GitHub issue data
            if (!Schema::hasColumn('codecheck_metadata', 'issue')) {
                $table->string('issue', 500)
                    ->default(json_encode(['url' => null, 'number' => null, 'labelsSelected' => []]))
                    ->after('certificate');
            }
        });
    }
}
