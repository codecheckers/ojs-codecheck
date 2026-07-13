<?php

/**
 * @file classes/migration/CodecheckMigration.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckMigration
 * @brief Abstract base class for all CODECHECK migrations.
 *        Wraps execution with logging so every migration is traceable.
 *        Subclasses implement runUp() instead of up().
 */

namespace APP\plugins\generic\codecheck\classes\migration;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Database\Migrations\Migration;
use PKP\install\DowngradeNotSupportedException;

abstract class CodecheckMigration extends Migration
{
    /**
     * Implement the migration logic here instead of up().
     * The base class handles logging around this automatically.
     */
    abstract protected function runUp(): void;

    /**
     * Runs the migration with start/end logging.
     * Call this — not runUp() — when chaining migrations.
     */
    public function up(): void
    {
        CodecheckLogger::info('Starting migration: ' . static::class);
        $this->runUp();
        CodecheckLogger::info('Completed migration: ' . static::class);
    }

    /**
     * Downgrade is not supported — dropping tables would destroy journal data.
     * Use the "Clear / Reset DB" button in plugin settings for intentional resets.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}