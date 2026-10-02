<?php

/**
 * @file classes/migration/upgrade/I185_MoveRecordsToConfigSpec2.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class I185_MoveRecordsToConfigSpec2
 *
 * @brief Issue #185 — Records a check against version 2.0 of the CODECHECK configuration
 *        specification, the only version the plugin implements. `latest` was
 *        stored until the specification's `latest` moved from 1.0 to 2.0, and
 *        1.0 was dropped before any journal ran the plugin in production, so
 *        every record on either moves to 2.0 — as does the column default,
 *        which was `latest`.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I185_MoveRecordsToConfigSpec2 extends CodecheckMigration
{
    protected function runUp(): void
    {
        // Nothing to do if the table doesn't exist — install migration handles
        // creation — or before I93 has renamed the column this reads.
        if (!Schema::hasTable('codecheck_metadata') || !Schema::hasColumn('codecheck_metadata', 'spec_version')) {
            return;
        }

        // Spelled out rather than read from `Constants`: this runs on every
        // enable, and a later release changing the default must not have this
        // migration quietly move records and the column to it. That change is a
        // migration of its own.
        $default = '2.0';

        // Only the two versions this release retires. It runs on every enable, so
        // it must not touch a version a later release adds.
        $moved = DB::table('codecheck_metadata')
            ->whereIn('spec_version', ['latest', '1.0'])
            ->update(['spec_version' => $default]);
        if ($moved > 0) {
            CodecheckLogger::info("Moved {$moved} CODECHECK records to config specification {$default}.");
        }

        // Altering the column rebuilds it, so only when it still carries a
        // default this release retires. Anything else is a later release's
        // choice, and this runs on every enable.
        $column = collect(Schema::getColumns('codecheck_metadata'))->firstWhere('name', 'spec_version');
        $reported = $column['default'] ?? null;
        if (self::defaultIs($reported, 'latest') || self::defaultIs($reported, '1.0')) {
            Schema::table('codecheck_metadata', function (Blueprint $table) use ($default) {
                $table->string('spec_version', 50)->default($default)->change();
            });
        }
    }

    /**
     * Whether the default a column reports is $default. MariaDB reports a
     * string default quoted (`'2.0'`), MySQL does not (`2.0`) and PostgreSQL
     * adds a cast (`'2.0'::character varying`), so none of that is part of the
     * answer; public static so that is testable without a database.
     */
    public static function defaultIs(?string $reported, string $default): bool
    {
        return trim((string) preg_replace('/::.*$/', '', (string) $reported), "'") === $default;
    }
}
