<?php

namespace APP\plugins\generic\codecheck\api\v1;

use PKP\core\APIResponse;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Slim\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;

// header for AJAX calls
define('INDEX_FILE_STARTED', true);
header('Content-Type: application/json');

class CodecheckApiHandler extends APIHandler
{
    public function __construct(PKPBaseController $controller)
    {
        $roles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR
        ];

        $this->_handlerPath = 'test';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'testGet'],
                    'roles' => $roles,
                ],
            ],
        ];

        parent::__construct($controller);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Example GET endpoint to test the API route.
     */
    public function testGet()
    {
        echo json_encode([
            'success'   => true,
            'message'   => 'Codecheck API test endpoint working!',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}