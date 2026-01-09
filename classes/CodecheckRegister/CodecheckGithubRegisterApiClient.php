<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

require __DIR__ . '/../../vendor/autoload.php';

use Github\Client;
use Dotenv\Dotenv;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// api client
class CodecheckGithubRegisterApiClient
{
    private $issues = [];
    private UniqueArray $labels;
    private $client;
    private string $githubRegisterRepository;
    private string $submissionID;
    private string $journalName;

    /**
     * Initializes a new CODECHECK GitHub Register Api Parser (initialize the GitHub Client and a new unique Array)
     * 
     * @param string $githubRegisterRepository The Repository of the GitHub Register
     * @param string $submissionID The ID of the Submission realted to the GitHub Register Issue
     * @param mixed $journal The name of the Journal the Submission is published in
     */
    function __construct(string $githubRegisterRepository, string $submissionID, mixed $journal)
    {
        $this->client = new Client();
        $this->labels = new UniqueArray();
        $this->githubRegisterRepository = $githubRegisterRepository;
        $this->submissionID = $submissionID;
        $this->journalName = $journal
                                ? $journal->getLocalizedName()
                                : 'Unknwon Journal';
    }

    /**
     * Fetches all Issues from the CODECHECK GitHub Register
     */
    public function fetchIssues(): void
    {
        $issuePage = 1;
        $issuesToFetchPerPage = 20;
        $fetchedMatchingIssue = false;

        do {
            try {
                $allissues = $this->client->api('issue')->all('codecheckers', $this->githubRegisterRepository, [
                    'state'     => 'all',          // 'open', 'closed', or 'all'
                    'labels'    => 'id assigned',  // label
                    'sort'      => 'updated',
                    'direction' => 'desc',
                    'per_page'  => $issuesToFetchPerPage, // issues that will be fetched per page
                    'page'      => $issuePage,
                ]);
            } catch (\Throwable $e) {
                throw new ApiFetchException("Failed fetching the GitHub Issues\n" . $e->getMessage());
            }

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

    /**
     * Fetches a Issue Labels from the CODECHECK GitHub Register
     */
    public function fetchLabels(): void
    {
        try {
            $fetchedLabels = $this->client->api('issue')->labels()->all('codecheckers', $this->githubRegisterRepository);
        } catch (\Throwable $e) {
            throw new ApiFetchException("Failed fetching the GitHub Issue Labels for the Venue Names\n" . $e->getMessage());
        }
        
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
     * @param string $authorString The formatted author string e.g. `author name et al.`
     * @return string Returns the GitHub URL of the newly created issue
     */
    public function addIssue(
        CertificateIdentifier $certificateIdentifier,
        string $codecheckVenueType,
        string $codecheckVenueName,
        string $authorString,
    ): string {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $repositoryOwner = 'codecheckers';
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        $issueTitle = $authorString . ' | ' . $certificateIdentifier->toStr();
        $issueBody = 'Journal: `' . $this->journalName . '`<br />' . 'Submission ID: `' . $this->submissionID . '`';
        $labelStrings = ['id assigned'];

        $labelStrings[] = $codecheckVenueType;
        $labelStrings[] = $codecheckVenueName;

        try {
            $issue = $this->client->api('issue')->create(
                $repositoryOwner,
                $this->githubRegisterRepository,
                [
                    'title' => $issueTitle,
                    'body'  => $issueBody,
                    'labels' => $labelStrings
                ]
            );
        } catch (\Throwable $e) {
    throw new ApiCreateException("Error while adding the new GitHub issue with the new Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage());
        }

        return $issue['html_url'];
    }

    /**
     * Gets all fetched CODECHECK GtiHub Register Issues
     * 
     * @return array Returns an array of all CODECHECK GtiHub Register Issues
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Gets all fetched CODECHECK GtiHub Register Issue Labels
     * 
     * @return UniqueArray Returns a `UniqueArray` of all CODECHECK GtiHub Register Issue Labels
     */
    public function getLabels(): UniqueArray
    {
        return $this->labels;
    }
}