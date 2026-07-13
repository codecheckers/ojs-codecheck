<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\classes\Constants;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;

class CodecheckGithubRegisterIssue {
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
        string $journalName,
        string $authorString,
        string $submissionID,
        array $codecheckers,
        array $repositories,
        array $updateInformation
    ){
        $this->repositoryOwner = $repositoryOwner;
        $this->repository = $repository;
        $this->submissionID = $submissionID;
        $this->codecheckStatus = '';
        $this->updateStatus = false;
        if(in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $updateInformation)) {
            $this->updateStatus = true;
            $this->codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->submissionID)->status;
        }
        $updateCodecheckStatus = $this->updateStatus ? 'true' : 'false';
        CodecheckLogger::debug("Record / Update Status: " . $updateCodecheckStatus);
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        $this->title = $this->createTitleMarkdown($authorString, $certificateIdentifier);
        $this->jsonEncodedCodecheckMetadata = $this->createJsonEncodedCodecheckMetadataMarkdown($authorString, $certificateIdentifier, $journalName, $submissionID, $codecheckers, $repositories);
        $this->body = $this->createBodyMarkdown($paperTitle, $journalName, $repositories) . "\n" . $this->jsonEncodedCodecheckMetadata;
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
    ): string
    {
        return $authorString . ' | ' . $certificateIdentifier->toStr();
    }

    private function createJsonEncodedCodecheckMetadataMarkdown(
        string $authorString,
        CertificateIdentifier $certificateIdentifier,
        string $journalName,
        string $submissionID,
        array $codecheckers,
        array $repositories
    ): string
    {
        $statusInformation = $this->updateStatus ? "\n\t\"status\": \"" . $this->codecheckStatus . "\"," : "";
        return "<details>\n<summary><h3>JSON encoded CODECHECK metadata</h3></summary>\n\n"
        . "```json\n"
        . "{"
        . "\n\t\"identifier\": \"" . $certificateIdentifier->toStr() . "\","
        . $statusInformation
        . "\n\t\"repositories\": " . json_encode($repositories) . ","
        . "\n\t\"codecheckers\": " . json_encode($codecheckers) . ","
        . "\n\t\"links\": [],"
        . "\n\t\"journal\": {\"name\": \"" . $journalName . "\", \"submissionID\": $submissionID},"
        . "\n}"
        . "\n```"
        . "\n\n</details>";
    }

    private function createBodyMarkdown(
        string $paperTitle,
        string $journalName,
        array $repositories
    ): string
    {
        $repoStr = "";
        foreach ($repositories as $repo) {
            $repoStr .= "\t- " . $repo . "\n";
        }
        $statusInformation = $this->updateStatus ? "<!-- The current status of the CODECHECK -->\n**CODECHECK Status:** " . __($this->codecheckStatus) . "\n\n" : "";
        
        return "<!-- Provide the title of your published paper or preprint -->\n## " . $paperTitle . "\n\n"
        . "<!-- Provide a link to your published paper or preprint, ideally with a DOI -->\n**Article:**\n\n"
        . "<!-- Information about the Journal in which the paper/ preprint is published -->\n**Journal:** " . $journalName . " *(Submission ID: " . $this->submissionID . ")*\n\n"
        . "<!-- Provide a link to your code (and data) repository(s) (GitHub, GitLab, etc.) -->\n**Repositories:**\n" . $repoStr . "\n\n"
        . $statusInformation;
    }

    private function fillLabels(
        CodecheckIssueLabels $codecheckIssueLabels
    ): array
    {
        $labels = ['id assigned'];
        $labels = array_merge($labels, $codecheckIssueLabels->get()->toArray());

        return $labels;
    }

    private function getFormattedLabelsForUrl(): string
    {
        $labels = "";
        $countLabels = 0;
        foreach($this->labels as $label) {
            $labels = $labels . rawurlencode($label);

            if($countLabels < count($this->labels) - 1) {
                $labels = $labels  . ",";
            }

            $countLabels++;
        }

        return $labels;
    }

    public function getNewIssueUrl(): string
    {
        $url = "https://github.com/$this->repositoryOwner/$this->repository/issues/new";
        $queryTitle = "title=" . rawurlencode($this->title);
        $queryBody = "body=" . rawurlencode($this->body);
        $queryLabels = "labels=" . $this->getFormattedLabelsForUrl();

        return $url . "?" . $queryTitle . "&" . $queryBody . "&" . $queryLabels;
    }
}