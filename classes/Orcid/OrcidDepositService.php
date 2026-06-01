<?php
/**
 * @file classes/Orcid/OrcidDepositService.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidDepositService
 * @brief Orchestrates depositing CODECHECK activity to ORCID profiles.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;

class OrcidDepositService
{
    private CodecheckPlugin $plugin;
    private OrcidTokenDAO $tokenDAO;
    private PeerReviewPayloadBuilder $payloadBuilder;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin         = $plugin;
        $this->tokenDAO       = new OrcidTokenDAO();
        $this->payloadBuilder = new PeerReviewPayloadBuilder();
    }

    /**
     * Deposit for every authorised codechecker of a submission.
     */
    public function depositForSubmission(int $submissionId): array
    {
        $context   = Application::get()->getRequest()->getContext();
        $contextId = $context->getId();

        $clientId     = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);
        $apiType      = $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE)
                        ?? Constants::ORCID_API_TYPE_SANDBOX;

        if (empty($clientId) || empty($clientSecret)) {
            error_log('[CODECHECK ORCID] Deposit skipped: no credentials configured.');
            return [];
        }

        $client     = new OrcidApiClient($clientId, $clientSecret, $apiType);
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return [];
        }

        $meta    = $this->loadCodecheckMeta($submissionId);
        $journal = $this->loadJournalInfo($contextId);

        // Ensure the group-id exists in ORCID before depositing peer-reviews.
        // ORCID requires group-ids to be pre-registered using client credentials.
        // This is a one-time operation per journal — it is safe to call every
        // time as ORCID returns 409 (already exists) which we handle gracefully.
        $issn = !empty($journal['issn']) ? trim($journal['issn']) : '';
        if (!empty($issn)) {
            $groupId   = 'issn:' . $issn;
            $groupName = !empty($journal['name']) ? $journal['name'] : 'CODECHECK Journal';
            try {
                $client->createGroupId($groupId, $groupName, 'journal');
            } catch (\Throwable $e) {
                // Log but do not block — the ISSN group-id may already exist
                // in ORCID's global registry (ISSNs are pre-loaded by ORCID)
                error_log('[CODECHECK ORCID] Group-id registration note: ' . $e->getMessage());
            }
        }
        // For orcid-generated group-ids we must create them ourselves
        else {
            $groupId   = 'orcid-generated:codecheck-ojs';
            $groupName = !empty($journal['name']) ? $journal['name'] : 'CODECHECK Journal';
            try {
                $client->createGroupId($groupId, $groupName, 'journal');
            } catch (\Throwable $e) {
                error_log('[CODECHECK ORCID] Group-id registration note: ' . $e->getMessage());
            }
        }

        $tokenRows = $this->tokenDAO->getAuthorizedBySubmission($submissionId);
        $results   = [];

        foreach ($tokenRows as $row) {
            $results[] = $this->depositOneCodechecker($client, $submission, $row, $meta, $journal);
        }

        return $results;
    }

    private function depositOneCodechecker(
        OrcidApiClient $client,
        $submission,
        object $row,
        array $meta,
        array $journal
    ): array {
        $orcidId     = $row->orcid_id;
        $accessToken = $row->access_token;
        $putCode     = $row->put_code;

        try {
            $payload = $this->payloadBuilder->build($submission, $orcidId, $meta, $journal);

            if ($putCode) {
                $client->putPeerReview($orcidId, $accessToken, $putCode, $payload);
                $this->tokenDAO->markSuccess((int) $row->id, $putCode);
                return ['orcidId' => $orcidId, 'status' => 'success', 'putCode' => $putCode, 'action' => 'updated'];
            } else {
                $newPutCode = $client->postPeerReview($orcidId, $accessToken, $payload);
                $this->tokenDAO->markSuccess((int) $row->id, $newPutCode);
                return ['orcidId' => $orcidId, 'status' => 'success', 'putCode' => $newPutCode, 'action' => 'created'];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            error_log('[CODECHECK ORCID] Deposit failed for ' . $orcidId . ': ' . $error);
            $this->tokenDAO->markFailed((int) $row->id, $error);
            return ['orcidId' => $orcidId, 'status' => 'failed', 'error' => $error];
        }
    }

    private function loadCodecheckMeta(int $submissionId): array
    {
        $row = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();
        return $row ? (array) $row : [];
    }

    private function loadJournalInfo(int $contextId): array
    {
        $context = Application::get()->getRequest()->getContext();
        return [
            'name'             => $context->getLocalizedName() ?? '',
            'issn'             => $context->getData('onlineIssn') ?? $context->getData('printIssn') ?? '',
            'publisherName'    => $context->getData('publisherInstitution') ?? '',
            'publisherCity'    => $context->getData('location') ?? '',
            'publisherCountry' => $context->getData('country') ?? 'XX',
            'ringgoldId'       => $context->getData('ringgoldId') ?? null,
        ];
    }
}