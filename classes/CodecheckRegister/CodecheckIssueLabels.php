<?php
namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckApiClient;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public static function fromApi(string $url, ?CodecheckApiClient $apiClient = null): CodecheckIssueLabels
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
            CodecheckLogger::error('CurlInit Exception: ' . $curlInitException->getMessage());
            throw $curlInitException;
        } catch (CurlReadException $curlReadException) {
            // TODO: Implement that the user gets notified, that the fetching of the Labels didn't work
            CodecheckLogger::error('CurlRead Exception: ' . $curlReadException->getMessage());
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

        $codecheckIssueLabels->saveIssueLabelsToDB();

        return $codecheckIssueLabels;
    }

    public static function fromDB(): CodecheckIssueLabels
    {
        $issueLabelRecords = DB::table('codecheck_issue_labels')
            ->pluck('label')
            ->toArray();

        CodecheckLogger::debug("Issue Label Records: " . json_encode($issueLabelRecords));

        $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelRecords ?? []);
        return $codecheckIssueLabels;
    }

    /**
     * This function saves all the Codecheck Issue Labels to the Database
     * 
     * @return void
     */
    public function saveIssueLabelsToDB(): bool
    {   
        CodecheckLogger::debug("Saving Issue Label data to DB: " . print_r($this->uniqueArray->toArray(), true));

        $tableName = 'codecheck_issue_labels';

        $tableExists = Schema::hasTable('codecheck_issue_labels');

        if(!$tableExists) {
            CodecheckLogger::debug("Issue Label Table doesnt exist");
            return !$tableExists;
        }

        $labelsLastUpdated = date('Y-m-d H:i:s');

        foreach ($this->uniqueArray->toArray() as $label) {
            if(is_string($label)) {
                $dbLabelRecord = [
                    'label' => $label,
                    'labels_last_updated' => $labelsLastUpdated
                ];

                $recordExists = DB::table($tableName)
                    ->where('label', $label)
                    ->exists();

                if ($recordExists) {
                    DB::table($tableName)
                        ->where('label', $label)
                        ->update($dbLabelRecord);
                    CodecheckLogger::debug("Updated existing label record");
                } else {
                    DB::table($tableName)->insert($dbLabelRecord);
                    CodecheckLogger::debug("Created new label record");
                }
            }
        }

        return true;
    }

    public function add(string $issue): void
    {
        $this->uniqueArray->add($issue);
    }

    public function addLabelArray(array $labels): void
    {
        $this->uniqueArray->addArray($labels);
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
