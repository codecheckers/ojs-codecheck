<?php

/**
 * @file classes/Orcid/OrcidDepositService.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class OrcidDepositService
 *
 * @brief Orchestrates depositing CODECHECK activity to ORCID profiles.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;

class OrcidDepositService
{
    /**
     * `<context>|<api type>|<group id>` keys this process has registered.
     *
     * @see ensureGroupIdRegistered()
     */
    private static array $groupIdRegistered = [];

    private CodecheckPlugin $plugin;
    private OrcidTokenDAO $tokenDAO;
    private PeerReviewPayloadBuilder $payloadBuilder;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
        $this->tokenDAO = new OrcidTokenDAO();
        $this->payloadBuilder = new PeerReviewPayloadBuilder();
    }

    /**
     * Deposit for every authorised codechecker of a submission.
     *
     * @param string|null $onlyOrcidId Deposit only this ORCID record. A reviewer
     *   may deposit their own codechecking activity and nobody else's (#173).
     *
     * @throws \InvalidArgumentException if required journal metadata is missing
     */
    public function depositForSubmission(int $submissionId, ?string $onlyOrcidId = null): array
    {
        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            CodecheckLogger::error('ORCID deposit skipped: no journal context on this request.');
            return [['status' => 'failed', 'error' => __('plugins.generic.codecheck.orcid.test.error.noCredentials')]];
        }
        $contextId = $context->getId();

        $clientId = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);
        $apiType = $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE)
                        ?? Constants::ORCID_API_TYPE_SANDBOX;

        if (empty($clientId) || empty($clientSecret)) {
            CodecheckLogger::error('ORCID deposit skipped: no credentials configured.');
            return [['status' => 'failed', 'error' => __('plugins.generic.codecheck.orcid.test.error.noCredentials')]];
        }

        // One narrow column rather than `loadCodecheckMeta()`'s `SELECT *`: this
        // is the common path for a journal with ORCID on, and the metadata row
        // carries the manifest, repositories, summary and report as TEXT. The
        // whole row is read below, once there is something to deposit.
        $certificate = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->value('certificate');

        // No row at all is a different thing from a row without a certificate,
        // and saying "no certificate yet" for a submission that has no CODECHECK
        // record sends the reader looking for the wrong thing.
        if ($certificate === null && !$this->hasCodecheckRecord($submissionId)) {
            CodecheckLogger::info("ORCID deposit skipped for submission {$submissionId}: no CODECHECK record.");

            return [[
                'status' => 'skipped',
                'error' => __('plugins.generic.codecheck.orcid.deposit.skipped.noRecord'),
            ]];
        }

        // Without a certificate there is no identifier that distinguishes this
        // check from any other. Depositing anyway used the literal
        // "codecheck:unknown" for every one of them, and ORCID de-duplicates on
        // that value: the second deposit was rejected as a duplicate, the
        // put-code of somebody else's item was read out of the error, and the
        // result was reported as a success. Skipping says what is true (#175).
        if (empty($certificate)) {
            CodecheckLogger::info("ORCID deposit skipped for submission {$submissionId}: no certificate identifier yet.");

            return [[
                'status' => 'skipped',
                'error' => __('plugins.generic.codecheck.orcid.deposit.skipped.noCertificate'),
            ]];
        }

        $authorized = $this->tokenDAO->getAuthorizedBySubmission($submissionId);
        $targets = self::depositTargets($authorized, $onlyOrcidId);

        // Answered a bare `[]`, which the workflow panel renders as nothing at
        // all — it shows only failures — so the button appeared to do nothing.
        // The two reasons are also different things: nobody has authorised, or
        // the person asking has not.
        if ($targets === []) {
            $nobodyAuthorized = count($authorized) === 0;
            CodecheckLogger::info(sprintf(
                'ORCID deposit skipped for submission %d: %s.',
                $submissionId,
                $nobodyAuthorized ? 'no codechecker has authorised one' : 'the requested ORCID iD has not authorised one'
            ));

            return [[
                'status' => 'skipped',
                'error' => __($nobodyAuthorized
                    ? 'plugins.generic.codecheck.orcid.deposit.skipped.noCodechecker'
                    : 'plugins.generic.codecheck.orcid.deposit.skipped.notAuthorised'),
            ]];
        }

        // Only now is the submission fetched, the whole metadata row read and
        // the masthead loaded: all three were done before the checks above, so
        // a publish that deposits nothing paid for them anyway (#182).
        $meta = $this->loadCodecheckMeta($submissionId);

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            CodecheckLogger::error('ORCID deposit skipped: submission ' . $submissionId . ' not found.');
            return [['status' => 'failed', 'error' => __('plugins.generic.codecheck.orcid.auth.error.submissionNotFound')]];
        }

        $journal = $this->loadJournalInfo($contextId);

        // Throws InvalidArgumentException naming what the masthead is missing.
        $this->validateJournalInfo($journal);

        // Constructing the client is inert — it only stores the credentials and
        // picks the sandbox or production host.
        return $this->deposit(
            $targets,
            $submission,
            $meta,
            $journal,
            new OrcidApiClient($clientId, $clientSecret, $apiType),
            $contextId,
            $apiType
        );
    }

    /**
     * The half that talks to ORCID.
     *
     * Every request the deposit makes is reached from here, and nothing reaches
     * here without a target (#182): registering the group id begins by posting
     * the journal's client id and secret to `/oauth/token`, and it used to run
     * before the certificate and codechecker checks — so publishing an opted-in
     * submission that could deposit nothing still made two requests, once per
     * article when an issue was published.
     *
     * That property is upheld by this being the only place that uses the
     * client, which is a convention rather than something the language
     * enforces. **No test covers it**: `depositForSubmission()` wants a journal
     * context, `Repo::submission()` and the database before it gets here, and
     * the live ORCID test that would exercise it is blocked on Member API
     * credentials (`dev/live-orcid-tests.md`). An edit that calls
     * `ensureGroupIdRegistered()` from anywhere else reintroduces #182 silently.
     *
     * @param array $targets rows from `depositTargets()`, never empty
     *
     * @return array one result per target, preceded by one row if the group id
     *   could not be registered
     */
    private function deposit(
        array $targets,
        Submission $submission,
        array $meta,
        array $journal,
        OrcidApiClient $client,
        int $contextId,
        string $apiType
    ): array {
        $results = [];

        // Reported rather than only logged: every deposit below may fail for
        // this one reason, and ORCID's own complaint names the group rather
        // than the registration, so the editor is left with N failures and no
        // cause. `error_log()` is not somewhere an editor can look.
        $registrationError = $this->ensureGroupIdRegistered($client, $contextId, $apiType, $journal);
        if ($registrationError !== null) {
            $results[] = [
                'status' => 'failed',
                'error' => __('plugins.generic.codecheck.orcid.deposit.failed.groupId'),
            ];
        }

        foreach ($targets as $row) {
            $results[] = $this->depositOneCodechecker($client, $submission, $row, $meta, $journal);
        }

        return $results;
    }

    /**
     * Whether a CODECHECK record exists for a submission at all.
     *
     * Only asked when the certificate came back null, to tell "no record" from
     * "a record with no certificate yet" — they need different answers, and a
     * `value()` cannot distinguish them.
     */
    private function hasCodecheckRecord(int $submissionId): bool
    {
        return DB::table('codecheck_metadata')->where('submission_id', $submissionId)->exists();
    }

    /**
     * Which authorised codecheckers this run deposits for.
     *
     * Pure, and public so it can be pinned without a database or ORCID: an
     * empty answer is what keeps a publish from contacting ORCID at all (#182).
     *
     * **`iterable` because the DAO answers a `Collection`**, not an array, and
     * this is also handed plain arrays by its tests. Narrowing it to `array`
     * would raise a TypeError on every deposit, which the publish hook would
     * swallow as "ORCID deposit exception".
     *
     * @param iterable $tokenRows `stdClass` rows as
     *   `OrcidTokenDAO::getAuthorizedBySubmission()` answers them; each carries
     *   an `orcid_id`, the query having excluded the rest
     * @param string|null $onlyOrcidId deposit only this record — a reviewer may
     *   deposit their own codechecking activity and nobody else's (#173)
     *
     * @return array the rows to deposit for, in the order given
     */
    public static function depositTargets(iterable $tokenRows, ?string $onlyOrcidId = null): array
    {
        $rows = is_array($tokenRows) ? $tokenRows : iterator_to_array($tokenRows, false);

        if ($onlyOrcidId === null) {
            return array_values($rows);
        }

        // **Both sides are normalised, because they arrive in different
        // shapes.** `codecheck_orcid_tokens.orcid_id` is the bare
        // `0000-…` iD ORCID answers with, and a 20-character column that could
        // not hold more; the API hands this `$user->getOrcid()` for anyone who
        // is not an editor, which OJS stores as the full `https://orcid.org/…`
        // URI. Compared as given, a reviewer's own deposit matched nothing and
        // the button did nothing at all, silently.
        $wanted = CodecheckCodecheckers::normalizeOrcid($onlyOrcidId);

        return array_values(array_filter(
            $rows,
            fn ($row) => CodecheckCodecheckers::normalizeOrcid($row->orcid_id ?? null) === $wanted
        ));
    }

    /**
     * Registers the journal's peer-review group id with ORCID, once per journal.
     *
     * A group id belongs to the journal, not to a submission, so asking per
     * submission was redundant. The memo makes it once for as long as the
     * process lives — under mod_php or FPM that is one request, which is what
     * `IssueGridHandler::publishIssue()` needs, since it publishes every
     * scheduled article of an issue in a single request.
     *
     * **Only a resolved attempt is remembered.** Memoising a thrown one as well
     * looked like a saving — a bad secret would otherwise cost two requests per
     * article — but it means one timeout on the first article of a twelve
     * article issue silently stops the other eleven from registering, and each
     * then deposits against a group that does not exist. A retry per article
     * is the lesser cost.
     *
     * **The key carries the journal and the API type**, not the group id alone:
     * every journal with no ISSN shares the `orcid-generated:codecheck-ojs`
     * fallback, so in any process that spans two journals — a queue worker,
     * a test run — the first would otherwise suppress the second, possibly on
     * the other ORCID host.
     *
     * Nothing across processes: a stored flag that is wrong, because the record
     * was deleted at ORCID or the journal's ISSN changed, is worse than a
     * request ORCID answers 409 to, since nothing would ever try again.
     *
     * @return string|null what went wrong, for the caller to report
     */
    private function ensureGroupIdRegistered(
        OrcidApiClient $client,
        int $contextId,
        string $apiType,
        array $journal
    ): ?string {
        // The same derivation the payload uses to cite the group, so the two
        // cannot come to name different things.
        $groupId = PeerReviewPayloadBuilder::groupIdFor($journal);
        $key = $contextId . '|' . $apiType . '|' . $groupId;

        if (isset(self::$groupIdRegistered[$key])) {
            return null;
        }

        // Name-then-publisher, which is the order this call has always used.
        // Note that `buildConveningOrganization()` prefers the publisher, so a
        // journal with both set registers a group under one name and cites the
        // other; that is left alone deliberately, because ORCID answers 409 for
        // a group that exists and would never take a correction.
        $groupName = trim((string) ($journal['name'] ?? '')) ?: trim((string) ($journal['publisherName'] ?? ''));

        try {
            $client->createGroupId($groupId, $groupName, 'journal');
            self::$groupIdRegistered[$key] = true;

            return null;
        } catch (\Throwable $e) {
            // `createGroupId()` already answers null for the 409 that says the
            // group is there, so reaching here is a real failure. Not fatal —
            // a deposit may still succeed — but a warning rather than debug,
            // because it is only reached when one is about to be attempted.
            CodecheckLogger::warning('ORCID group-id registration failed: ' . $e->getMessage());

            return $e->getMessage();
        }
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
        if (empty($publisherName)) {
            $missing[] = 'Publisher Name (Journal Settings → Masthead → Publisher)';
        }
        if (empty($country)) {
            $missing[] = 'Country (Journal Settings → Masthead → Country)';
        }

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
        $orcidId = $row->orcid_id;
        $accessToken = $row->access_token;
        $putCode = $row->put_code;

        try {
            $payload = $this->payloadBuilder->build($submission, $orcidId, $meta, $journal);

            if ($putCode) {
                $client->putPeerReview($orcidId, $accessToken, $putCode, $payload);
                $this->tokenDAO->markSuccess($row->id, $putCode);
                return ['orcidId' => $orcidId, 'status' => 'success', 'putCode' => $putCode, 'action' => 'updated'];
            } else {
                $newPutCode = $client->postPeerReview($orcidId, $accessToken, $payload);
                $this->tokenDAO->markSuccess($row->id, $newPutCode);
                return ['orcidId' => $orcidId, 'status' => 'success', 'putCode' => $newPutCode, 'action' => 'created'];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            CodecheckLogger::error('ORCID deposit failed for ' . $orcidId . ': ' . $error);
            $this->tokenDAO->markFailed($row->id, $error);
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
            'name' => $context->getLocalizedName() ?? '',
            'issn' => $context->getData('onlineIssn') ?? $context->getData('printIssn') ?? '',
            'publisherName' => $context->getData('publisherInstitution') ?? '',
            'publisherCity' => $this->plugin->getSetting($contextId, Constants::ORCID_CITY) ?? '',
            'publisherCountry' => $context->getData('country') ?? '',
            'ringgoldId' => $context->getData('ringgoldId') ?? null,
        ];
    }
}
