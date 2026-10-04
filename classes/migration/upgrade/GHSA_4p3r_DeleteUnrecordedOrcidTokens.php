<?php

/**
 * @file classes/migration/upgrade/GHSA_4p3r_DeleteUnrecordedOrcidTokens.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class GHSA_4p3r_DeleteUnrecordedOrcidTokens
 *
 * @brief Advisory GHSA-4p3r-qgp4-g74r — Deletes the ORCID tokens of accounts
 *        that are not a recorded codechecker of their submission.
 *
 * An ORCID account is credited for a check only when its iD is on the
 * submission's codechecker list, and the callback no longer stores one that is
 * not. A token stored before that rule — by anyone who could reach the
 * unauthenticated flow, for any account — stays in the table with a live
 * access token, out of sight of the ORCID panel, and is credited the moment its
 * iD is recorded. So it is deleted.
 *
 * **This runs on every enable**, like every upgrade step, so it also deletes
 * the token of a codechecker an editor has since taken off the record. That is
 * the same rule: such a token is never deposited for, and re-adding the
 * codechecker asks them to connect their account again. A row whose iD a
 * legacy entry holds — spelled `ORCID`, or failing the check digit — goes too,
 * because the deposit reads that entry as having no iD.
 *
 * What is deleted is logged, put-code included: an item already deposited
 * stays on the ORCID record, and the log line is what an administrator has to
 * find it by. Rows without an iD (an authorisation never completed) are left
 * alone; they credit nobody.
 */

namespace APP\plugins\generic\codecheck\classes\migration\upgrade;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GHSA_4p3r_DeleteUnrecordedOrcidTokens extends CodecheckMigration
{
    protected function runUp(): void
    {
        if (!Schema::hasTable('codecheck_orcid_tokens') || !Schema::hasTable('codecheck_metadata')) {
            return;
        }

        $tokens = DB::table('codecheck_orcid_tokens')
            ->whereNotNull('orcid_id')
            ->get(['id', 'submission_id', 'orcid_id', 'put_code']);

        if ($tokens->isEmpty()) {
            return;
        }

        $codecheckers = DB::table('codecheck_metadata')
            ->whereIn('submission_id', $tokens->pluck('submission_id')->unique()->all())
            ->pluck('codecheckers', 'submission_id')
            ->all();

        $unrecorded = self::unrecordedTokens($tokens->all(), $codecheckers);

        foreach ($unrecorded as $token) {
            CodecheckLogger::warning(sprintf(
                'Deleting the ORCID token of %s for submission %d: not a recorded codechecker (GHSA-4p3r-qgp4-g74r)%s.',
                $token->orcid_id,
                $token->submission_id,
                $token->put_code ? ", its deposited item {$token->put_code} stays on the ORCID record" : ''
            ));
        }

        if ($unrecorded !== []) {
            DB::table('codecheck_orcid_tokens')
                ->whereIn('id', array_map(fn ($token) => $token->id, $unrecorded))
                ->delete();
        }
    }

    /**
     * The token rows whose iD is not recorded for their submission's
     * codecheckers. Public static so the rule is testable without a database.
     *
     * @param array $tokens `stdClass` rows with `submission_id` and `orcid_id`
     * @param array $codecheckersBySubmission the stored `codecheckers` column,
     *   keyed by submission id; a submission missing here has none recorded
     *
     * @return array the rows to delete, in the order given
     */
    public static function unrecordedTokens(array $tokens, array $codecheckersBySubmission): array
    {
        return array_values(array_filter($tokens, function ($token) use ($codecheckersBySubmission) {
            $recorded = CodecheckCodecheckers::recordedOrcids($codecheckersBySubmission[$token->submission_id] ?? null);

            return !in_array(CodecheckCodecheckers::normalizeOrcid($token->orcid_id), $recorded, true);
        }));
    }
}
