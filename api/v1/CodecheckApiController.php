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
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
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
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getGroupRoutes(): void
    {
        Route::get('status', $this->getCurrentStatus(...))
            ->name('codecheck.status.get');
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
}
