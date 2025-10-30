<?php
namespace APP\plugins\generic\codecheck\controllers;

use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueNames;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenue;

// header for AJAX calls
define('INDEX_FILE_STARTED', true);
header('Content-Type: application/json');

class CodecheckAPIHandler
{
    private $apiRoute;
    private $params = [];

    public function __construct(string $route, string $jsonParams)
    {
        $this->apiRoute = $route;
        error_log($jsonParams);
        $this->params = json_decode($jsonParams, true);
        error_log(print_r($this->params, true));
    }

    public function serveApiRoute(): void
    {
        switch ($this->apiRoute) {
            case 'getVenueData':
                $this->getVenueData();
                break;
            
            case 'reserveIdentifier':
                // get the Params that where sent from the JS script via AJAX
                $venueType = $this->params["venueType"];
                $venueName = $this->params["venueName"];

                // check if they are of type string (If not return success false over the API)
                if(is_string($venueType) && is_string($venueName)) {
                    $this->reserveIdentifier($venueType, $venueName);
                } else {
                    $this->returnApiError(400, 'Bad Request', "The CODECHECK Venue Type and/ or Venue Names aren't of Type string as expected.");
                }
                
                break;

            default:
                error_log("[CODECHECK API Hanlder] -> at Route: $this->apiRoute -> 404: Requested unknown API Route");
                break;
        }
    }

    public function getVenueData(): void
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

    public function reserveIdentifier(string $venueType, string $venueName): void
    {
        // CODECHECK GitHub Issue Register API parser
        $apiParser = new CodecheckRegisterGithubIssuesApiParser();

        // CODECHECK Register with list of all identifiers in range
        $certificateIdentifierList = CertificateIdentifierList::fromApi($apiParser);

        // print Certificate Identifier list
        $certificateIdentifierList->sortDesc();

        // create the new unique Identifier
        $new_identifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

        // create the CODECHECK Venue with the selected type and name
        $codecheckVenue = new CodecheckVenue();

        $codecheckVenue->setVenueType($venueType);
        $codecheckVenue->setVenueName($venueName);

        // Add the new issue to the CODECHECK GtiHub Register
        $issueGithubUrl = $apiParser->addIssue($new_identifier, $codecheckVenue->getVenueType(), $codecheckVenue->getVenueName());

        // return a success result
        $result = [
            'success' => true,
            'identifier' => $new_identifier->toStr(),
            'issueUrl' => $issueGithubUrl,
        ];

        echo json_encode($result);
        exit();
    }

    public function returnApiError(int $errorCode, string $errorDescription, string $errorMessage): void
    {
        $result = [
            'success' => false,
            'error' => 'CODECHECK API Error [' . $errorCode . ': ' . $errorDescription .'] ' . $errorMessage,
        ];

        echo json_encode($result);
        exit();
    }
}
