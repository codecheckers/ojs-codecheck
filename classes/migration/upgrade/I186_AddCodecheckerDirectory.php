<?php

/**
 * @file classes/migration/upgrade/I186_AddCodecheckerDirectory.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class I186_AddCodecheckerDirectory
 *
 * @brief Issue #186 — the journal's directory of codecheckers: name, ORCID iD
 *        and GitHub username, so the register issue can be assigned and an
 *        editor need not retype who a codechecker is for every check.
 *
 * Nothing is converted. A submission's `codecheckers` list keeps its own copy
 * of each entry, and the directory fills from the saves that follow.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I186_AddCodecheckerDirectory extends CodecheckMigration
{
    protected function runUp(): void
    {
        if (Schema::hasTable('codecheck_codecheckers')) {
            return;
        }

        Schema::create('codecheck_codecheckers', function (Blueprint $table) {
            $table->bigIncrements('codechecker_id');
            $table->bigInteger('context_id');
            $table->string('name', 255);
            // Bare form, as `CodecheckCodecheckers::normalizeOrcid()` stores it.
            $table->string('orcid', 19)->nullable();
            // GitHub allows 39 characters.
            $table->string('github_username', 39)->nullable();
            $table->timestamps();
            // Each unique key admits any number of NULLs, so an entry known by
            // only one of the two identifiers is fine.
            $table->unique(['context_id', 'orcid'], 'codecheck_codecheckers_orcid');
            $table->unique(['context_id', 'github_username'], 'codecheck_codecheckers_github');
        });
    }
}
