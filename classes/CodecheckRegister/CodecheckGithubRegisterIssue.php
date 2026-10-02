<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusRegisterUpdate;

class CodecheckGithubRegisterIssue
{
    private string $repositoryOwner;
    private string $repository;
    private string $title;
    private string $body;
    private string $submissionID;
    private array $labels;
    private string $jsonEncodedCodecheckMetadata;
    private string $codecheckStatus;
    private bool $updateStatus;

    public function __construct(
        string $repositoryOwner,
        string $repository,
        CertificateIdentifier $certificateIdentifier,
        CodecheckIssueLabels $codecheckIssueLabels,
        string $paperTitle,
        CodecheckPostOrigin $origin,
        string $authorString,
        string $submissionID,
        array $codecheckers,
        array $repositories,
        array $updateInformation
    ) {
        $this->repositoryOwner = $repositoryOwner;
        $this->repository = $repository;
        $this->submissionID = $submissionID;
        $this->codecheckStatus = '';
        $this->updateStatus = false;
        if (in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $updateInformation)) {
            $this->updateStatus = true;
            $this->codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->submissionID)->status;
        }
        $updateCodecheckStatus = $this->updateStatus ? 'true' : 'false';
        CodecheckLogger::debug('Record / Update Status: ' . $updateCodecheckStatus);
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        // The issue is public. A repository marked hidden is part of the record
        // but must never reach a reader, and the editorial form posts whole
        // entries — which would otherwise be published verbatim, hidden ones and
        // all, along with their internal flags (Issue #154).
        $repositories = CodecheckRepositories::publicUrls($repositories);
        $this->title = $this->createTitleMarkdown($authorString, $certificateIdentifier);
        $this->jsonEncodedCodecheckMetadata = $this->createJsonEncodedCodecheckMetadataMarkdown($certificateIdentifier, $origin, $submissionID, $codecheckers, $repositories);
        // The body is rendered whole on every update, so the signature closes
        // it once and is never stacked.
        $this->body = $this->createBodyMarkdown($paperTitle, $origin->getJournalName(), $repositories, $codecheckers)
            . "\n" . $this->jsonEncodedCodecheckMetadata
            . $origin->signature();
        $this->labels = $this->fillLabels($codecheckIssueLabels);
    }

    public function getRepositoryOwner(): string
    {
        return $this->repositoryOwner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    private function createTitleMarkdown(
        string $authorString,
        CertificateIdentifier $certificateIdentifier
    ): string {
        return $authorString . ' | ' . $certificateIdentifier->toStr();
    }

    /**
     * The record as JSON, for whoever processes the register by machine.
     *
     * Built as an array and encoded, where it used to be concatenated: that gave
     * a trailing comma after `journal` and left the journal name unescaped, so
     * the block was never valid JSON. `journal.url`, `journal.ojsVersion` and
     * `plugin` say which installation wrote it.
     */
    private function createJsonEncodedCodecheckMetadataMarkdown(
        CertificateIdentifier $certificateIdentifier,
        CodecheckPostOrigin $origin,
        string $submissionID,
        array $codecheckers,
        array $repositories
    ): string {
        $metadata = ['identifier' => $certificateIdentifier->toStr()];
        if ($this->updateStatus) {
            $metadata['status'] = $this->codecheckStatus;
        }
        $metadata += [
            'repositories' => array_values($repositories),
            // In the stored shape, whatever the form sent: name, bare ORCID iD
            // and GitHub username (#186).
            'codecheckers' => CodecheckCodecheckers::withNormalizedEntries($codecheckers),
            'links' => [],
            'journal' => $origin->journalMetadata() + ['submissionID' => (int) $submissionID],
            'plugin' => $origin->pluginMetadata(),
        ];

        return "<details>\n<summary><h3>JSON encoded CODECHECK metadata</h3></summary>\n\n"
        . "```json\n"
        // A backtick as \u0060, which is the same string in JSON: a name holding
        // three of them would otherwise close the fence early.
        . str_replace('`', '\u0060', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE))
        . "\n```"
        . "\n\n</details>";
    }

    private function createBodyMarkdown(
        string $paperTitle,
        string $journalName,
        array $repositories,
        array $codecheckers
    ): string {
        $repoStr = '';
        foreach ($repositories as $repo) {
            $repoStr .= "\t- " . $repo . "\n";
        }
        // Named without a mention: the body is rewritten on every update, and
        // the assignment is what says on GitHub who is checking (#186).
        $codecheckerNames = RegisterCodecheckers::describeAll($codecheckers);
        $codecheckerInformation = $codecheckerNames === ''
            ? ''
            : "<!-- Who is checking -->\n**Codecheckers:** " . $codecheckerNames . "\n\n";
        $statusInformation = $this->updateStatus ? "<!-- The current status of the CODECHECK -->\n**CODECHECK Status:** " . __($this->codecheckStatus) . "\n\n" : '';

        return "<!-- Provide the title of your published paper or preprint -->\n## " . $paperTitle . "\n\n"
        . "<!-- Provide a link to your published paper or preprint, ideally with a DOI -->\n**Article:**\n\n"
        . "<!-- Information about the Journal in which the paper/ preprint is published -->\n**Journal:** " . $journalName . ' *(Submission ID: ' . $this->submissionID . ")*\n\n"
        . "<!-- Provide a link to your code (and data) repository(s) (GitHub, GitLab, etc.) -->\n**Repositories:**\n" . $repoStr . "\n\n"
        . $codecheckerInformation
        . $statusInformation;
    }

    /**
     * The labels the issue carries: the identifier label, the ones the status
     * asks for, and the venue labels the editor chose.
     *
     * The status labels belong here as well as on a later status change (#174),
     * because the ordinary order of work records a status *before* an identifier
     * is reserved — so the issue would otherwise be opened without
     * `needs codechecker`, which is exactly the label someone looking for work
     * in the register filters on, and no later change would add it.
     */
    private function fillLabels(
        CodecheckIssueLabels $codecheckIssueLabels
    ): array {
        $labels = [Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL];

        if ($this->updateStatus) {
            $labels = array_merge(
                $labels,
                CodecheckStatusRegisterUpdate::wantedLabels($this->codecheckStatus) ?? []
            );
        }

        $labels = array_merge($labels, $codecheckIssueLabels->get()->toArray());

        return array_values(array_unique($labels));
    }

    private function getFormattedLabelsForUrl(): string
    {
        $labels = '';
        $countLabels = 0;
        foreach ($this->labels as $label) {
            $labels = $labels . rawurlencode($label);

            if ($countLabels < count($this->labels) - 1) {
                $labels = $labels . ',';
            }

            $countLabels++;
        }

        return $labels;
    }

    public function getNewIssueUrl(): string
    {
        $url = "https://github.com/{$this->repositoryOwner}/{$this->repository}/issues/new";
        $queryTitle = 'title=' . rawurlencode($this->title);
        $queryBody = 'body=' . rawurlencode($this->body);
        $queryLabels = 'labels=' . $this->getFormattedLabelsForUrl();

        return $url . '?' . $queryTitle . '&' . $queryBody . '&' . $queryLabels;
    }
}
