<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

require __DIR__ . '/../../vendor/autoload.php';

use Github\Client;
use Dotenv\Dotenv;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\UniqueArray;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\Exceptions\NoMatchingIssuesFoundException;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// api call
class CodecheckRegisterGithubIssuesApiParser
{
    private $issues = [];
    private UniqueArray $labels;
    private $client;

    function __construct()
    {
        $this->client = new Client();
        $this->labels = new UniqueArray();
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

    /**
     * Adds an Issue with the new Certificate Identifier to the CODECHECK GitHub Register
     *
     * @param CertificateIdentifier $certificateIdentifier The Certificate identifier to be added
     * @param string $codecheckVenueType The CODECHECK Venue Type that will be added as a label to the issue
     * @param string $codecheckVenueName The CODECHECK Venue Name that will be added as a second label to the issue
     * @return string Returns the GitHub URL of the newly created issue
     */
    public function addIssue(
        CertificateIdentifier $certificateIdentifier,
        string $codecheckVenueType,
        string $codecheckVenueName
    ): string {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $repositoryOwner = 'codecheckers';
        $repositoryName = 'testing-dev-register';
        $issueTitle = 'New CODECHECK | ' . $certificateIdentifier->toStr();
        $issueBody = '';
        $labelStrings = ['id assigned'];

        $labelStrings[] = $codecheckVenueType;
        $labelStrings[] = $codecheckVenueName;

        $issue = $this->client->api('issue')->create(
            $repositoryOwner,
            $repositoryName,
            [
                'title' => $issueTitle,
                'body'  => $issueBody,
                'labels' => $labelStrings
            ]
        );

        return $issue['html_url'];
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getLabels(): UniqueArray
    {
        return $this->labels;
    }
}