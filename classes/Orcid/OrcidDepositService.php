<?php
/**
 * @file classes/Orcid/OrcidDepositService.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidDepositService
 * @brief Orchestrates depositing CODECHECK activity to ORCID profiles.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
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
     *
     * @throws \InvalidArgumentException if required journal metadata is missing
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
            CodecheckLogger::error('ORCID deposit skipped: no credentials configured.');
            return [];
        }

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return [];
        }

        $journal = $this->loadJournalInfo($contextId);

        // Validate required journal metadata before attempting any deposit.
        // Throws InvalidArgumentException with a clear message if missing.
        $this->validateJournalInfo($journal);

        $client = new OrcidApiClient($clientId, $clientSecret, $apiType);
        $meta   = $this->loadCodecheckMeta($submissionId);

        // Ensure the group-id exists in ORCID before depositing peer-reviews.
        $issn = !empty($journal['issn']) ? trim($journal['issn']) : '';
        if (!empty($issn)) {
            $groupId   = 'issn:' . $issn;
            $groupName = !empty($journal['name']) ? $journal['name'] : $journal['publisherName'];
        } else {
            $groupId   = 'orcid-generated:codecheck-ojs';
            $groupName = !empty($journal['name']) ? $journal['name'] : $journal['publisherName'];
        }

        try {
            $client->createGroupId($groupId, $groupName, 'journal');
        } catch (\Throwable $e) {
            // Log but do not block — the group-id may already exist
            CodecheckLogger::debug('ORCID group-id registration note: ' . $e->getMessage());
        }

        $tokenRows = $this->tokenDAO->getAuthorizedBySubmission($submissionId);
        $results   = [];

        foreach ($tokenRows as $row) {
            $results[] = $this->depositOneCodechecker($client, $submission, $row, $meta, $journal);
        }

        return $results;
    }

    /**
     * Validate that all required journal metadata for ORCID deposition is present.
     * OJS does not have a publisher city field, so only name and country are required.
     *
     * @throws \InvalidArgumentException with a descriptive message listing missing fields
     */
    public function validateJournalInfo(array $journal): void
    {
        $publisherName = !empty($journal['publisherName'])
            ? trim($journal['publisherName'])
            : (!empty($journal['name']) ? trim($journal['name']) : '');

        $country = !empty($journal['publisherCountry']) ? trim($journal['publisherCountry']) : '';

        $missing = [];
        if (empty($publisherName)) $missing[] = 'Publisher Name (Journal Settings → Masthead → Publisher)';
        if (empty($country))       $missing[] = 'Country (Journal Settings → Masthead → Country)';

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'ORCID deposition requires the following journal metadata to be configured: ' .
                implode(', ', $missing) . '.'
            );
        }
    }

    /**
     * Load journal info and validate it — convenience method for API handler.
     */
    public function getValidatedJournalInfo(int $contextId): array
    {
        $journal = $this->loadJournalInfo($contextId);
        $this->validateJournalInfo($journal);
        return $journal;
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
            CodecheckLogger::error('ORCID deposit failed for ' . $orcidId . ': ' . $error);
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
            'publisherCity'    => '', // OJS has no publisher city field
            'publisherCountry' => $context->getData('country') ?? '',
            'ringgoldId'       => $context->getData('ringgoldId') ?? null,
        ];
    }
}