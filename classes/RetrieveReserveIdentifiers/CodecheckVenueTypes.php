<?php
namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\UniqueArray;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\JsonApiCaller;

class CodecheckVenueTypes
{
    private UniqueArray $uniqueArray;

    function __construct()
    {
        // Initialize unique Array
        $this->uniqueArray = new UniqueArray();
        // Intialize API caller
        $jsonApiCaller = new JsonApiCaller("https://codecheck.org.uk/register/venues/index.json");
        // fetch CODECHECK Type data
        $jsonApiCaller->fetch();
        // get json Data from API Caller
        $data = $jsonApiCaller->getData();

        foreach($data as $venue) {
            // insert every type (as this is a unique Array each Type will only occur once)
            $type = $venue["Venue type"];
            // Add every venue type to the unique Array
            $this->uniqueArray->add($type);
        }
    }

    public function get(): UniqueArray
    {
        return $this->uniqueArray;
    }
}