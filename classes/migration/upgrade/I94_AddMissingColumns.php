<?php

/**
 * @file classes/migration/upgrade/I94_AddMissingColumns.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I94_AddMissingColumns
 * @brief Issue #94 — Add columns that were introduced after the initial release
 *        to installations that already have the table. All checks are idempotent.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class I94_AddMissingColumns extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}