<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Request;

class CodecheckMetadataHandler
{
    private mixed $submissionId;

    /**
     * `CodecheckMetadataHandler`
     * @param \APP\core\Request $request The API Request
     */
    public function __construct(Request $request)
    {
        $this->submissionId = $request->getUserVar('submissionId');
    }

    /**
     * Get the submission ID
     * @return mixed Returns the Submission ID for the Request that was passed in the constructor
     */
    public function getSubmissionId(): mixed
    {
        return $this->submissionId;
    }

    /**
     * Build the Yaml file from the publication and metadata contents
     * @param mixed $publication The publication data
     * @param mixed $metadata The metadata information from the Database
     * @return string The fully generated Yaml as a string
     */
    public function buildYaml($publication, $metadata): string
    {
        $manifest = json_decode($metadata->manifest ?? '[]', true);
        $codecheckers = json_decode($metadata->codecheckers ?? '[]', true);

        $yaml = "---\n";
        $yaml .= "version: https://codecheck.org.uk/spec/config/1.0/\n";
        $yaml .= "paper:\n";
        $yaml .= "  title: \"" . $this->escapeYaml($publication->getLocalizedTitle()) . "\"\n";
        $yaml .= "  authors:\n";

        foreach ($publication->getData('authors') as $author) {
            $yaml .= "    - name: " . $this->escapeYaml($author->getFullName()) . "\n";
            if ($author->getOrcid()) {
                $yaml .= "      ORCID: " . $author->getOrcid() . "\n";
            }
        }

        $doi = $publication->getStoredPubId('doi');
        if ($doi) {
            $yaml .= "  reference: https://doi.org/" . $doi . "\n";
        }

        $yaml .= "manifest:\n";
        foreach ($manifest as $file) {
            $yaml .= "  - file: " . $this->escapeYaml($file['file'] ?? '') . "\n";
            $yaml .= "    comment: \"" . $this->escapeYaml($file['comment'] ?? '') . "\"\n";
        }

        $yaml .= "codechecker:\n";
        foreach ($codecheckers as $checker) {
            $yaml .= "  - name: " . $this->escapeYaml($checker['name'] ?? '') . "\n";
            if (!empty($checker['orcid'])) {
                $yaml .= "    ORCID: " . $checker['orcid'] . "\n";
            }
        }

        if ($metadata->summary) {
            $yaml .= "summary: >\n";
            foreach (explode("\n", $metadata->summary) as $line) {
                $yaml .= "  " . $line . "\n";
            }
        }

        if ($metadata->repository) {
            $yaml .= "repository: " . $metadata->repository . "\n";
        }

        if ($metadata->check_time) {
            $yaml .= "check_time: \"" . $metadata->check_time . "\"\n";
        }

        if ($metadata->certificate) {
            $yaml .= "certificate: " . $metadata->certificate . "\n";
        }

        if ($metadata->report_url) {
            $yaml .= "report: " . $metadata->report_url . "\n";
        }

        return $yaml;
    }

    /**
     * Get the Authors for a specific publication
     * @param mixed $publication The publication data
     * @return array The Authors with Name and ORCID (if isset) in an Array
     */
    public function getAuthors($publication): array
    {
        if (!$publication) {
            return [];
        }
        
        $authors = [];
        foreach ($publication->getData('authors') as $author) {
            $authors[] = [
                'name' => $author->getFullName(),
                'orcid' => $author->getOrcid()
            ];
        }
        return $authors;
    }

    /**
     * Escape Yaml Content
     * @param string $content The Yaml Content to be escaped
     * @return string The Escaped Yaml Content
     */
    private function escapeYaml(string $content): string
    {
        return str_replace('"', '\\"', $content);
    }
}