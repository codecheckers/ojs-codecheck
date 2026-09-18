<?php

/**
 * @file classes/migration/upgrade/I154_MoveCodecheckYamlFlagOntoRepository.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I154_MoveCodecheckYamlFlagOntoRepository
 * @brief Issue #154 — Record which repository holds the `codecheck.yml` on the
 *        repository entry itself instead of as `repoWithCodecheckYaml`, an index
 *        into the list.
 *
 * The index was read by the article page, the publication validator and the
 * register deposit, but nothing kept it in step with the list it indexed: the
 * editorial form splices entries out and the author merge reorders them, so the
 * index could come to name a repository nobody selected — and that URL is what
 * gets deposited into the public register.
 *
 * Idempotent: rows already carrying the flag, or with no index to convert, are
 * left alone.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I154_MoveCodecheckYamlFlagOntoRepository extends CodecheckMigration
{
    protected function runUp(): void
    {
        if (!Schema::hasTable('codecheck_metadata')) {
            return;
        }

        $this->widenRepositoryColumn();

        foreach (DB::table('codecheck_metadata')->get(['submission_id', 'repository']) as $row) {
            $converted = self::convert($row->repository);

            if ($converted === null) {
                continue;
            }

            DB::table('codecheck_metadata')
                ->where('submission_id', $row->submission_id)
                ->update(['repository' => $converted]);

            CodecheckLogger::info(
                "Moved the codecheck.yml flag onto the repository entry for submission {$row->submission_id}."
            );
        }
    }

    /**
     * `repository` holds a JSON list of entries, and was created as
     * varchar(500). Recording which entry holds the `codecheck.yml` on the entry
     * adds roughly 30 bytes each, and four GitHub URLs already exceeded the
     * limit before that — on a strict server the write fails outright, and on a
     * lenient one it is truncated mid-JSON, which reads back as no repositories
     * at all.
     */
    private function widenRepositoryColumn(): void
    {
        $column = DB::selectOne(
            "SHOW COLUMNS FROM codecheck_metadata WHERE Field = 'repository'"
        );

        if ($column === null || stripos($column->Type, 'text') !== false) {
            return;
        }

        DB::statement('ALTER TABLE codecheck_metadata MODIFY repository TEXT NULL');
        CodecheckLogger::info('Widened codecheck_metadata.repository to TEXT.');
    }

    /**
     * The converted JSON, or null when there is nothing to change.
     *
     * Public and static so the conversion can be tested without a database —
     * it is the part that decides which repository keeps the flag.
     */
    public static function convert(?string $repository): ?string
    {
        if ($repository === null || trim($repository) === '') {
            return null;
        }

        $decoded = json_decode($repository, true);

        if (!is_array($decoded) || !array_key_exists('repoWithCodecheckYaml', $decoded)) {
            return null;
        }

        $index = $decoded['repoWithCodecheckYaml'];
        unset($decoded['repoWithCodecheckYaml']);

        if (is_array($decoded['repositories'] ?? null)) {
            foreach ($decoded['repositories'] as $position => $entry) {
                if (is_array($entry)) {
                    $decoded['repositories'][$position]['containsCodecheckYaml'] = $position === $index;
                }
            }
        }

        return json_encode($decoded);
    }
}
