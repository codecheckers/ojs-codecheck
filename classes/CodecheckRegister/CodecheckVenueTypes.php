<?php
namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\JsonApiCaller;

class CodecheckVenueTypes
{
    private UniqueArray $uniqueArray;

    /**
     * Initializes a new List of all CODECHECK Venue Types
     */
    function __construct()
    {
        // Initialize unique Array
        $this->uniqueArray = new UniqueArray();
        // Intialize API caller
        $jsonApiCaller = new JsonApiCaller("https://codecheck.org.uk/register/venues/index.json");
        // fetch CODECHECK Type data
        try {
            $jsonApiCaller->fetch();
        } catch (ApiFetchException $e) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log($e);
            throw $e;
            return;
        }
        // get json Data from API Caller
        $data = $jsonApiCaller->getData();

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