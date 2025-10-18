<?php
namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use Ds\Set;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckVenueTypes;

class CodecheckVenueNames
{
    private Set $set;

    function __construct()
    {
        // Initialize Set
        $this->set = new Set();

        $apiCaller = new CodecheckRegisterGithubIssuesApiParser();

        // fetch CODECHECK Certificate GitHub Labels
        $apiCaller->fetchLabels();
        // get Labels from API Caller
        $labels = $apiCaller->getLabels();

        // find all venue Types
        // TODO: Remove this once the actualy Codecheck API contains the labels/ Venue Names to fetch
        $codecheckVenueTypes = new CodecheckVenueTypes();

        foreach($labels as $label) {
            // If a Label is already a Venue Type it can't also be a venue Name
            // Therefore this Label has to be skipped
            if($codecheckVenueTypes->get()->contains($label) || $label == "id assigned" || $label == "development") {
                continue;
            }
            // add Label to Venue Names
            $this->set->add($label);
        }
    }

    public function get(): Set
    {
        return $this->set;
    }
}