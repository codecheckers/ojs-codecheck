<?php
namespace APP\plugins\generic\codecheck\controllers;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueNames;
use PKP\handler\APIHandler;
use PKP\core\APIResponse;
use Slim\Http\Request as SlimRequest;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

// header for AJAX calls
header('Content-Type: application/json');

class CodecheckAPIHandler extends APIHandler
{
    public function __construct()
    {
        $this->_endpoints = [
            'POST' => [
                [
                    'pattern' => 'codecheck/getVenueData',
                    'handler' => [$this, 'getVenueData'],
                    'roles' => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
        ];
        
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new RoleBasedHandlerOperationPolicy(
            $request,
            $roleAssignments,
            ['getVenueData']
        ));
        
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Function that returns JSON encoding of venue types and names
     */
    public function getVenueData(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = Application::get()->getRequest();
        
        $codecheckVenueTypes = new CodecheckVenueTypes();
        $codecheckVenueNames = new CodecheckVenueNames();

        // Your PHP logic here
        $result = [
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
        ];
        
        return $response->withJson($result, 200);
    }
}
