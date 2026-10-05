<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

require __DIR__ . '/../../vendor/autoload.php';

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiUpdateException;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Github\Client;

// api client
class CodecheckGithubRegisterApiClient
{
    /** How many pages of register issues one fetch will walk at most. */
    private const MAX_ISSUE_PAGES = 50;

    private $issues = [];
    private int $labelledIssuesSeen = 0;
    private $client;
    private string $githubPAT;
    private string $githubRegisterOrganization;
    private string $githubRegisterRepository;
    private string $submissionID;
    private CodecheckPostOrigin $origin;

    /**
     * Initializes a new CODECHECK GitHub Register Api Parser (initialize the GitHub Client and a new unique Array)
     *
     * @param string $githubPersonalAccessToken The required GitHub `(PAT)` (classic), to access the GitHub Register Repository
     * @param string $githubRegisterOrganization The Organization owning the GitHub Register Repository
     * @param string $githubRegisterRepository The Repository of the GitHub Register
     * @param string $submissionID The ID of the Submission realted to the GitHub Register Issue
     * @param CodecheckPostOrigin $origin Where posts come from, and the signature they end with
     */
    public function __construct(string $githubPersonalAccessToken, string $githubRegisterOrganization, string $githubRegisterRepository, string $submissionID, CodecheckPostOrigin $origin, ?Client $client = null)
    {
        $this->client = $client ?? GithubHttp::client();
        $this->githubPAT = $githubPersonalAccessToken;
        $this->githubRegisterOrganization = $githubRegisterOrganization;
        $this->githubRegisterRepository = $githubRegisterRepository;
        $this->submissionID = $submissionID;
        $this->origin = $origin;
    }

    /**
     * Fetches only the first newest Issues from the CODECHECK GitHub Register
     *
     * An empty result is a state and not an error — a register with no issue
     * carrying the `id assigned` label simply has no identifiers — so the issue
     * list is left empty and the caller decides (#130). `hasSeenLabelledIssues()`
     * is how the caller tells that apart from a register whose labelled issues
     * carry no readable identifier, which is not an empty register at all.
     */
    public function fetchNewestIssues(): void
    {
        $issuePage = 1;
        $issuesToFetchPerPage = 20;
        $fetchedMatchingIssue = false;

        do {
            try {
                $allissues = $this->client->api('issue')->all($this->githubRegisterOrganization, $this->githubRegisterRepository, [
                    'state' => 'all',          // 'open', 'closed', or 'all'
                    'labels' => Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL, // select only issues where there is an id assigned
                    'sort' => 'updated',
                    'direction' => 'desc',
                    'per_page' => $issuesToFetchPerPage, // issues that will be fetched per page
                    'page' => $issuePage,
                ]);
            } catch (\Throwable $e) {
                throw new ApiFetchException("Failed fetching the GitHub Issues\n" . $e->getMessage());
            }

            // no more pages to walk: whatever was collected so far is all there is
            if (empty($allissues)) {
                break;
            }

            $collectedBefore = count($this->issues);
            $this->collectIssuesCarryingAnIdentifier($allissues);
            $fetchedMatchingIssue = count($this->issues) > $collectedBefore;

            $issuePage++;
            // Only the remote end decides when the pages run out, and a server
            // that ignores `page` — a caching proxy, a captive portal — answers
            // the same body for ever. The walk is bounded here instead.
        } while (!$fetchedMatchingIssue && $issuePage <= self::MAX_ISSUE_PAGES);
    }

    /**
     * Whether the register held any issue carrying the `id assigned` label,
     * whatever its title — the register's own development issues aside.
     *
     * An empty identifier list plus labelled issues means the register is not
     * empty — its titles do not carry a readable `YYYY-NNN` — and reserving
     * "the first" identifier there would duplicate one that already exists.
     */
    public function hasSeenLabelledIssues(): bool
    {
        return $this->labelledIssuesSeen > 0;
    }

    /**
     * Whether the configured register repository carries the `id assigned`
     * label — `null` when that could not be established (#129, #130).
     */
    public function registerHasIdAssignedLabel(): ?bool
    {
        return self::repositoryHasLabel(
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL,
            $this->client
        );
    }

    /**
     * Whether a GitHub repository carries a label, in one request.
     *
     * The one place this question is asked, so the reservation and the settings
     * form cannot come to disagree about whether a register is usable. The
     * request is unauthenticated, like every other register read.
     *
     * Three answers, not two: only a 404 means the label is absent. A spent
     * rate limit, a private repository or a mistyped name answers `null`,
     * because "we could not read the repository" must not be reported to
     * anyone as "the label is missing".
     *
     * @param ?Client $client A client to reuse; a new one is built otherwise
     *
     * @return ?bool `true` present, `false` absent, `null` could not be established
     */
    public static function repositoryHasLabel(
        string $organization,
        string $repository,
        string $label,
        ?Client $client = null
    ): ?bool {
        try {
            ($client ?? GithubHttp::client())->api('issue')->labels()->show($organization, $repository, $label);
            return true;
        } catch (\Throwable $e) {
            if ((int) $e->getCode() === 404) {
                return false;
            }

            CodecheckLogger::warning(
                "Could not establish whether {$organization}/{$repository} has the '{$label}' label: "
                . $e->getMessage()
            );

            return null;
        }
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

        $this->collectIssuesCarryingAnIdentifier($allissues['items'] ?? []);
    }

    /**
     * Fetches all Issues from the CODECHECK GitHub Register
     */
    public function fetchIssueByIdentifier(
        CertificateIdentifier $certificateIdentifier
    ): void {
        try {
            $allissues = $this->client->api('search')->issues('repo:' . $this->githubRegisterOrganization . '/' . $this->githubRegisterRepository . ' "' . $certificateIdentifier->toStr() . '" sort:"updated"');
        } catch (\Throwable $e) {
            throw new ApiFetchException("Failed fetching the GitHub Issues\n" . $e->getMessage());
        }

        $this->collectIssuesCarryingAnIdentifier($allissues['items'] ?? []);
    }

    /**
     * Keeps the issues whose title carries a certificate identifier, which is
     * the part after the last `|` — see CertificateIdentifierList.
     *
     * The register's own development issues are left out, whatever their
     * title: they are not certificates, so an identifier written in one must
     * not be taken for the register's newest.
     */
    private function collectIssuesCarryingAnIdentifier(array $issues): void
    {
        foreach ($issues as $issue) {
            if (self::isDevelopmentIssue($issue)) {
                continue;
            }

            $this->labelledIssuesSeen++;

            if (strpos($issue['title'] ?? '', '|') !== false) {
                $this->issues[] = $issue;
            }
        }
    }

    /**
     * Whether a register issue is one of the register's own development issues.
     */
    private static function isDevelopmentIssue(array $issue): bool
    {
        return in_array(
            Constants::CODECHECK_REGISTER_DEVELOPMENT_LABEL,
            self::labelNames($issue['labels'] ?? []),
            true
        );
    }

    /**
     * The names of a set of labels as GitHub handed them over.
     *
     * They arrive as objects, and a search result occasionally as bare names,
     * so both shapes are read here rather than at each of the three call sites.
     *
     * @param mixed $labels What GitHub answered where a list of labels belongs
     *
     * @return string[]
     */
    private static function labelNames(mixed $labels): array
    {
        $names = [];

        foreach (is_array($labels) ? $labels : [] as $label) {
            $name = is_array($label) ? ($label['name'] ?? null) : $label;

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Adds an Issue with the new Certificate Identifier to the CODECHECK GitHub Register
     *
     * @param CertificateIdentifier $certificateIdentifier The Certificate identifier to be added
     * @param CodecheckIssueLabels $codecheckIssueLabels The CODECHECK Issue Labels that will be added
     * @param string $authorString The formatted author string e.g. `author name et al.`
     * @param string $paperTitle The Title of the submitted paper / preprint / article
     *
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
            $this->origin,
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
                    'body' => $codecheckIssue->getBody(),
                    'labels' => $codecheckIssue->getLabels()
                ]
            );
        } catch (\Throwable $e) {
            throw new ApiCreateException('Error while adding the new GitHub issue with the new Certificate Identifier: ' . $certificateIdentifier->toStr() . "\n" . $e->getMessage(), $e->getCode());
        }

        return $issue;
    }

    /**
     * The labels currently on a register issue.
     *
     * Read before changing any, so a label is neither added twice nor removed
     * when it is not there — and so the log can say what actually changed.
     *
     * @return string[] The label names, in the order GitHub returns them
     */
    public function getIssueLabels(int $issueNumber): array
    {
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            // The issue carries its whole label set inline. The labels endpoint
            // would answer one unpaginated page of 30, where "absent" and "not
            // on this page" read the same — and a register issue can hold more.
            $issue = $this->client->api('issue')->show(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber
            );

            return self::labelNames(is_array($issue) ? ($issue['labels'] ?? []) : []);
        } catch (\Throwable $e) {
            throw new ApiFetchException(
                "Failed fetching the labels of register issue #{$issueNumber}\n" . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * Add labels to a register issue, leaving the ones already on it alone.
     *
     * Adding is deliberately not replacing: the register issue carries labels
     * nobody here owns, and replacing the list would wipe them (#174).
     *
     * @param string[] $labels
     *
     * @throws ApiUpdateException when GitHub refuses the change.
     */
    public function addLabelsToIssue(int $issueNumber, array $labels): void
    {
        if (empty($labels)) {
            return;
        }

        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            $this->client->api('issue')->labels()->add(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                $labels
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException(
                'Could not add the labels ' . implode(', ', $labels)
                . " to register issue #{$issueNumber}: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * Remove one label from a register issue.
     *
     * @throws ApiUpdateException when GitHub refuses the change.
     */
    public function removeLabelFromIssue(int $issueNumber, string $label): void
    {
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            $this->client->api('issue')->labels()->remove(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                $label
            );
        } catch (\Throwable $e) {
            // GitHub answers 404 when the label is not on the issue, which is
            // the state the removal was asking for. Reporting that as a failure
            // would make the sync depend on nobody else having touched the
            // issue — the same rule as repositoryHasLabel()'s.
            if ((int) $e->getCode() === 404) {
                return;
            }

            throw new ApiUpdateException(
                "Could not remove the label '{$label}' from register issue #{$issueNumber}: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * Assign people to a register issue, leaving anyone already assigned alone.
     *
     * Adding, never replacing, for the reason the labels are only added (#174):
     * someone may have assigned a person by hand, and the register is not this
     * plugin's to tidy up (#186).
     *
     * GitHub does not refuse a username it cannot assign — someone who is not
     * a member or collaborator of the register and has not commented on the
     * issue — it leaves them out. So the answer is read back: what this returns
     * is who is actually assigned now, and a requested name missing from it was
     * not assigned.
     *
     * @param string[] $usernames
     *
     * @throws ApiUpdateException when GitHub refuses the request.
     *
     * @return string[] Everyone assigned to the issue after the request
     */
    private function addAssigneesToIssue(int $issueNumber, array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }

        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            $issue = $this->client->api('issue')->assignees()->add(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                ['assignees' => array_values($usernames)]
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException(
                'Could not assign ' . implode(', ', $usernames)
                . " to register issue #{$issueNumber}: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }

        return array_values(array_filter(
            array_map(
                fn ($assignee) => is_array($assignee) ? ($assignee['login'] ?? null) : null,
                is_array($issue) ? ($issue['assignees'] ?? []) : []
            ),
            'is_string'
        ));
    }

    /**
     * Assign the codecheckers that have a GitHub username to a register issue
     * (#186), and answer who is assigned to it afterwards — or `null` when
     * GitHub could not be asked, which is not the same as nobody assigned: a
     * failed request says nothing about who is assigned already.
     *
     * Best-effort, and the one place that decides so: whatever wrote the issue
     * has already succeeded, and an assignment GitHub refuses must not be
     * reported as that write failing.
     *
     * @param mixed $codecheckers the list as the column holds it
     *
     * @return ?string[]
     */
    public function syncAssignees(int $issueNumber, mixed $codecheckers): ?array
    {
        $usernames = RegisterCodecheckers::usernames($codecheckers);
        if ($usernames === []) {
            return [];
        }

        try {
            $assigned = $this->addAssigneesToIssue($issueNumber, $usernames);
            CodecheckLogger::info(
                "Register issue #{$issueNumber}: asked to assign [" . implode(', ', $usernames)
                . '], assigned now [' . implode(', ', $assigned) . '].'
            );
            return $assigned;
        } catch (\Throwable $e) {
            CodecheckLogger::warning("Could not assign the codecheckers to register issue #{$issueNumber}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Rewrite the JSON metadata block in a register issue's body, leaving the
     * rest of the body as it stands (#186).
     *
     * Read first and written only when the JSON changed, so a save that
     * changed nothing the block carries costs one request and no edit on the
     * issue — not even a new update time. A body without the block is left
     * alone.
     *
     * @throws ApiUpdateException when GitHub refuses the edit.
     *
     * @return bool whether the body was rewritten
     */
    public function replaceIssueMetadataBlock(int $issueNumber, string $block): bool
    {
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            $body = (string) ($this->showIssue($issueNumber)['body'] ?? '');
            // The JSON alone decides: the block's update time differs on every
            // call, and a save that changed nothing it carries leaves it be.
            // A body without the block (null) is left alone; one written before
            // the block was marked is rewritten once, to carry the marker.
            $current = CodecheckGithubRegisterIssue::metadataJson($body);
            if ($current === null
                || ($current === CodecheckGithubRegisterIssue::metadataJson($block)
                    && CodecheckGithubRegisterIssue::hasMarkedMetadataBlock($body))) {
                return false;
            }
            $updated = CodecheckGithubRegisterIssue::withMetadataBlock($body, $block);

            $this->client->api('issue')->update(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                ['body' => $updated]
            );

            return true;
        } catch (\Throwable $e) {
            throw new ApiUpdateException(
                "Could not rewrite the metadata of register issue #{$issueNumber}: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * A register issue as GitHub holds it.
     */
    private function showIssue(int $issueNumber): array
    {
        $issue = $this->client->api('issue')->show(
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $issueNumber
        );

        return is_array($issue) ? $issue : [];
    }

    /**
     * Write a comment under a register issue.
     *
     * The register issue's title, body and labels are rewritten in place as a
     * CODECHECK progresses, which leaves no trace of when anything changed. A
     * comment is the part GitHub shows as a timeline, so a status change can be
     * followed by anyone reading the register (#150).
     *
     * @throws ApiUpdateException when GitHub refuses the comment.
     */
    public function commentOnIssue(int $issueNumber, string $body): void
    {
        $body .= $this->origin->signature();

        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        try {
            $this->client->api('issue')->comments()->create(
                $this->githubRegisterOrganization,
                $this->githubRegisterRepository,
                $issueNumber,
                ['body' => $body]
            );
        } catch (\Throwable $e) {
            throw new ApiUpdateException(
                'Could not comment on register issue #' . $issueNumber . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Updates the register issue that carries this Certificate Identifier
     *
     * @param array $updateInformation Which parts of the issue to rewrite
     * @param int $issueNumber The Number of the corresponding GitHub Issue
     * @param CertificateIdentifier $certificateIdentifier The Certificate identifier the issue carries
     * @param CodecheckIssueLabels $codecheckIssueLabels The CODECHECK Issue Labels that will be updated
     * @param string $paperTitle The Title of the submitted paper / preprint / article
     * @param string $authorString The formatted author string e.g. `author name et al.`
     *
     * @return array Returns the GitHub URL & Issue Number of the updated issue
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
        // The configured PAT, like every other call here. This read $_ENV, which
        // is only populated when a .env happens to exist — so updating a
        // register issue failed on an ordinary install (#150).
        $this->client->authenticate($this->githubPAT, null, Client::AUTH_ACCESS_TOKEN);

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            $this->githubRegisterOrganization,
            $this->githubRegisterRepository,
            $certificateIdentifier,
            $codecheckIssueLabels,
            $paperTitle,
            $this->origin,
            $authorString,
            $this->submissionID,
            $codecheckers,
            $repositories,
            $updateInformation
        );

        $issueContents = [];

        if (in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_TITLE, $updateInformation)) {
            $issueContents['title'] = $codecheckIssue->getTitle();
        }

        try {
            if (in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY, $updateInformation)) {
                // The form asks for this after every save: a block whose JSON
                // did not change keeps its time, and a body that would not
                // change is not sent, so the issue shows no edit for it.
                $currentBody = (string) ($this->showIssue($issueNumber)['body'] ?? '');
                $body = CodecheckGithubRegisterIssue::keepingUnchangedMetadataBlock($codecheckIssue->getBody(), $currentBody);
                if ($body !== str_replace("\r\n", "\n", $currentBody)) {
                    $issueContents['body'] = $body;
                }
            }

            $issue = empty($issueContents)
                // Nothing the journal asked to keep up to date, so nothing to send.
                ? $this->showIssue($issueNumber)
                : $this->client->api('issue')->update(
                    $this->githubRegisterOrganization,
                    $this->githubRegisterRepository,
                    $issueNumber,
                    $issueContents,
                );
        } catch (\Throwable $e) {
            throw new ApiUpdateException("Error while updating GitHub issue #{$issueNumber} with the Certificate Identifier: " . $certificateIdentifier->toStr() . "\n" . $e->getMessage(), $e->getCode());
        }

        // The labels the form offers are **added**, never written as the whole
        // list. Sending `labels` in the PATCH above replaced everything on the
        // issue, so pressing "update issue" wiped the status labels the plugin
        // had just set, along with `buddy exchange`, `help welcome`,
        // `metadata pending` and anything else a human had added (#174).
        //
        // Best-effort, and deliberately after the update: the title and body
        // have already landed, so a refused label must not be reported to the
        // editor as the whole update failing — they would press save again and
        // rewrite what already succeeded.
        $selectedLabels = $codecheckIssueLabels->get()->toArray();

        if (!empty($selectedLabels)) {
            try {
                $this->addLabelsToIssue($issueNumber, $codecheckIssue->getLabels());
            } catch (\Throwable $e) {
                CodecheckLogger::warning(
                    "Updated register issue #{$issueNumber}, but could not add its labels: " . $e->getMessage()
                );
            }
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
     *
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
            throw new ApiFetchException("Failed fetching '{$registerFilePath}' from the register repository.\n" . $e->getMessage());
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
            throw new ApiCreateException("Failed creating the branch '{$branchName}' for the register deposit.\n" . $e->getMessage(), $e->getCode());
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
            throw new ApiUpdateException("Failed committing the updated '{$registerFilePath}' to branch '{$branchName}'.\n" . $e->getMessage(), $e->getCode());
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
            fn ($value) => str_contains($value, ',') ? '"' . str_replace('"', '""', $value) . '"' : $value,
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
            $body .= "| {$column} | {$value} |\n";
        }
        return $body . $this->origin->signature();
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
}
