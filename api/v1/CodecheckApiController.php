<?php
/**
 * @file api/v1/CodecheckApiController.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckApiController
 * @brief The CODECHECK API as a PKP controller (Issue #50, stage 0).
 *
 * A spike. It serves one endpoint — `GET status` — so that the four assumptions
 * the migration rests on can be checked against a running instance before the
 * rest of `CodecheckApiHandler` follows:
 *
 *   1. the URL does not change: getHandlerPath() returns 'codecheck', and
 *      APIRouter::matchesPluginHandlerPath() matches whatever follows /api/v1/;
 *   2. the CSRF contract does not change: PKP's ValidateCsrfToken reads
 *      HTTP_X_CSRF_TOKEN and compares it with the session token, which is what
 *      the Vue client already sends;
 *   3. ?submissionId= keeps working: PKPBaseController::getParameter() falls back
 *      to the query string, so SubmissionAccessPolicy resolves it without the id
 *      moving into the path;
 *   4. SubmissionAccessPolicy gives per-submission scoping — and has no
 *      ROLE_ID_READER branch, so it closes the read hole structurally.
 *
 * While this exists, CodecheckPlugin::setupAPIHandler() skips the `status` route
 * so the old handler does not claim it first: the Dispatcher::dispatch hook fires
 * before routing and that handler exits. That skip is scaffolding and goes away
 * with the old handler.
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
     * The roles that may reach these routes at all. This is the journal-wide
     * half; which submission they may reach is SubmissionAccessPolicy's job.
     *
     * ROLE_ID_READER is deliberately absent. The hand-rolled handler admitted it
     * to every read endpoint, which is how an account one self-registration old
     * could read the title and authors of an unpublished submission.
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
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
        Route::get('status', $this->getCurrentStatus(...))
            ->name('codecheck.status.get');

        Route::get('status/history', $this->getStatusHistory(...))
            ->name('codecheck.status.history');

        Route::get('metadata', $this->getMetadata(...))
            ->name('codecheck.metadata.get');

        Route::get('yaml', $this->generateYaml(...))
            ->name('codecheck.yaml.get');

        Route::get('orcid-status', $this->getOrcidStatus(...))
            ->name('codecheck.orcid.status');
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
}
