<?php

require __DIR__ . '/vendor/autoload.php';

use Github\Client;
use Dotenv\Dotenv;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Set
{
    protected $arraySet = [];

    public function insert($element): void
    {
        foreach ($this->arraySet as $listElement) {
            if($listElement->toStr() == $element->toStr()) {
                return;
            }
        }
        $this->arraySet[] = $element;
    }

    public function insertArray(array $array): void
    {
        foreach ($array as $element) {
            $this->insert($element);
        }
    }

    public function getArray(): array
    {
        return $this->arraySet;
    }
}

enum CodecheckType
{
    case checkNL;
    case community;
    case conference_workshop;
    case institution;
    case journal;
    case lifecycleJournal;

    public function labels(): array
    {
        return match($this) {
            self::checkNL               => ['check-nl', 'community'],
            self::community             => ['community'],
            self::conference_workshop   => ['conference/workshop'],
            self::institution           => ['institution'],
            self::journal               => ['journal'],
            self::lifecycleJournal      => ['lifecycle journal'],
        };
    }
}

class CertificateIdentifier
{
    private $year;
    private $id;

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getId(): int
    {
        return $this->id;
    }

    // Factory Method for Certificate Identifier
    static function fromStr(string $identifier_str): CertificateIdentifier
    {
        // split Identifier String at '-'
        list($year, $id) = explode('-', $identifier_str);
        // create new instance of $certificateIdentifier
        $certificateIdentifier = new CertificateIdentifier();
        // set year and id (cast to int from str)
        $certificateIdentifier->setYear((int) $year);
        $certificateIdentifier->setId((int) $id);
        // return new instance of $certificateIdentifier
        return $certificateIdentifier;
    }

    // Factory Method for new unique Identifier
    static function newUniqueIdentifier(CertificateIdentifierList $certificateIdentifierList): CertificateIdentifier
    {
        $latest_identifier = $certificateIdentifierList->getNewestIdentifier();
        $current_year = (int) date("Y");

        $new_identifier = new CertificateIdentifier();

        // different year, so this is the first CODECHECK certificate of the year -> id 001
        if($current_year != $latest_identifier->getYear()) {
            // configure new Identifier
            $new_identifier->setYear($current_year);
            $new_identifier->setId(1);
            return $new_identifier;
        }

        // get the latest id
        $latest_id = (int) $latest_identifier->getId();
        // increment the latest id by one to get a new unique one
        $latest_id++;
        // configure new Identifier
        $new_identifier->setYear($latest_identifier->getYear());
        $new_identifier->setId($latest_id);
        return $new_identifier;
    }

    public function toStr(): string
    {
        // pad with leading zeros (3 digits) in case number doesn't have 3 digits already
        return $this->year . '-' . str_pad($this->id, 3, '0', STR_PAD_LEFT);;
    }
}

class CertificateIdentifierList extends Set
{
    // Factory Method to create a new CertificateIdentifierList from a GitHub API fetch
    static function fromApi(
        CodecheckRegisterGithubIssuesApiParser $apiParser
    ): CertificateIdentifierList {
        $newCertificateIdentifierList = new CertificateIdentifierList();

        // fetch API
        $apiParser->fetchApi();

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
            for ($id_count = $from_identifier->getId(); $id_count <= $to_identifier->getId(); $id_count++) {
                $new_identifier = new CertificateIdentifier();
                $new_identifier->setYear($from_identifier->getYear());
                $new_identifier->setId($id_count);
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
        $this->insertArray($idRange);
    }

    // sort ascending Certificate Identifiers
    public function sortAsc(): void
    {
        usort($this->arraySet, function($a, $b) {
            // First, compare year
            if ($a->getYear() !== $b->getYear()) {
                return $a->getYear() <=> $b->getYear();
            }
            // If years are equal, compare ID
            return $a->getId() <=> $b->getId();
        });
    }

    public function sortDesc(): void
    {
        usort($this->arraySet, function($a, $b) {
            // First, compare year descending
            if ($a->getYear() !== $b->getYear()) {
                return $b->getYear() <=> $a->getYear();
            }
            // If years are equal, compare ID descending
            return $b->getId() <=> $a->getId();
        });
    }

    public function getNumberOfIdentifiers(): int
    {
        return count($this->arraySet);
    }

    // return the latest identifier
    public function getNewestIdentifier(): CertificateIdentifier
    {
        $this->sortDesc();
        // get first element of sort descending -> newest element
        return $this->arraySet[0];
    }

    public function toStr(): string
    {
        $return_str = "Certificate Identifiers:\n";
        foreach ($this->arraySet as $id) {
            $return_str = $return_str . $id->toStr() . "\n";
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

class NoMatchingIssuesFoundException extends \Exception {
    public function __construct(
        string $message = "No more issues available from GitHub API",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

// api call
class CodecheckRegisterGithubIssuesApiParser
{
    private $issues = [];
    private $client;

    function __construct()
    {
        $this->client = new Client();
    }

    public function fetchApi(): void
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

    public function addIssue(
        CertificateIdentifier $certificateIdentifier,
        CodecheckType $codecheckType
    ): void {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $repositoryOwner = 'dxL1nus';
        $repositoryName = 'dxL1nus';
        $issueTitle = 'New CODECHECK | ' . $certificateIdentifier->toStr();
        $issueBody = '';
        $labels = ['id assigned'];

        $labels = array_merge($labels, $codecheckType->labels());

        $issue = $this->client->api('issue')->create(
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

$apiParser->addIssue($new_identifier, CodecheckType::checkNL);

echo "Added new issue with identifier: " . $new_identifier->toStr() . "\n";

//echo "{$num_of_issues}";