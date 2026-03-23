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
use APP\plugins\generic\codecheck\classes\Exceptions\GithubUrlParseException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiUpdateException;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// api client
class CodecheckGithubRegisterApiClient
{
    private $issues = [];
    private UniqueArray $labels;
    private $client;
    private string $githubRegisterRepositoryOwner;
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
    function __construct(string $githubRegisterRepositoryOwner, string $githubRegisterRepository, string $submissionID, mixed $journal, ?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->labels = new UniqueArray();
        $this->githubRegisterRepositoryOwner = $githubRegisterRepositoryOwner;
        $this->githubRegisterRepository = $githubRegisterRepository;
        $this->submissionID = $submissionID;
        $this->journalName = $journal?->getLocalizedName() ?? 'Unknown Journal';
    }

    /**
     * Parses a GitHub Url and returns owner, repository, branch and a specified path (if a path was specified)
     * @param string $url The GitHub Url
     * @return array The GitHub Url data (owner, repository, branch and a specified path)
     */
    public static function parseGithubUrl(string $url): array
    {
        // Case 1: Blob URL (folder or file)
        $patternBlob = '#^https://github\.com/([^/]+)/([^/]+)/blob/([^/]+)/(.*)$#';
        if (preg_match($patternBlob, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo'  => $matches[2],
                'ref'   => $matches[3],
                'path'  => rtrim($matches[4], '/'),
            ];
        }

        // Case 2: Repo root URL
        // e.g. https://github.com/codecheckers/certificate-2025-029
        $patternRepo = '#^https://github\.com/([^/]+)/([^/]+)/?#';
        if (preg_match($patternRepo, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo'  => $matches[2],
                'ref'   => 'main',   // default branch guess
                'path'  => '',       // repo root
            ];
        }

        throw new GithubUrlParseException("Unsupported GitHub URL format: $url");
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
                    'labels'    => 'id assigned',  // select only issues where there is an id assigned
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
                throw new NoMatchingIssuesFoundException("There was no open or closed issue found with the label 'id assigned' in the GitHub Codecheck Register.");
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
     * @param CodecheckIssueLabels $codecheckIssueLabels The CODECHECK Issue Labels that will be added
     * @param string $authorString The formatted author string e.g. `author name et al.`
     * @param string $paperTitle The Title of the submitted paper / preprint / article
     * @return array Returns the GitHub URL & Issue Number of the newly created issue
     */
    public function addIssue(
        CertificateIdentifier $certificateIdentifier,
        CodecheckIssueLabels $codecheckIssueLabels,
        string $paperTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): array {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            $this->githubRegisterRepositoryOwner,
            $this->githubRegisterRepository,
            $certificateIdentifier,
            $codecheckIssueLabels,
            $paperTitle,
            $this->journalName,
            $authorString,
            $this->submissionID,
            $codecheckers,
            $repositories
        );

        try {
            $issue = $this->client->api('issue')->create(
                $this->githubRegisterRepositoryOwner,
                $this->githubRegisterRepository,
                [
                    'title' => $codecheckIssue->getTitle(),
                    'body'  => $codecheckIssue->getBody(),
                    'labels' => $codecheckIssue->getLabels()
                ]
            );
        } catch (\Throwable $e) {
            throw new ApiCreateException("Error while adding the new GitHub issue with the new Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage());
        }

        return $issue;
    }

    /**
     * Adds an Issue with the new Certificate Identifier to the CODECHECK GitHub Register
     *
     * @param int $issueNumber The Number of the corresponding GitHub Issue
     * @param CertificateIdentifier $certificateIdentifier The Certificate identifier to be added
     * @param CodecheckIssueLabels $codecheckIssueLabels The CODECHECK Issue Labels that will be updated
     * @param string $authorString The formatted author string e.g. `author name et al.`
     * @param string $paperTitle The Title of the submitted paper / preprint / article
     * @return array Returns the GitHub URL & Issue Number of the newly created issue
     */
    public function updateIssue(
        int $issueNumber,
        CertificateIdentifier $certificateIdentifier,
        CodecheckIssueLabels $codecheckIssueLabels,
        string $paperTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): array {
        $token = $_ENV['CODECHECK_REGISTER_GITHUB_TOKEN'];

        $this->client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            $this->githubRegisterRepositoryOwner,
            $this->githubRegisterRepository,
            $certificateIdentifier,
            $codecheckIssueLabels,
            $paperTitle,
            $this->journalName,
            $authorString,
            $this->submissionID,
            $codecheckers,
            $repositories
        );

        $issueContents = [
            'title' => $codecheckIssue->getTitle(),
            'body'  => $codecheckIssue->getBody()
        ];

        if(!empty($codecheckIssueLabels->get()->toArray())){
            $issueContents['labels'] = $codecheckIssue->getLabels();
        }

        try {
            $issue = $this->client->api('issue')->update(
                $this->githubRegisterRepositoryOwner,
                $this->githubRegisterRepository,
                $issueNumber,
                $issueContents,
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException("Error while updating GitHub issue #$issueNumber with the Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage());
        }

        return $issue;
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
