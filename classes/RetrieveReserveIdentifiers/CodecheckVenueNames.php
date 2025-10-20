<?php
namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\UniqueArray;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueTypes;

class CodecheckVenueNames
{
    private UniqueArray $uniqueArray;

    function __construct()
    {
        // Initialize unique Array
        $this->uniqueArray = new UniqueArray();

        $apiCaller = new CodecheckRegisterGithubIssuesApiParser();

        // fetch CODECHECK Certificate GitHub Labels
        $apiCaller->fetchLabels();
        // get Labels from API Caller
        $labels = $apiCaller->getLabels();

        // find all venue Types
        // TODO: Remove this once the actualy Codecheck API contains the labels/ Venue Names to fetch
        $codecheckVenueTypes = new CodecheckVenueTypes();

        foreach($labels->toArray() as $label) {
            // If a Label is already a Venue Type it can't also be a venue Name
            // Therefore this Label has to be skipped
            if($codecheckVenueTypes->get()->contains($label) || $label == "id assigned" || $label == "development") {
                continue;
            }
            // add Label to Venue Names
            $this->uniqueArray->add($label);
        }
    }

    public function get(): UniqueArray
    {
        return $this->uniqueArray;
    }
}