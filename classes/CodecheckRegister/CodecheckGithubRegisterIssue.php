<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenue;

class CodecheckGithubRegisterIssue {
    private string $repositoryOwner;
    private string $repository;
    private string $title;
    private string $body;
    private string $submissionID;
    private array $labels;
    private string $jsonEncodedCodecheckMetadata;

    public function __construct(
        string $repositoryOwner,
        string $repository,
        CertificateIdentifier $certificateIdentifier,
        CodecheckVenue $codecheckVenue,
        string $paperTitle,
        string $journalName,
        string $authorString,
        string $submissionID
    ){
        $this->repositoryOwner = $repositoryOwner;
        $this->repository = $repository;
        $this->submissionID = $submissionID;
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        $this->title = $this->createTitleMarkdown($authorString, $certificateIdentifier);
        $this->jsonEncodedCodecheckMetadata = $this->createJsonEncodedCodecheckMetadataMarkdown($authorString, $certificateIdentifier, $journalName, $submissionID);
        $this->body = $this->createBodyMarkdown($paperTitle, $journalName) . "\n" . $this->jsonEncodedCodecheckMetadata;
        $this->labels = $this->createLabelsFromVenue($codecheckVenue);
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
        string $submissionID
    ): string
    {
        return "<details>\n<summary><h3>JSON encoded CODECHECK metadata</h3></summary>\n\n"
        . "```json\n"
        . "{"
        . "\n\t\"identifier\": \"" . $certificateIdentifier->toStr() . "\","
        . "\n\t\"repository\": [],"
        . "\n\t\"codecheckers\": [],"
        . "\n\t\"links\": [],"
        . "\n\t\"journal\": {\"name\": \"" . $journalName . "\", \"submissionID\": $submissionID},"
        . "\n}"
        . "\n```"
        . "\n\n</details>";
    }

    private function createBodyMarkdown(
        string $paperTitle,
        string $journalName
    ): string
    {
        return "<!-- Provide the title of your published paper or preprint -->\n## " . $paperTitle . "\n\n"
        . "<!-- Provide a link to your published paper or preprint, ideally with a DOI -->\n**Article:**\n\n"
        . "<!-- Information about the Journal in which the paper/ preprint is published -->\n**Journal:** " . $journalName . " *(Submission ID: " . $this->submissionID . ")*\n\n"
        . "<!-- Provide a link to your code (and data) repository(s) (GitHub, GitLab, etc.) -->\n**Repository:**";
    }

    private function createLabelsFromVenue(
        CodecheckVenue $venue
    ): array
    {
        $labels = ['id assigned'];
        $labels[] = $venue->getVenueType();
        $labels[] = $venue->getVenueName();

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