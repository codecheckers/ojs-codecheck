<?php
/**
 * @file api/v1/CodecheckApiController.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckApiController
 * @brief The CODECHECK API.
 *
 * Every CODECHECK API route is served here. It replaced a hand-rolled router
 * that did its own CSRF and role checks and `exit`ed after responding, which
 * meant it could not express "may this user act on *this* submission" — so a
 * journal-wide role was the only gate, and a self-registered reader could read
 * the title and authors of an unpublished submission (Issue #50).
 *
 * What the move buys, none of it written here:
 *
 *   - `SubmissionAccessPolicy` resolves the submission, checks it belongs to
 *     this journal, and scopes each role — a reviewer to the submission they
 *     were assigned to, an author to their own. It has no ROLE_ID_READER branch,
 *     so a reader is refused structurally rather than by us remembering to.
 *   - `ValidateCsrfToken` is global middleware and reads the same
 *     `X-Csrf-Token` header the Vue client already sends, for the state-changing
 *     methods only.
 *   - `->whereNumber('submissionId')` gives the type check the old router lacked.
 *
 * Roles are set per route rather than for the group, the way
 * PKPBackendSubmissionsController does, because they differ: reading admits an
 * author, writing does not, and anything reaching the public register is for a
 * journal editor or an administrator alone (Issue #173).
 *
 * The URLs did not change, so nothing in the Vue layer had to.
 */

namespace APP\plugins\generic\codecheck\api\v1;

use APP\core\Application;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidTokenDAO;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidApiClient;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckYamlValidator;
use Illuminate\Support\Facades\Schema;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckPublicationValidator;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class CodecheckApiController extends PKPBaseController
{
    /**
     * Handler methods that act on one submission.
     *
     * `authorize()` is per controller, not per route, so a blanket
     * SubmissionAccessPolicy would reject the journal-scoped routes — they have
     * no submission to resolve and the policy answers `invalidSubmission`.
     * `getRouteActionName()` returns the PHP method name bound to the route, so
     * this is a list of method names.
     */
    private const SUBMISSION_SCOPED = [
        'getCurrentStatus',
        'getStatusHistory',
        'getMetadata',
        'generateYaml',
        'getOrcidStatus',
        'saveMetadata',
        'loadMetadataFromRepository',
        'updateStatus',
        'depositToOrcid',
    ];

    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /**
     * Serves the same paths the hand-rolled handler does, so nothing the Vue
     * layer calls has to change.
     */
    public function getHandlerPath(): string
    {
        return 'codecheck';
    }

    /**
     * Who may reach a route at all, before the submission is considered.
     *
     * Roles are set per route rather than for the group, the way
     * PKPBackendSubmissionsController does, because they differ: reading is open
     * to an author, writing is not, and anything that reaches the public
     * register is for a journal editor or an administrator alone (Issue #173).
     *
     * ROLE_ID_READER appears nowhere. The hand-rolled handler admitted it to
     * every read endpoint, which is how an account one self-registration old
     * could read the title and authors of an unpublished submission.
     */
    private const READ_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_AUTHOR,
    ];

    /** Writing CODECHECK data: editors, or the reviewer assigned to it. */
    private const WRITE_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_REVIEWER,
    ];

    /** The editorial roles, for things that do not touch one submission. */
    private const EDITOR_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
    ];

    /** Anything published under the journal's name in the public register. */
    private const ADMIN_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
    ];

    public function getRouteGroupMiddleware(): array
    {
        return ['has.user', 'has.context'];
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array(static::getRouteActionName($args[0]), self::SUBMISSION_SCOPED, true)) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getGroupRoutes(): void
    {
        $read = [self::roleAuthorizer(self::READ_ROLES)];
        $write = [self::roleAuthorizer(self::WRITE_ROLES)];

        // Reads, scoped to one submission by SubmissionAccessPolicy.
        Route::get('status', $this->getCurrentStatus(...))
            ->name('codecheck.status.get')->middleware($read);

        Route::get('status/history', $this->getStatusHistory(...))
            ->name('codecheck.status.history')->middleware($read);

        Route::get('metadata', $this->getMetadata(...))
            ->name('codecheck.metadata.get')->middleware($read);

        Route::get('yaml', $this->generateYaml(...))
            ->name('codecheck.yaml.get')->middleware($read);

        Route::get('orcid-status', $this->getOrcidStatus(...))
            ->name('codecheck.orcid.status')->middleware($read);

        // Writes. The role list keeps authors out; the policy keeps a reviewer
        // to the submission they were assigned to.
        Route::post('metadata', $this->saveMetadata(...))
            ->name('codecheck.metadata.save')->middleware($write);

        Route::post('repository', $this->loadMetadataFromRepository(...))
            ->name('codecheck.repository.load')->middleware($write);

        Route::post('status/update', $this->updateStatus(...))
            ->name('codecheck.status.update')->middleware($write);

        Route::post('orcid-deposit', $this->depositToOrcid(...))
            ->name('codecheck.orcid.deposit')->middleware($write);

        // Journal-scoped: no submission, so authorize() adds no
        // SubmissionAccessPolicy for these — see SUBMISSION_SCOPED.
        $editor = [self::roleAuthorizer(self::EDITOR_ROLES)];
        $admin = [self::roleAuthorizer(self::ADMIN_ROLES)];

        Route::get('labels', $this->getCodecheckIssueLabels(...))
            ->name('codecheck.labels')->middleware($editor);

        Route::get('register', $this->getGithubRegisterRepositoryUrl(...))
            ->name('codecheck.register')->middleware($read);

        Route::get('orcid-test', $this->testOrcidSetup(...))
            ->name('codecheck.orcid.test')->middleware($admin);

        Route::post('repository/validate', $this->validateMetadataFromRepository(...))
            ->name('codecheck.repository.validate')->middleware($read);

        Route::post('yaml/validate', $this->validateYamlStructure(...))
            ->name('codecheck.yaml.validate')->middleware($read);

        Route::post('users/roles/validation', $this->validateUserAccessRightsToStatus(...))
            ->name('codecheck.roles.validate')->middleware($read);

        // Anything published under the journal's name in the public CODECHECK
        // register stays with a journal editor or an administrator (#173).
        Route::post('identifier', $this->reserveIdentifier(...))
            ->name('codecheck.identifier.reserve')->middleware($admin);

        Route::post('issue', $this->updateGithubIssue(...))
            ->name('codecheck.issue.update')->middleware($admin);
    }

    /**
     * GET api/v1/codecheck/status?submissionId=N
     *
     * The submission comes from the policy rather than the request: by the time
     * this runs, it has been resolved, confirmed to belong to this journal and
     * checked against what the caller may see.
     */
    public function getCurrentStatus(): \Illuminate\Http\JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        return response()->json([
            'success' => true,
            'statusRecord' => CodecheckStatusHandler::getCurrentStatusData($submission->getId()),
            'allStatuses' => Constants::CODECHECK_STATUSES,
        ], 200);
    }

    /**
     * GET api/v1/codecheck/status/history?submissionId=N
     *
     * An empty history is reported as a failure with a null history rather than
     * as an empty list, and with 400 rather than 404. That is the contract the
     * client and `status-handler.cy.js` already rely on, so it is kept verbatim
     * across the move.
     */
    public function getStatusHistory(): \Illuminate\Http\JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $statusHistory = CodecheckStatusHandler::getStatusDataHistory($submission->getId());

        if (empty($statusHistory)) {
            return response()->json([
                'success' => false,
                'error' => "Currently there is no recorded CODECHECK status history for this submission ID in the OJS database.",
                'statusHistory' => null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'statusHistory' => $statusHistory,
        ], 200);
    }

    /**
     * GET api/v1/codecheck/metadata?submissionId=N
     */
    public function getMetadata(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $result = $this->metadataHandler()->getMetadata($request, $submission->getId());

        if (isset($result['error'])) {
            // A refused payload is a bad request; 404 is for a submission that
            // is not there — though the policy has already ruled that out.
            $status = $result['status'] ?? 404;
            unset($result['status']);

            return response()->json(
                array_merge($result, ['success' => false, 'submissionID' => $submission->getId()]),
                $status
            );
        }

        $result['settings'] = [
            'enabledConfigVersions' => $this->plugin->getEnabledConfigVersions($request->getContext()?->getId()),
        ];

        return response()->json(array_merge($result, ['success' => true]), 200);
    }

    /**
     * GET api/v1/codecheck/yaml?submissionId=N
     */
    public function generateYaml(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $result = $this->metadataHandler()->generateYaml($request, $submission->getId());

        if (isset($result['error'])) {
            $status = $result['status'] ?? 404;
            unset($result['status']);

            return response()->json(
                array_merge($result, ['success' => false, 'submissionID' => $submission->getId()]),
                $status
            );
        }

        return response()->json(array_merge($result, ['success' => true]), 200);
    }

    /**
     * The metadata handler, built per request because it reads the request.
     */
    private function metadataHandler(): CodecheckMetadataHandler
    {
        return new CodecheckMetadataHandler(
            Application::get()->getRequest(),
            new \Github\Client(),
            new CurlApiClient()
        );
    }

    /**
     * GET api/v1/codecheck/orcid-status?submissionId=N
     */
    public function getOrcidStatus(): \Illuminate\Http\JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionId = $submission->getId();

        $metadata = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->first();

        $codecheckerNames = [];
        if ($metadata && $metadata->codecheckers) {
            $decoded = json_decode($metadata->codecheckers, true);
            if (is_array($decoded)) {
                $codecheckerNames = $decoded;
            }
        }

        $tokenDAO  = new OrcidTokenDAO();
        $tokenRows = $tokenDAO->getAllBySubmission($submissionId);

        $tokensByOrcid = [];
        foreach ($tokenRows as $row) {
            if ($row->orcid_id) {
                $tokensByOrcid[$row->orcid_id] = $row;
            }
        }

        $codecheckers = [];

        if (!empty($codecheckerNames)) {
            foreach ($codecheckerNames as $cc) {
                $name     = is_array($cc) ? ($cc['name'] ?? '') : (string) $cc;
                $orcidId  = is_array($cc) ? ($cc['orcid'] ?? $cc['ORCID'] ?? null) : null;
                $tokenRow = $orcidId ? ($tokensByOrcid[$orcidId] ?? null) : null;

                $codecheckers[] = [
                    'name'          => $name,
                    'orcidId'       => $tokenRow->orcid_id ?? null,
                    'depositStatus' => $tokenRow->deposit_status ?? null,
                    'putCode'       => $tokenRow->put_code ?? null,
                    'depositedAt'   => $tokenRow->deposited_at ?? null,
                    'errorMessage'  => $tokenRow->error_message ?? null,
                ];
            }
        } else {
            foreach ($tokenRows as $row) {
                $codecheckers[] = [
                    'name'          => $row->orcid_id ?? 'Unknown',
                    'orcidId'       => $row->orcid_id,
                    'depositStatus' => $row->deposit_status,
                    'putCode'       => $row->put_code,
                    'depositedAt'   => $row->deposited_at,
                    'errorMessage'  => $row->error_message,
                ];
            }
        }

        $journalConfigError = null;
        try {
            $depositService = new OrcidDepositService($this->plugin);
            $depositService->getValidatedJournalInfo(Application::get()->getRequest()->getContext()->getId());
        } catch (\InvalidArgumentException $e) {
            $journalConfigError = $e->getMessage();
        }

        return response()->json([
            'success'            => true,
            'submissionId'       => $submissionId,
            'codecheckers'       => $codecheckers,
            'journalConfigError' => $journalConfigError,
        ], 200);
    }

    /**
     * POST api/v1/codecheck/metadata?submissionId=N
     */
    public function saveMetadata(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $result = $this->metadataHandler()->saveMetadata($request, $submission->getId());

        if (isset($result['error'])) {
            // A refused payload is a bad request; 404 is for a submission that
            // is not there — which the policy has already ruled out.
            $status = $result['status'] ?? 404;
            unset($result['status']);

            return response()->json(
                array_merge($result, ['success' => false, 'submissionID' => $submission->getId()]),
                $status
            );
        }

        return response()->json(array_merge($result, ['success' => true]), 200);
    }

    /**
     * POST api/v1/codecheck/repository?submissionId=N
     *
     * The handler answers with the plugin's own JsonResponse, which echoes and
     * exits; here its payload and code are handed back to the router instead.
     */
    public function loadMetadataFromRepository(): \Illuminate\Http\JsonResponse
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $repository = $postParams['repository'] ?? null;

        if (!is_string($repository)) {
            return response()->json([
                'success' => false,
                'error' => 'The provided Repository must be of the type string.',
            ], 400);
        }

        $response = $this->metadataHandler()->importMetadataFromRepository($repository);

        return response()->json($response->getPayloadArray(), $response->getHttpResponseCode());
    }

    /**
     * POST api/v1/codecheck/orcid-deposit?submissionId=N
     *
     * SubmissionAccessPolicy has already established that the caller may act on
     * this submission. What it cannot express is kept here: an editor may
     * deposit for every codechecker, a reviewer only for their own record
     * (Issue #173, #175).
     */
    public function depositToOrcid(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionId = $submission->getId();

        if (!$this->plugin->getSetting($context->getId(), Constants::ORCID_ENABLED)) {
            return response()->json([
                'success' => false,
                'error' => 'ORCID deposition is not enabled for this journal.',
            ], 400);
        }

        $user = $request->getUser();
        $isEditor = CodecheckSubmissionAccess::isEditor($user, $context->getId());

        // The per-row button names an ORCID iD; "Deposit to all" sends none. The
        // endpoint used to ignore it either way and deposit for every authorised
        // codechecker, so the two buttons did the same thing and a re-deposit
        // re-PUT someone else's item (#175).
        $postParams  = json_decode(file_get_contents('php://input'), true) ?? [];
        $requested   = $postParams['orcidId'] ?? null;
        $onlyOrcidId = is_string($requested) && $requested !== '' ? $requested : null;

        if (!$isEditor) {
            // SubmissionAccessPolicy has already refused a reviewer who is not
            // assigned here — depositToOrcid is in SUBMISSION_SCOPED — so this is
            // the second lock rather than the one holding the door. It stays
            // because dropping this route from that list would otherwise open
            // the deposit silently (#175).
            if (!CodecheckSubmissionAccess::isAssignedReviewer($user, $submissionId)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Only an editor, or a reviewer assigned to this submission, may deposit to ORCID.',
                ], 403);
            }

            // A reviewer deposits their own record whatever the payload asked
            // for, so a crafted request cannot deposit on a colleague's behalf.
            $onlyOrcidId = $user?->getOrcid();

            if (empty($onlyOrcidId)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'No ORCID iD is recorded for your account, so there is nothing to deposit to.',
                ], 400);
            }
        }

        try {
            $depositService = new OrcidDepositService($this->plugin);
            $results = $depositService->depositForSubmission($submissionId, $onlyOrcidId);
        } catch (\Throwable $e) {
            CodecheckLogger::error('ORCID depositToOrcid API error: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'results' => $results], 200);
    }

    /**
     * POST api/v1/codecheck/status/update?submissionId=N
     *
     * `userId` in the body is a **mode**, not an actor: -1 asks for the
     * automatic update, which the handler records as the system. The actor is
     * always the caller. Kept exactly as the hand-rolled handler had it,
     * including the 400-with-payload shapes `status-handler.cy.js` pins.
     */
    public function updateStatus(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionId = $submission->getId();

        $postParams = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $postParams['status'] ?? null;
        $requestedMode = $postParams['userId'] ?? null;

        if (!is_string($status) || !is_int($requestedMode)) {
            return response()->json([
                'success' => false,
                'statusRecord' => ['status' => $status, 'userId' => $requestedMode],
                'allStatuses' => Constants::CODECHECK_STATUSES,
                'error' => 'Bad Request: Please provide a Status form of string and a User ID in the form of int.',
            ], 400);
        }

        if ($requestedMode === -1) {
            $submissionMetadata = $this->metadataHandler()->getMetadata($request, $submissionId);

            if (array_key_exists('error', $submissionMetadata)) {
                return response()->json([
                    'success' => false,
                    'error' => $submissionMetadata['error'],
                    'allStatuses' => Constants::CODECHECK_STATUSES,
                ], 400);
            }

            $statusUpdate = CodecheckStatusHandler::automaticStatusUpdate($submissionMetadata);

            if (empty($statusUpdate)) {
                return response()->json([
                    'success' => false,
                    'statusRecord' => $statusUpdate,
                    'allStatuses' => Constants::CODECHECK_STATUSES,
                    'error' => "Status doesn't need to be automatically updated.",
                ], 400);
            }

            return response()->json([
                'success' => true,
                'statusRecord' => $statusUpdate,
                'allStatuses' => Constants::CODECHECK_STATUSES,
            ], 200);
        }

        $userId = (int) $request->getUser()->getId();

        if (!in_array($status, Constants::CODECHECK_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'allStatuses' => Constants::CODECHECK_STATUSES,
                'error' => 'Bad Request: Unknown CODECHECK status.',
            ], 400);
        }

        $statusUpdate = CodecheckStatusHandler::updateStatus($submissionId, $status, $userId);

        if ($statusUpdate == false) {
            return response()->json([
                'success' => true,
                'statusRecord' => ['status' => $status, 'userId' => $userId],
                'allStatuses' => Constants::CODECHECK_STATUSES,
                'error' => 'Inserting into the CODECHECK Status Database went wrong.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'statusRecord' => $statusUpdate,
            'allStatuses' => Constants::CODECHECK_STATUSES,
        ], 200);
    }


    /**
     * Gets the Issue Labels of the CODECHECK API
     * 
     * @return void
     */
    public function getCodecheckIssueLabels(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $dbLabelsOutdated = false;

        try {
            $issueLabelsLastUpdated = strtotime($this->getIssueLabelsLastUpdated());
        } catch (\Throwable $e) {
            return response()->json([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], $e->getCode());
        }
        $now = strtotime(date('Y-m-d H:i:s'));
        $timeDifferenceInHours = round(($now - $issueLabelsLastUpdated) / 3600);

        if($timeDifferenceInHours > 6) {
            $dbLabelsOutdated = true;
        }

        $codecheckIssueLabels = CodecheckIssueLabels::fromDB();

        if($dbLabelsOutdated) {
            try {
                $codecheckIssueLabels = CodecheckIssueLabels::fromApi("https://codecheck.org.uk/register/venues/index.json");
            } catch (\Throwable $e) {
                return response()->json([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], $e->getCode());
            }
        }

        // add the github custom labels specified in the plugin settings form to the Label Array returned back to the user
        $context = $request->getContext();
        $githubCustomLabels = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_CUSTOM_LABELS);
        $codecheckIssueLabels->addLabelArray($githubCustomLabels);

        $codecheckStatuses = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED);
        CodecheckLogger::debug('Selected status keys: ' . json_encode($codecheckStatuses));

        // Serve the getCodecheckIssueLabels API route
        return response()->json([
            'success' => true,
            'labels' => $codecheckIssueLabels->get()->toArray(),
        ], 200);
    }

    public function getGithubRegisterRepositoryUrl(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $githubRegisterRepositoryOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepositoryRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        return response()->json([
            'success' => true,
            'url' => "github.com/$githubRegisterRepositoryOrganization/$githubRegisterRepositoryRepository",
        ], 200);
    }

    /**
     * GET api/v1/codecheck/orcid-test
     *
     * Tests the ORCID setup without writing any data:
     * 1. Validates required journal metadata
     * 2. Makes an authenticated token request to verify credentials
     */
    public function testOrcidSetup(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $context   = $request->getContext();
        $contextId = $context->getId();

        try {
            $depositService = new OrcidDepositService($this->plugin);
            $depositService->getValidatedJournalInfo($contextId);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'step'    => 'metadata',
                'error'   => $e->getMessage(),
            ], 400);
        }

        $clientId     = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);
        $apiType      = $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE)
                        ?? Constants::ORCID_API_TYPE_SANDBOX;

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'success' => false,
                'step'    => 'credentials',
                'error'   => __('plugins.generic.codecheck.orcid.test.error.noCredentials'),
            ], 400);
        }

        try {
            $client = new OrcidApiClient($clientId, $clientSecret, $apiType);
            $client->getClientCredentialsToken();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'step'    => 'credentials',
                'error'   => __('plugins.generic.codecheck.orcid.test.error.credentialsFailed') . ' ' . $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => __('plugins.generic.codecheck.orcid.test.success'),
        ], 200);
    }

    /**
     * This reserves a new Identifier
     * 
     * @return void
     */
    public function reserveIdentifier(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $postParams = json_decode(file_get_contents('php://input'), true);
        
        $parameterValidationError = IdentifierParameterValidator::forReserveIdentifier($postParams);

        if ($parameterValidationError !== null) {
            return response()->json([
                'success'   => false,
                'error'     => $parameterValidationError,
            ], 400);
        }

        $issueLabelArray = $postParams["issue"]["labelsSelected"];
        $submissionData = $postParams["submission"];
        $articleTitle = $submissionData["title"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];
        $reserveIdentifierMode = $postParams['reserveIdentifierMode'];

        $context = $request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        $authorString = $this->getAuthorStringBasedOnAuthorAnonymity();

        if (!in_array($reserveIdentifierMode, ['api', 'newIssueUrl', 'linkExistingIdentifier'])) {
            return response()->json([
                'success' => false,
                'error'   => "An unexpected mode for the reservation of the Certificate Identifier was given: " . $reserveIdentifierMode,
            ], 400);
        }

        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository, // Name of the GitHub Repository for the Register
            $this->metadataHandler()->getSubmissionId(), // Submission ID
            $context, // The Journal Object of the Submission
        );

        // CODECHECK Register with list of all identifiers in range
        try {
            if($reserveIdentifierMode == 'linkExistingIdentifier') {
                $identifierStr = $postParams["identifier"];
                $certificateIdentifierList = CertificateIdentifierList::fromApiWithIdentifier(
                    $codecheckGithubRegisterApiClient,
                    CertificateIdentifier::fromStr($identifierStr)
                );
                $this->linkExistingIdentifier($identifierStr, $certificateIdentifierList);
            }

            $certificateIdentifierList = CertificateIdentifierList::fromApi(
                $codecheckGithubRegisterApiClient,
                true
            );
            // sort Certificate Identifier list descending
            $certificateIdentifierList->sortDesc();
            // create the new unique Identifier
            $newIdentifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);
            // create the CODECHECK Issue Labels with the selected issue labels
            $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);

            if($reserveIdentifierMode == 'api') {
                $issue = $this->reserveIdentifierWithApi(
                    $codecheckGithubRegisterApiClient,
                    $newIdentifier,
                    $codecheckIssueLabels,
                    $articleTitle,
                    $authorString,
                    $codecheckers,
                    $repositories
                );
                $issueGithubUrl = $issue['html_url'];
                $issueNumber = $issue['number'];
            } else if($reserveIdentifierMode == 'newIssueUrl') {
                $issueGithubUrl = $this->reserveIdentifierWithNewIssueUrl(
                    $githubRegisterOrganization,
                    $githubRegisterRepository,
                    $newIdentifier,
                    $codecheckIssueLabels,
                    $articleTitle,
                    $authorString,
                    $codecheckers,
                    $repositories
                );
                $issueNumber = null;
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'success' => true,
            'identifier' => $newIdentifier->toStr(),
            'issueUrl' => $issueGithubUrl,
            'issueNumber' => $issueNumber,
        ], 200);
    }

    public function updateGithubIssue(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $postParams = json_decode(file_get_contents('php://input'), true);

        $parameterValidationError = IdentifierParameterValidator::forGithubIssueUpdate($postParams);

        if ($parameterValidationError !== null) {
            return response()->json([
                'success'   => false,
                'error'     => $parameterValidationError,
            ], 400);
        }

        $issue = $postParams['issue'];
        $issueLabelArray = $postParams["issue"]["labelsSelected"];
        $submissionData = $postParams["submission"];
        $articleTitle = $submissionData["title"];
        $identifierStr = $postParams["identifier"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];

        $context = $request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);
        $updateInformation = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);

        $authorString = $this->getAuthorStringBasedOnAuthorAnonymity();

        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository, // Name of the GitHub Repository for the Register
            $this->metadataHandler()->getSubmissionId(), // Submission ID
            $context, // The Journal Object of the Submission
        );

        $identifier = CertificateIdentifier::fromStr($identifierStr);
        $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);
        try {
            $updatedIssue = $codecheckGithubRegisterApiClient->updateIssue(
                $updateInformation,
                $issue['number'],
                $identifier,
                $codecheckIssueLabels,
                $articleTitle,
                $authorString,
                $codecheckers,
                $repositories
            );

            return response()->json([
                'success' => true,
                'identifier' => $identifier->toStr(),
                'issueUrl' => $updatedIssue['html_url'],
                'issueNumber' => $updatedIssue['number'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'identifier' => $identifier->toStr(),
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    /**
     * This function validates if the contents of the CODECHECK metadata form are equal to the contents in the provided repositories `codecheck.yml` file
     * 
     * @return void
     */
    public function validateMetadataFromRepository(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $postParams = json_decode(file_get_contents('php://input'), true);
        $repository = $postParams["repository"];

        if(!is_string($repository)) {
            return response()->json([
                'success' => false,
                'error' => 'The provided Repository must be of the type string.'
            ], 400);
        }

        $publicationValidator = new CodecheckPublicationValidator($this->plugin);
        $publicationValidator->validateMetadataFromRepository($repository);
        $errors = $publicationValidator->getErrors();

        if(count($errors) > 0) {
            return response()->json([
                'success' => false,
                'error' => implode(' ,', $errors),
            ], 500);
        }
        return response()->json([
            'success' => true,
        ], 200);
    }

    /**
     * This function validates the structure of a Yaml file
     * 
     * @return void
     */
    public function validateYamlStructure(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $postParams = json_decode(file_get_contents('php://input'), true);
        $yamlContent = $postParams["yaml"];

        $yamlValidator = new CodecheckYamlValidator($yamlContent);

        try {
            $yamlValidator->validateYaml();
        } catch (\Throwable $e) {
            CodecheckLogger::error('YAML Parse Exception: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $e->getCode());
        }

        CodecheckLogger::info('The generated YAML content is structurally valid');

        return response()->json([
            'success' => true,
        ], 200);
    }

    /**
     * Whether the caller may set the CODECHECK status.
     *
     * The answer comes from the session. It used to be read out of the request
     * body — the caller sent its own `user.roles` and the server looked for 16
     * in the list — so the question "may I?" was answered by whoever asked. The
     * client still posts `pkp.currentUser`; that body is now ignored rather than
     * trusted, and an honest client gets the same answer as before.
     *
     * This only shows and hides a control. The enforcement is
     * assertMayWriteMetadata() on the endpoints themselves.
     */
    public function validateUserAccessRightsToStatus(): \Illuminate\Http\JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();

        $allowedToAccess = $user
            && $context
            && $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId());

        return response()->json([
            'success' => true,
            'userAllowedToAccess' => $allowedToAccess,
        ], 200);
    }

    private function getAuthorStringBasedOnAuthorAnonymity(): string
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $authorString = $postParams['submission']['authorString'] ?? null;

        $context = Application::get()->getRequest()->getContext();
        $isAuthorStringEnabled = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_AUTHOR_ANONYMITY);

        // Anonymous authors, or no author string given, means no names in the
        // register issue. That is an empty string, not null: the issue builder
        // turns an empty one into "New CODECHECK", while null was a TypeError in
        // reserveIdentifierWithApi() — so reserving an identifier failed outright
        // whenever a journal kept its authors anonymous, which is the default.
        if (!$isAuthorStringEnabled || !is_string($authorString)) {
            return '';
        }

        return $authorString;
    }

    /**
     * This reserves a new Identifier with the GitHub New Issue Url
     * 
     * @return string
     */
    private function reserveIdentifierWithNewIssueUrl(
        string $githubRegisterOrganization,
        string $githubRegisterRepository,
        CertificateIdentifier $identifier,
        CodecheckIssueLabels $issueLabels,
        string $articleTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): string
    {
        // Not a parameter and not a property: this method was extracted for #50
        // without it, so every call fatalled on "Call to a member function
        // getContext() on null". `reserveIdentifier()` gets it the same way.
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $journalName = $context?->getLocalizedName() ?? 'Unknown Journal';
        $updateInformation = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);
        $codecheckIssue = new CodecheckGithubRegisterIssue(
            $githubRegisterOrganization,
            $githubRegisterRepository,
            $identifier,
            $issueLabels,
            $articleTitle,
            $journalName,
            $authorString,
            $this->metadataHandler()->getSubmissionId(),
            $codecheckers,
            $repositories,
            $updateInformation
        );

        return $codecheckIssue->getNewIssueUrl();
    }

    /**
     * This reserves a new Identifier with the GitHub API
     * 
     * @return array
     */
    private function reserveIdentifierWithApi(
        CodecheckGithubRegisterApiClient $codecheckGithubRegisterApiClient,
        CertificateIdentifier $identifier,
        CodecheckIssueLabels $issueLabels,
        string $articleTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): array
    {
        $updateInformation = $this->plugin->getSetting($request->getContext()->getId(), Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);
        // Add the new issue to the CODECHECK GtiHub Register
        $issue = $codecheckGithubRegisterApiClient->addIssue(
            $identifier,
            $issueLabels,
            $articleTitle,
            $authorString,
            $codecheckers,
            $repositories,
            $updateInformation
        );

        return $issue;
    }

    private function linkExistingIdentifier(
        string $identifierStr,
        CertificateIdentifierList $certificateIdentifierList
    ) {
        $title =  "a | " . $identifierStr;
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        if($rawIdentifier == null) {
            return response()->json([
                'success'   => false,
                'identifier' => $identifierStr,
                'error'     => "The identifier: " . $identifierStr . " isn't matching the required format (YYYY-NNN or YYYY-NNN/YYYY-NNN).",
            ], 400);
        }
        $identifier = CertificateIdentifier::fromStr($rawIdentifier);
        $issue = $certificateIdentifierList->getIssueInformationByIdentifier($identifier);
        if(!is_array($issue) || !is_string($issue['issueUrl']) || !is_int($issue['issueNumber'])) {
            return response()->json([
                'success'   => false,
                'identifier' => $identifierStr,
                'error'     => "The certificate with the Identifier: ". $identifierStr . " doesn't exist in the GitHub Register.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'identifier' => $identifier->toStr(),
            'issueUrl' => $issue['issueUrl'],
            'issueNumber' => $issue['issueNumber'],
        ], 200);
    }

    /**
     * This function gets when the Codecheck Issue Labels where last updated
     * 
     * @return string The Date when the issues where last updated
     */
    private function getIssueLabelsLastUpdated(): string
    {
        if (!Schema::hasTable('codecheck_issue_labels')) {
            // The issue labels table doesn't exist
            CodecheckLogger::error("CODECHECK API: The Issue Label table doesn't exist");
            throw new Exception("The table 'codecheck_issue_labels' doesn't exist.", 500);
        }

        $labelsLastUpdated = DB::table('codecheck_issue_labels')
            ->select(['labels_last_updated'])
            ->first();

        CodecheckLogger::debug("Labels: " . print_r(DB::table('codecheck_issue_labels')->select(['*'])->get()->toArray(), true));

        // If Labels weren't updated yet, set last updated to earliest date possible, so they will definitely get updated
        $labelsLastUpdated = $labelsLastUpdated->labels_last_updated ?? date('Y-m-d H:i:s', 0);

        CodecheckLogger::debug("CODECHECK API: Codecheck Issues Last Updated: " . json_encode($labelsLastUpdated));
        
        return $labelsLastUpdated;
    }
}
