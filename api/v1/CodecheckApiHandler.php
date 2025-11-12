<?php

namespace APP\plugins\generic\codecheck\api\v1;

use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\core\Request;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueNames;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenue;

class CodecheckApiHandler extends APIHandler
{
    private JsonResponse $response;
    private array $roles;
    private array $endpoints;
    private string $route;
    private Request $request;

    public function __construct(PKPBaseController $controller, Request $request, array &$args)
    {
        $this->response = new JsonResponse();

        $this->roles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR
        ];

        $this->endpoints = [
            'GET' => [
                [
                    'route' => 'getVenueData',
                    'handler' => [$this, 'getVenueData'],
                    'roles' => $this->roles,
                ],
            ],
            'POST' => [
                [
                    'route' => 'reserveIdentifier',
                    'handler' => [$this, 'reserveIdentifier'],
                    'roles' => $this->roles,
                ],
            ],
        ];

        $this->request = $request;

        $this->authorize($request, $args, $this->roles);

        $this->route = $this->getRouteFromRequest();

        $this->serveRequest();

        parent::__construct($controller);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    private function getRouteFromRequest(): ?string
    {
        if (preg_match('#api/v1/codecheck/(.*)#', $this->request->getRequestPath(), $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

    private function serveRequest(): void
    {
        // get the request Method like POST or GET
        $method = $this->request->getRequestMethod();

        error_log("Method: " . $method);

        foreach ($this->endpoints[$method] as $endpoint) {
            if($endpoint['route'] == $this->route) {
                call_user_func($endpoint['handler']);
                return;
            }
        }
    }

    private function getVenueData(): void
    {   
        try {
            $codecheckVenueTypes = new CodecheckVenueTypes();
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], 400);
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], 400);
            return;
        }

        // Serve the getVenueData API route
        $this->response->response([
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
        ], 200);
    }

    public function reserveIdentifier(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $venueType = $postParams["venueType"];
        $venueName = $postParams["venueName"];
        $authorString = $postParams["authorString"];

        // check if they are of type string (If not return success false over the API)
        if(is_string($venueType) && is_string($venueName) && is_string($authorString)) {
            // CODECHECK GitHub Issue Register API parser
            $apiParser = new CodecheckRegisterGithubIssuesApiParser();

            // CODECHECK Register with list of all identifiers in range
            try {
                $certificateIdentifierList = CertificateIdentifierList::fromApi($apiParser);
            } catch (ApiFetchException $ae) {
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            } catch (NoMatchingIssuesFoundException $me) {
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            }

            // print Certificate Identifier list
            $certificateIdentifierList->sortDesc();

            // create the new unique Identifier
            $new_identifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

            // create the CODECHECK Venue with the selected type and name
            $codecheckVenue = new CodecheckVenue();

            $codecheckVenue->setVenueType($venueType);
            $codecheckVenue->setVenueName($venueName);

            // Add the new issue to the CODECHECK GtiHub Register
            try {
                $issueGithubUrl = $apiParser->addIssue($new_identifier, $codecheckVenue->getVenueType(), $codecheckVenue->getVenueName(), $authorString);
            } catch (ApiCreateException $e) {
                // return an error result
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            }

            // return a success result
            $this->response->response([
                'success' => true,
                'identifier' => $new_identifier->toStr(),
                'issueUrl' => $issueGithubUrl,
            ], 200);
            return;
        } else {
            $this->response->response([
                'success'   => false,
                'error'     => "The CODECHECK Venue Type and/ or Venue Names aren't of Type string as expected.",
            ], 400);
            return;
        }
    }
}