<?php
namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;

class CodecheckIssueLabels
{
    private UniqueArray $uniqueArray;

    /**
     * Initializes a new List of all CODECHECK Issue Labels
     */
    function __construct(array $issueLabelArray)
    {
        // Initialize and fill unique Array
        $this->uniqueArray = UniqueArray::from($issueLabelArray);
    }

    public static function fromApi(string $url): CodecheckIssueLabels
    {
        $issueLabelArray = [];

        // fetch CODECHECK Certificate GitHub Labels
        // Intialize API caller
        $codecheckApiClient = $apiClient ?? new CodecheckApiClient();
        // fetch CODECHECK Type data
        try {
            $codecheckApiClient->fetch($url);
        } catch (CurlInitException $curlInitException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log("CurlInit Exception: " . $curlInitException);
            throw $curlInitException;
        } catch (CurlReadException $curlReadException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            error_log("CurlInit Exception: " . $curlReadException);
            throw $curlReadException;
        }
        // get json Data from API call
        $data = $codecheckApiClient->getData();

        foreach($data as $venue) {
            $label = $venue["Issue label"];
            // If a Label is "id assigned" or "development" it automatically gets assigned
            // Therefore this Label has to be skipped here, as it shouldn't be selected manually by the user
            if($label == "id assigned" || $label == "development") {
                continue;
            }
            // add Label to Venue Names
            $issueLabelArray[] = $label;
        }

        $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);
        return $codecheckIssueLabels;
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
