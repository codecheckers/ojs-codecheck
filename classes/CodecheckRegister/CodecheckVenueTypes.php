<?php
namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;

class CodecheckVenueTypes
{
    private UniqueArray $uniqueArray;

    /**
     * Initializes a new List of all CODECHECK Venue Types
     */
    function __construct(?CodecheckApiClient $apiClient = null)
    {
        // Initialize unique Array
        $this->uniqueArray = new UniqueArray();
        // Intialize API caller
        $codecheckApiClient = $apiClient ?? new CodecheckApiClient();
        // fetch CODECHECK Type data
        try {
            $codecheckApiClient->fetch("https://codecheck.org.uk/register/venues/index.json");
        } catch (CurlInitException $curlInitException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log($curlInitException);
            throw $curlInitException;
        } catch (CurlReadException $curlReadException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log($curlReadException);
            throw $curlReadException;
        }
        // get json Data from API Caller
        $data = $codecheckApiClient->getData();

        foreach($data as $venue) {
            // insert every type (as this is a unique Array each Type will only occur once)
            $type = $venue["Venue type"];
            // Add every venue type to the unique Array
            $this->uniqueArray->add($type);
        }
    }

    /**
     * Gets the List of all CODECHECK Venue Types
     * 
     * @return UniqueArray Returns all CODECHECK Venue Types inside a `UniqueArray`
     */
    public function get(): UniqueArray
    {
        return $this->uniqueArray;
    }
}