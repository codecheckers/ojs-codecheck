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
define('INDEX_FILE_STARTED', true);
header('Content-Type: application/json');

class CodecheckAPIHandler
{
    private $apiRoute;

    function __construct(string $route)
    {
        $this->apiRoute = $route;
    }

    public function serveApiRoute()
    {
        switch ($this->apiRoute) {
            case 'getVenueData':
                $this->getVenueData();
                break;
            
            default:
                error_log("[CODECHECK API Hanlder] -> at Route: $this->apiRoute -> 404: Requested unknown API Route");
                break;
        }
    }

    public static function getVenueData()
    {
        $codecheckVenueTypes = new CodecheckVenueTypes();
        $codecheckVenueNames = new CodecheckVenueNames();

        $result = [
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
        ];

        echo json_encode($result);
        exit();
    }
}
