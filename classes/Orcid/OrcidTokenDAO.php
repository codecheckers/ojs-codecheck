<?php
/**
 * @file classes/Orcid/OrcidTokenDAO.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidTokenDAO
 * @brief Database access for the codecheck_orcid_tokens table.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\plugins\generic\codecheck\classes\Constants;
use Illuminate\Support\Facades\DB;

class OrcidTokenDAO
{
    /**
     * Find one row by submission + ORCID iD. Returns null when not found.
     */
    public function getBySubmissionAndOrcid(int $submissionId, string $orcidId): ?object
    {
        return DB::table('codecheck_orcid_tokens')
            ->where('submission_id', $submissionId)
            ->where('orcid_id', $orcidId)
            ->first();
    }

    /**
     * All token rows for a submission (authorised or not).
     */
    public function getAllBySubmission(int $submissionId): \Illuminate\Support\Collection
    {
        return DB::table('codecheck_orcid_tokens')
            ->where('submission_id', $submissionId)
            ->get();
    }

    /**
     * Only rows that have a stored access token (OAuth completed).
     */
    public function getAuthorizedBySubmission(int $submissionId): \Illuminate\Support\Collection
    {
        return DB::table('codecheck_orcid_tokens')
            ->where('submission_id', $submissionId)
            ->whereNotNull('access_token')
            ->whereNotNull('orcid_id')
            ->get();
    }

    /**
     * Insert or update a token row after a codechecker authorises.
     */
    public function upsertToken(
        int $submissionId,
        string $orcidId,
        string $accessToken,
        ?string $refreshToken = null,
        ?string $tokenExpiresAt = null
    ): void {
        $existing = $this->getBySubmissionAndOrcid($submissionId, $orcidId);

        if ($existing) {
            DB::table('codecheck_orcid_tokens')
                ->where('submission_id', $submissionId)
                ->where('orcid_id', $orcidId)
                ->update([
                    'access_token'     => $accessToken,
                    'refresh_token'    => $refreshToken,
                    'token_expires_at' => $tokenExpiresAt,
                    'deposit_status'   => Constants::ORCID_DEPOSIT_STATUS_PENDING,
                    'put_code'         => null,
                    'error_message'    => null,
                    'updated_at'       => now(),
                ]);
        } else {
            DB::table('codecheck_orcid_tokens')->insert([
                'submission_id'    => $submissionId,
                'orcid_id'         => $orcidId,
                'access_token'     => $accessToken,
                'refresh_token'    => $refreshToken,
                'token_expires_at' => $tokenExpiresAt,
                'deposit_status'   => Constants::ORCID_DEPOSIT_STATUS_PENDING,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Mark deposit successful and store the ORCID put-code.
     */
    public function markSuccess(int $id, string $putCode): void
    {
        DB::table('codecheck_orcid_tokens')
            ->where('id', $id)
            ->update([
                'deposit_status' => Constants::ORCID_DEPOSIT_STATUS_SUCCESS,
                'put_code'       => $putCode,
                'error_message'  => null,
                'deposited_at'   => now(),
                'updated_at'     => now(),
            ]);
    }

    /**
     * Mark deposit failed and store the error for the editor to see.
     */
    public function markFailed(int $id, string $errorMessage): void
    {
        DB::table('codecheck_orcid_tokens')
            ->where('id', $id)
            ->update([
                'deposit_status' => Constants::ORCID_DEPOSIT_STATUS_FAILED,
                'error_message'  => $errorMessage,
                'updated_at'     => now(),
            ]);
    }
}