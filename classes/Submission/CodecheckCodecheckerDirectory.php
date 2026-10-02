<?php

/**
 * @file classes/Submission/CodecheckCodecheckerDirectory.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckCodecheckerDirectory
 *
 * @brief The journal's directory of codecheckers (`codecheck_codecheckers`,
 *   #186): who a codechecker is, so the editor picks them rather than retyping
 *   a name, an ORCID iD and a GitHub username for every check.
 *
 * **The directory is a memory, not the record.** A submission's `codecheckers`
 * list keeps its own copy of each entry, because a check records who did it
 * at the time, and an entry edited here later must not rewrite a finished
 * check. The directory fills itself from the editorial saves (`remember()`),
 * so nothing else has to keep it in step.
 *
 * An entry is identified by its ORCID iD, or failing that its GitHub username.
 * One with neither cannot be told apart from a namesake and is not remembered.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CommunityCodecheckers;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CodecheckCodecheckerDirectory
{
    private const TABLE = 'codecheck_codecheckers';

    /**
     * The journal's codecheckers, by name, in the shape a submission stores.
     *
     * @return array<int, array{name: string, orcid: string, github: string}>
     */
    public static function listForContext(int $contextId): array
    {
        // Before the upgrade migration has run there is no directory, which
        // is an empty one rather than an error for the dialog to show.
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('context_id', $contextId)
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'orcid' => (string) ($row->orcid ?? ''),
                'github' => (string) ($row->github_username ?? ''),
            ])
            ->all();
    }

    /**
     * The GitHub username to suggest for an ORCID iD, and where it came from:
     * the journal's own record first, then the CODECHECK community list.
     *
     * @return ?array{github: string, source: string}
     */
    public static function suggestGithubUsername(int $contextId, string $orcid): ?array
    {
        $orcid = CodecheckCodecheckers::normalizeOrcid($orcid);
        if (!CodecheckCodecheckers::isOrcid($orcid)) {
            return null;
        }

        $github = Schema::hasTable(self::TABLE)
            ? DB::table(self::TABLE)->where('context_id', $contextId)->where('orcid', $orcid)->value('github_username')
            : null;
        if (is_string($github) && $github !== '') {
            return ['github' => $github, 'source' => 'directory'];
        }

        $github = CommunityCodecheckers::githubUsernameFor($orcid);

        return $github === null ? null : ['github' => $github, 'source' => 'community'];
    }

    /**
     * Record every codechecker in a saved list that can be identified.
     *
     * Best-effort: the save the list came from has already succeeded, and a
     * directory that missed one entry is a convenience lost, not a record
     * wrong — so a failure is logged and nothing else.
     *
     * @param array<int, array{name: string, orcid: string, github: string}> $codecheckers
     *   as `CodecheckCodecheckers::withNormalizedEntries()` answers it
     */
    public static function rememberAll(int $contextId, array $codecheckers): void
    {
        foreach ($codecheckers as $codechecker) {
            try {
                self::remember($contextId, $codechecker);
            } catch (\Throwable $e) {
                CodecheckLogger::warning('Could not record a codechecker in the journal directory: ' . $e->getMessage());
            }
        }
    }

    /**
     * Insert or update one entry, matched by ORCID iD first and GitHub username
     * second. A value given replaces the one on file; an empty one leaves it.
     *
     * @param array{name: string, orcid: string, github: string} $entry
     */
    private static function remember(int $contextId, array $entry): void
    {
        $orcid = $entry['orcid'];
        $github = $entry['github'];

        if ($entry['name'] === '' || ($orcid === '' && $github === '')) {
            return;
        }

        $byOrcid = $orcid === ''
            ? null
            : DB::table(self::TABLE)->where('context_id', $contextId)->where('orcid', $orcid)->first();
        $byUsername = $github === ''
            ? null
            : DB::table(self::TABLE)->where('context_id', $contextId)->where('github_username', $github)->first();

        // The username's entry is this person's only if it carries no other iD.
        $existing = $byOrcid ?? (($byUsername && (empty($byUsername->orcid) || $orcid === '')) ? $byUsername : null);

        // The username is unique per journal: one on file for somebody else is
        // not moved silently from one person to another.
        if ($byUsername && (!$existing || $byUsername->codechecker_id !== $existing->codechecker_id)) {
            CodecheckLogger::info("Not recording GitHub username {$github} for {$entry['name']}: the journal has it for {$byUsername->name}");
            $github = '';
        }

        $values = ['name' => $entry['name'], 'updated_at' => date('Y-m-d H:i:s')];
        if ($orcid !== '') {
            $values['orcid'] = $orcid;
        }
        if ($github !== '') {
            $values['github_username'] = $github;
        }

        if ($existing) {
            DB::table(self::TABLE)->where('codechecker_id', $existing->codechecker_id)->update($values);
        } elseif ($orcid !== '' || $github !== '') {
            DB::table(self::TABLE)->insert($values + [
                'context_id' => $contextId,
                'created_at' => $values['updated_at'],
            ]);
        }
    }
}
