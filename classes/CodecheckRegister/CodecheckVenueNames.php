<?php
namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;

class CodecheckVenueNames
{
    private UniqueArray $uniqueArray;

    /**
     * Initializes a new List of all CODECHECK Venue Names
     */
    function __construct()
    {
        // Initialize unique Array
        $this->uniqueArray = new UniqueArray();

        // Intialize API caller
        $codecheckApiClient = new CodecheckApiClient();
        // fetch CODECHECK Type data
        try {
            $codecheckApiClient->fetch("https://codecheck.org.uk/register/venues/index.json");
        } catch (CurlInitException $curlInitException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log($curlInitException);
            throw $curlInitException;
            return;
        } catch (CurlReadException $curlReadException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log($curlReadException);
            throw $curlReadException;
            return;
        }
        // get json Data from API Caller
        $data = $codecheckApiClient->getData();

        // find all venue Types
        // TODO: Remove this once the actualy Codecheck API contains the labels/ Venue Names to fetch
        $codecheckVenueTypes = new CodecheckVenueTypes();

        foreach($data as $venue) {
            $label = $venue["Issue label"];
            // If a Label is already a Venue Type it can't also be a venue Name
            // Therefore this Label has to be skipped
            if($codecheckVenueTypes->get()->contains($label) || $label == "id assigned" || $label == "development") {
                continue;
            }
            // add Label to Venue Names
            $this->uniqueArray->add($label);
        }
    }

    /**
     * Gets the List of all CODECHECK Venue Names
     * 
     * @return UniqueArray Returns all CODECHECK Venue Names inside a `UniqueArray`
     */
    public function get(): UniqueArray
    {
        return $this->uniqueArray;
    }
}