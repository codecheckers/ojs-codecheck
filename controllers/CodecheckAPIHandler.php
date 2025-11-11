<?php
namespace APP\plugins\generic\codecheck\controllers;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
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
                $authorString = $this->params["authorString"];

                // check if they are of type string (If not return success false over the API)
                if(is_string($venueType) && is_string($venueName) && is_string($authorString)) {
                    $this->reserveIdentifier($venueType, $venueName, $authorString);
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
        try {
            $codecheckVenueTypes = new CodecheckVenueTypes();
        } catch (ApiFetchException $e) {
            $this->returnApiError(400, "Bad Request", $e->getMessage());
            exit();
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (ApiFetchException $e) {
            $this->returnApiError(400, "Bad Request", $e->getMessage());
            exit();
            return;
        }

        $result = [
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
        ];

        echo json_encode($result);
        exit();
    }

    public function reserveIdentifier(string $venueType, string $venueName, string $authorString): void
    {
        // CODECHECK GitHub Issue Register API parser
        $apiParser = new CodecheckRegisterGithubIssuesApiParser();

        // CODECHECK Register with list of all identifiers in range
        try {
            $certificateIdentifierList = CertificateIdentifierList::fromApi($apiParser);
        } catch (ApiFetchException $ae) {
            $this->returnApiError(400, "Bad Request", $ae->getMessage());
            return;
        } catch (NoMatchingIssuesFoundException $me) {
            $this->returnApiError(400, "Bad Request", $me->getMessage());
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
            $this->returnApiError(400, "Bad Request", $e->getMessage());
            exit();
            return;
        }

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
