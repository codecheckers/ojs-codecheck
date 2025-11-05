<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\facades\Repo;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\Role;

class CodecheckApiHandler
{
    public CodecheckPlugin $plugin;

    public array $allowedRoles = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR
    ];

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Add API route for saving CODECHECK fields
     */
    public function addRoute(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        error_log("CODECHECK API: Adding route to APIHandler");
        
        $apiHandler->addRoute(
            'POST',
            "codecheck/{publication_id}/save",
            function (IlluminateRequest $illuminateRequest): JsonResponse {
                error_log("CODECHECK API: Route called! Publication ID: " . $illuminateRequest->route('publication_id'));
                return $this->saveFields($illuminateRequest);
            },
            'codecheck.save',
            $this->allowedRoles
        );

        error_log("CODECHECK API: Route added successfully");
        
        return Hook::CONTINUE;
    }

    /**
     * Save CODECHECK fields to publication
     */
    private function saveFields(IlluminateRequest $illuminateRequest): JsonResponse
    {
        error_log("CODECHECK API: saveFields called");
        
        $publicationId = (int)$illuminateRequest->route('publication_id');
        error_log("CODECHECK API: Publication ID: " . $publicationId);
        
        $publication = Repo::publication()->get($publicationId);
        
        if (!$publication) {
            error_log("CODECHECK API: Publication not found");
            return response()->json([
                'error' => 'Publication not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $illuminateRequest->input();
        error_log("CODECHECK API: Received params: " . json_encode($params));

        if (!is_array($params)) {
            error_log("CODECHECK API: Invalid parameters");
            return response()->json([
                'error' => 'Invalid parameters',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save fields
        $updates = [];
        $fields = ['codeRepository', 'dataRepository', 'manifestFiles', 'dataAvailabilityStatement'];
        
        foreach ($fields as $field) {
            if (isset($params[$field])) {
                $updates[$field] = $params[$field];
            }
        }

        if (!empty($updates)) {
            error_log("CODECHECK API: Saving " . count($updates) . " fields");
            Repo::publication()->edit($publication, $updates);
            error_log("CODECHECK API: Successfully saved to publication " . $publication->getId());
        }

        // Reload publication
        $publication = Repo::publication()->get($publicationId);

        return response()->json([
            'success' => true,
            'saved' => count($updates),
            'data' => $publication->_data
        ], Response::HTTP_OK);
    }
}