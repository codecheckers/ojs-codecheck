<?php

require __DIR__ . '/../../vendor/autoload.php';

use Github\Client;
use Dotenv\Dotenv;
use Ds\Set;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

class NoMatchingIssuesFoundException extends \Exception
{
    public function __construct(
        string $message = "No more issues available from GitHub API",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

class ApiFetchException extends \Exception
{
    public function __construct(
        string $message = "Error fetching the API data",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

class JsonApiCaller
{
    private $url;
    private $jsonData = [];

    function __construct(string $url)
    {
        $this->url = $url;
    }

    public function fetch()
    {
        // Fetch JSON from API
        $response = file_get_contents($this->url);

        // throw error if no data was fetched from API
        if ($response === FALSE) {
            throw new ApiFetchException("Error fetching the API data");
        }

        // Decode JSON into PHP array
        $this->jsonData = json_decode($response, true);
    }

    public function getData(): array
    {
        return $this->jsonData;
    }
}

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
            if($codecheckVenueTypes->get()->contains($label) || $label == "id assigned") {
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

class CodecheckVenue
{
    private $venueName;
    private $venueType;

    public function setVenueName(string $venueName)
    {
        $this->venueName = str_replace(["\r", "\n"], "", $venueName);
    }

    public function setVenueType(string $venueType)
    {
        $this->venueType = str_replace(["\r", "\n"], "", $venueType);
    }

    public function getVenueName(): string
    {
        return $this->venueName;
    }

    public function getVenueType(): string
    {
        return $this->venueType;
    }
}

class CertificateIdentifier
{
    private $year;
    private $number;

    function __construct(int $year, int $number)
    {
        $this->year = $year;
        $this->number = $number;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    // Factory Method for Certificate Identifier
    static function fromStr(string $identifier_str): CertificateIdentifier
    {
        // split Identifier String at '-'
        list($year, $number) = explode('-', $identifier_str);
        // create new instance of $certificateIdentifier (cast to int from str)
        $certificateIdentifier = new CertificateIdentifier((int) $year, (int) $number);
        // return new instance of $certificateIdentifier
        return $certificateIdentifier;
    }

    // Factory Method for new unique Identifier
    static function newUniqueIdentifier(CertificateIdentifierList $certificateIdentifierList): CertificateIdentifier
    {
        $latest_identifier = $certificateIdentifierList->getNewestIdentifier();
        $current_year = (int) date("Y");

        // different year, so this is the first CODECHECK certificate of the year -> id 001
        if($current_year != $latest_identifier->getYear()) {
            // configure new Identifier which is the first identifier of a new year
            $new_identifier = new CertificateIdentifier((int) $current_year, 1);
            return $new_identifier;
        }

        // get the latest id
        $latest_id = (int) $latest_identifier->getNumber();
        // increment the latest id by one to get a new unique one
        $latest_id++;
        // create new Identifier
        $new_identifier = new CertificateIdentifier($latest_identifier->getYear(), $latest_id);
        return $new_identifier;
    }

    public function toStr(): string
    {
        // pad with leading zeros (3 digits) in case number doesn't have 3 digits already
        return $this->year . '-' . str_pad($this->number, 3, '0', STR_PAD_LEFT);;
    }
}

class CertificateIdentifierList
{
    private Set $set;

    function __construct()
    {
        $this->set = new Set();   
    }

    // Factory Method to create a new CertificateIdentifierList from a GitHub API fetch
    static function fromApi(
        CodecheckRegisterGithubIssuesApiParser $apiParser
    ): CertificateIdentifierList {
        $newCertificateIdentifierList = new CertificateIdentifierList();

        // fetch API
        $apiParser->fetchIssues();

        foreach ($apiParser->getIssues() as $issue) {
            // raw identifier (can still have ranges of identifiers);
            $rawIdentifier = getRawIdentifier($issue['title']);
            
            // append to all identifiers in new Register
            $newCertificateIdentifierList->appendToCertificateIdList($rawIdentifier);
        }

        // return the new Register
        return $newCertificateIdentifierList;
    }

    public function appendToCertificateIdList(string $rawIdentifier): void
    {
        // list of certificate identifiers in range
        $idRange = [];

        // if it is a range
        if(strpos($rawIdentifier, '/')) {
            // split into "fromIdStr" and "toIdStr"
            list($fromIdStr, $toIdStr) = explode('/', $rawIdentifier);

            $from_identifier = CertificateIdentifier::fromStr($fromIdStr);
            $to_identifier = CertificateIdentifier::fromStr($toIdStr);

            // append to $idRange list
            for ($id_count = $from_identifier->getNumber(); $id_count <= $to_identifier->getNumber(); $id_count++) {
                $new_identifier = new CertificateIdentifier($from_identifier->getYear(), $id_count);
                // append new identifier
                $idRange[] = $new_identifier;
            }
        }
        // if it isn't a list then just append on identifier
        else {
            $new_identifier = CertificateIdentifier::fromStr($rawIdentifier);
            $idRange[] = $new_identifier;
        }

        // append to all certificate identifiers
        foreach ($idRange as $identifier) {
            if (!$this->set->contains($identifier)) {
                $this->set->add($identifier);
            }
        }
    }

    // sort ascending Certificate Identifiers
    public function sortAsc(): void
    {
        $this->set->sort(function($a, $b) {
            // First, compare year
            if ($a->getYear() !== $b->getYear()) {
                return $a->getYear() <=> $b->getYear();
            }
            // If years are equal, compare ID
            return $a->getNumber() <=> $b->getNumber();
        });
    }

    public function sortDesc(): void
    {
        $this->set->sort(function($a, $b) {
            // First, compare year descending
            if ($a->getYear() !== $b->getYear()) {
                return $b->getYear() <=> $a->getYear();
            }
            // If years are equal, compare ID descending
            return $b->getNumber() <=> $a->getNumber();
        });
    }

    public function getNumberOfIdentifiers(): int
    {
        return $this->set->count();
    }

    // return the latest identifier
    public function getNewestIdentifier(): CertificateIdentifier
    {
        $this->sortDesc();
        // get first element of sort descending -> newest element
        return $this->set->first();
    }

    public function toStr(): string
    {
        $return_str = "Certificate Identifiers:\n";
        foreach ($this->set as $identifier) {
            $return_str .= $identifier->toStr() . "\n";
        }
        return $return_str;
    }
}

// get the certificate ID from the issue description
function getRawIdentifier(string $title): string
{
    $title = strtolower($title); // convert whole title to lowercase

    //$title = "Arabsheibani, Winter, Tomko | 2025-026/2025-029";

    if (strpos($title, '|') !== false) {
        // find the last "|"
        $seperator = strrpos($title, '|');
        // move one position forwards (so we get character after '|')
        $seperator++;

        // Find where the next line break occurs after "certificate"
        $rawIdentifier = substr($title, $seperator);
        // remove white spaces
        $rawIdentifier = preg_replace('/[\s]+/', '', $rawIdentifier);
    }

    return $rawIdentifier;
}

// api call
class CodecheckRegisterGithubIssuesApiParser
{
    private $issues = [];
    private Set $labels;
    private $client;

    function __construct()
    {
        $this->client = new Client();
        $this->labels = new Set();
    }

    public function fetchIssues(): void
    {
        $issuePage = 1;
        $issuesToFetchPerPage = 20;
        $fetchedMatchingIssue = false;

        do {
            $allissues = $this->client->api('issue')->all('codecheckers', 'register', [
                'state'     => 'all',          // 'open', 'closed', or 'all'
                'labels'    => 'id assigned',  // label
                'sort'      => 'updated',
                'direction' => 'desc',
                'per_page'  => $issuesToFetchPerPage, // issues that will be fetched per page
                'page'      => $issuePage,
            ]);

            // stop looping if no more issues exist and we haven't yet found a matching issue
            if (empty($allissues) && empty($this->issue)) {
                throw new NoMatchingIssuesFoundException("There was no Issue found with a '|' inside the GitHub Codecheck Register.");
            }

            foreach ($allissues as $issue) {
                if (strpos($issue['title'], '|') !== false) {
                    $this->issues[] = $issue;
                    $fetchedMatchingIssue = true;
                }
            }

            $issuePage++;
        } while (!$fetchedMatchingIssue);
    }

    public function fetchLabels(): void
    {
        $fetchedLabels = $this->client->api('issue')->labels()->all('codecheckers', 'register');
        foreach($fetchedLabels as $label) {
            $this->labels->add($label["name"]);
        }
    }

    public function addIssue(
        CertificateIdentifier $certificateIdentifier,
        string $codecheckVenueType,
        string $codecheckVenueName
    ): void {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $repositoryOwner = 'codecheckers';
        $repositoryName = 'testing-dev-register';
        $issueTitle = 'New CODECHECK | ' . $certificateIdentifier->toStr();
        $issueBody = '';
        $labels = ['id assigned'];

        $labels[] = $codecheckVenueType;
        $labels[] = $codecheckVenueName;

        $this->client->api('issue')->create(
            $repositoryOwner,
            $repositoryName,
            [
                'title' => $issueTitle,
                'body'  => $issueBody,
                'labels' => $labels
            ]
        );
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getLabels(): Set
    {
        return $this->labels;
    }
}


// CODECHECK GitHub Issue Register API parser
$apiParser = new CodecheckRegisterGithubIssuesApiParser();

// CODECHECK Register with list of all identifiers in range
$certificateIdentifierList = CertificateIdentifierList::fromApi($apiParser);

// print Certificate Identifier list
$certificateIdentifierList->sortDesc();
echo $certificateIdentifierList->toStr();

echo $certificateIdentifierList->getNewestIdentifier()->toStr() . "\n";

$new_identifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

$codecheckVenueTypes = new CodecheckVenueTypes();
$codecheckVenueNames = new CodecheckVenueNames();

$codecheckVenue = new CodecheckVenue();

print_r($codecheckVenueTypes->get()->toArray());
echo "\n";
print_r($codecheckVenueNames->get()->toArray());

// TODO: Replace CLI logic here to Venue Type & Venue Name combination being selected by form in journal plugin settings
$stdin = fopen("php://stdin","r");
echo "Enter a Venue Type:\n";
$codecheckVenue->setVenueType(fgets($stdin));
echo "\nEnter a Venue Name:\n";
$codecheckVenue->setVenueName(fgets($stdin));

echo $codecheckVenue->getVenueType() . ", " . $codecheckVenue->getVenueName();

$apiParser->addIssue($new_identifier, $codecheckVenue->getVenueType(), $codecheckVenue->getVenueName());

echo "Added new issue with identifier: " . $new_identifier->toStr() . "\n";

//echo "{$num_of_issues}";