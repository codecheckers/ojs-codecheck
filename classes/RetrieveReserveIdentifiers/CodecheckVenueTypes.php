<?php
namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use Ds\Set;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\JsonApiCaller;

class CodecheckVenueTypes
{
    private Set $set;

    function __construct()
    {
        // Initialize Set
        $this->set = new Set();
        // Intialize API caller
        $jsonApiCaller = new JsonApiCaller("https://codecheck.org.uk/register/venues/index.json");
        // fetch CODECHECK Type data
        $jsonApiCaller->fetch();
        // get json Data from API Caller
        $data = $jsonApiCaller->getData();

        foreach($data as $venue) {
            // insert every type (as this is a Set each Type will only occur once)
            $type = $venue["Venue type"];
            // Add every venue type to the Set
            $this->set->add($type);
        }
    }

    public function get(): Set
    {
        return $this->set;
    }
}