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
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiUpdateException;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// api client
class CodecheckGithubRegisterApiClient
{
    private $issues = [];
    private UniqueArray $labels;
    private $client;
    private string $githubPAT;
    private string $githubRegisterOrganization;
    private string $githubRegisterRepository;
    private string $submissionID;
    private string $journalName;

    /**
     * Initializes a new CODECHECK GitHub Register Api Parser (initialize the GitHub Client and a new unique Array)
     * 
     * @param string $githubPersonalAccessToken The required GitHub `(PAT)` (classic), to access the GitHub Register Repository
     * @param string $githubRegisterOrganization The Organization owning the GitHub Register Repository
     * @param string $githubRegisterRepository The Repository of the GitHub Register
     * @param string $submissionID The ID of the Submission realted to the GitHub Register Issue
     * @param mixed $journal The name of the Journal the Submission is published in
     */
    function __construct(string $githubPersonalAccessToken, string $githubRegisterOrganization, string $githubRegisterRepository, string $submissionID, mixed $journal, ?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->labels = new UniqueArray();
        $this->githubPAT = $githubPersonalAccessToken;
        $this->githubRegisterOrganization = $githubRegisterOrganization;
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
     * Fetches only the first newest Issues from the CODECHECK GitHub Register
     */
    public function fetchNewestIssues(): void
    {
        $issuePage = 1;
        $issuesToFetchPerPage = 20;
        $fetchedMatchingIssue = false;

        do {
            try {
                $allissues = $this->client->api('issue')->all($this->githubRegisterOrganization, $this->githubRegisterRepository, [
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
            if (empty($allissues) && empty($this->issues)) {
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
     * Fetches all Issues from the CODECHECK GitHub Register
     */
    public function fetchAllIssues(): void
    {
        try {
            $allissues = $this->client->api('search')->issues('repo:' . $this->githubRegisterOrganization . '/' . $this->githubRegisterRepository . ' sort:"updated"');
        } catch (\Throwable $e) {
            throw new ApiFetchException("Failed fetching the GitHub Issues\n" . $e->getMessage());
        }

        foreach ($allissues['items'] as $issue) {
            if (strpos($issue['title'], '|') !== false) {
                $this->issues[] = $issue;
            }
        }

        // stop if no issues exist and we haven't yet found any matching issue
        if (empty($allissues) && empty($this->issues)) {
            throw new NoMatchingIssuesFoundException("There was no open or closed issue found with the label 'id assigned' in the GitHub Codecheck Register.");
        }
    }

    /**
     * Fetches all Issues from the CODECHECK GitHub Register
     */
    public function fetchIssueByIdentifier(
        CertificateIdentifier $certificateIdentifier
    ): void
    {
        try {
            $allissues = $this->client->api('search')->issues('repo:' . $this->githubRegisterOrganization . '/' . $this->githubRegisterRepository . ' "'. $certificateIdentifier->toStr() . '" sort:"updated"');
        } catch (\Throwable $e) {
            throw new ApiFetchException("Failed fetching the GitHub Issues\n" . $e->getMessage());
        }

        foreach ($allissues['items'] as $issue) {
            if (strpos($issue['title'], '|') !== false) {
                $this->issues[] = $issue;
            }
        }

        // stop if no issues exist and we haven't yet found any matching issue
        if (empty($allissues) && empty($this->issues)) {
            throw new NoMatchingIssuesFoundException("There was no open or closed issue found with the label 'id assigned' in the GitHub Codecheck Register.");
        }
    }

    /**
     * Fetches a Issue Labels from the CODECHECK GitHub Register
     */
    public function fetchLabels(): void
    {
        try {
            $fetchedLabels = $this->client->api('issue')->labels()->all($this->githubRegisterOrganization, $this->githubRegisterRepository);
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
        array $repositories,
        array $updateInformation
    ): array {
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $certificateIdentifier,
            $codecheckIssueLabels,
            $paperTitle,
            $this->journalName,
            $authorString,
            $this->submissionID,
            $codecheckers,
            $repositories,
            $updateInformation
        );

        try {
            $issue = $this->client->api('issue')->create(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                [
                    'title' => $codecheckIssue->getTitle(),
                    'body'  => $codecheckIssue->getBody(),
                    'labels' => $codecheckIssue->getLabels()
                ]
            );
        } catch (\Throwable $e) {
            throw new ApiCreateException("Error while adding the new GitHub issue with the new Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage(), $e->getCode());
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
        array $updateInformation,
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
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $certificateIdentifier,
            $codecheckIssueLabels,
            $paperTitle,
            $this->journalName,
            $authorString,
            $this->submissionID,
            $codecheckers,
            $repositories,
            $updateInformation
        );

        $issueContents = [];

        if(in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE, $updateInformation)) {
            $issueContents['title'] = $codecheckIssue->getTitle();
        }

        if(in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY, $updateInformation)) {
            $issueContents['body'] = $codecheckIssue->getBody();
        }

        if(!empty($codecheckIssueLabels->get()->toArray())){
            $issueContents['labels'] = $codecheckIssue->getLabels();
        }

        try {
            $issue = $this->client->api('issue')->update(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                $issueContents,
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException("Error while updating GitHub issue #$issueNumber with the Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage(), $e->getCode());
        }

        return $issue;
    }

    /**
     * Deposits a new row into the register.csv of the CODECHECK GitHub Register,
     * by opening a Pull Request against the register repository.
     *
     * Flow: fetch current register.csv + its blob sha -> append the new row ->
     * create a branch off the register's default branch -> commit the updated
     * file to that branch -> open a PR back to the default branch.
     *
     * @param array $row Associative array with keys: Certificate, Repository, Type, Venue, Issue
     * @param string $certificateIdentifier Used to build a unique branch name and PR title
     * @return array The created Pull Request's GitHub API response array
     */
    public function depositRegisterRow(array $row, string $certificateIdentifier): array
    {
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        $registerFilePath = 'register.csv';

        // 1. Get the current register.csv content + sha, and the repo's default branch
        try {
            $repoInfo = $this->client->api('repo')->show($this->githubRegisterOrganization, $this->githubRegisterRepository);
            $defaultBranch = $repoInfo['default_branch'] ?? 'main';

            $fileContents = $this->client->api('repo')->contents()->show(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $registerFilePath,
                $defaultBranch
            );
        } catch (\Throwable $e) {
            throw new ApiFetchException("Failed fetching '$registerFilePath' from the register repository.\n" . $e->getMessage());
        }

        $currentCsv = base64_decode($fileContents['content']);
        $currentSha = $fileContents['sha'];

        // 2. Append the new row, preserving the existing line-ending style
        $newCsv = $this->appendCsvRow($currentCsv, $row);

        // 3. Create a new branch off the default branch's current commit
        $branchName = 'register-deposit/' . preg_replace('/[^A-Za-z0-9_.-]/', '-', $certificateIdentifier);

        try {
            $baseRef = $this->client->api('gitData')->references()->show(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                'heads/' . $defaultBranch
            );
            $baseSha = $baseRef['object']['sha'];

            $this->client->api('gitData')->references()->create(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                [
                    'ref' => 'refs/heads/' . $branchName,
                    'sha' => $baseSha,
                ]
            );
        } catch (\Throwable $e) {
            throw new ApiCreateException("Failed creating the branch '$branchName' for the register deposit.\n" . $e->getMessage(), $e->getCode());
        }

        // 4. Commit the updated register.csv to the new branch
        try {
            $this->client->api('repo')->contents()->update(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $registerFilePath,
                $newCsv,
                'Add register entry for certificate ' . $certificateIdentifier,
                $currentSha,
                $branchName
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException("Failed committing the updated '$registerFilePath' to branch '$branchName'.\n" . $e->getMessage(), $e->getCode());
        }

        // 5. Open the Pull Request
        try {
            $pullRequest = $this->client->api('pull_request')->create(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                [
                    'base' => $defaultBranch,
                    'head' => $branchName,
                    'title' => 'Register deposit: certificate ' . $certificateIdentifier,
                    'body' => $this->buildDepositPrBody($row),
                ]
            );
        } catch (\Throwable $e) {
            throw new ApiCreateException("Failed opening the Pull Request for the register deposit.\n" . $e->getMessage(), $e->getCode());
        }

        return $pullRequest;
    }

    /**
     * Appends a single row to CSV content, matching the existing header's
     * column order and preserving the file's trailing-newline style.
     */
    private function appendCsvRow(string $currentCsv, array $row): string
    {
        $hadTrailingNewline = str_ends_with($currentCsv, "\n");
        $lines = explode("\n", rtrim($currentCsv, "\n"));

        $header = str_getcsv($lines[0]);

        $newLineValues = [];
        foreach ($header as $column) {
            $newLineValues[] = $row[$column] ?? '';
        }

        $newLine = implode(',', array_map(
            fn($value) => str_contains($value, ',') ? '"' . str_replace('"', '""', $value) . '"' : $value,
            $newLineValues
        ));

        $lines[] = $newLine;

        return implode("\n", $lines) . ($hadTrailingNewline ? "\n" : '');
    }

    private function buildDepositPrBody(array $row): string
    {
        $body = "Automated register deposit opened by the OJS CODECHECK plugin on publication.\n\n";
        $body .= "| Column | Value |\n|---|---|\n";
        foreach ($row as $column => $value) {
            // Turn the Issue number into a full URL so GitHub auto-links this PR
            // with the corresponding register issue, and posts a backlink comment there too.
            if ($column === 'Issue' && $value !== 'NA' && ctype_digit((string) $value)) {
                $issueUrl = "https://github.com/{$this->githubRegisterOrganization}/{$this->githubRegisterRepository}/issues/{$value}";
                $value = "[#{$value}]({$issueUrl})";
            }
            $body .= "| $column | $value |\n";
        }
        return $body;
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
