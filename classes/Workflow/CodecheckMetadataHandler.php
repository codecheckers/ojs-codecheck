<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use \APP\core\Request;
use Symfony\Component\Yaml\Yaml;

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

    public function buildYaml($publication, $metadata): string
    {
        $manifest = json_decode($metadata->manifest ?? '[]', true);
        $codecheckers = json_decode($metadata->codecheckers ?? '[]', true);

        // Build YAML data structure
        $data = [
            'version' => 'https://codecheck.org.uk/spec/config/1.0/'
        ];

        // Add source if present
        if ($metadata->source) {
            $data['source'] = $metadata->source;
        }

        // Paper section
        $authors = [];
        foreach ($publication->getData('authors') as $author) {
            $locale = $author->getDefaultLocale();
            $givenName = $author->getGivenName($locale) ?? '';
            $familyName = $author->getFamilyName($locale) ?? '';
            $fullName = trim($givenName . ' ' . $familyName);
            
            $authorData = ['name' => $fullName];
            if ($author->getOrcid()) {
                $authorData['ORCID'] = $author->getOrcid();
            }
            $authors[] = $authorData;
        }

        $paperData = [
            'title' => $publication->getLocalizedTitle(),
            'authors' => $authors
        ];

        $doi = $publication->getStoredPubId('doi');
        if ($doi) {
            $paperData['reference'] = 'https://doi.org/' . $doi;
        }

        $data['paper'] = $paperData;

        // Manifest section
        $manifestData = [];
        foreach ($manifest as $file) {
            $fileData = ['file' => $file['file'] ?? ''];
            if (!empty($file['comment'])) {
                $fileData['comment'] = $file['comment'];
            }
            $manifestData[] = $fileData;
        }
        $data['manifest'] = $manifestData;

        // Codechecker section
        $codecheckerData = [];
        foreach ($codecheckers as $checker) {
            $checkerData = ['name' => $checker['name'] ?? ''];
            if (!empty($checker['orcid'])) {
                $checkerData['ORCID'] = $checker['orcid'];
            }
            $codecheckerData[] = $checkerData;
        }
        $data['codechecker'] = $codecheckerData;

        // Summary
        if ($metadata->summary) {
            $data['summary'] = $metadata->summary;
        }

        // Repository
        if ($metadata->repository) {
            $data['repository'] = $metadata->repository;
        }

        // Check time
        if ($metadata->check_time) {
            $data['check_time'] = $metadata->check_time;
        }

        // Certificate
        if ($metadata->certificate) {
            $data['certificate'] = $metadata->certificate;
        }

        // Report
        if ($metadata->report) {
            $data['report'] = $metadata->report;
        }

        // Generate YAML
        $yaml = "---\n" . Yaml::dump($data, 4, 2);

        // Add custom additional content at the end if present
        if ($metadata->additional_content) {
            $yaml .= "\n" . trim($metadata->additional_content) . "\n";
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
            $locale = $author->getDefaultLocale();
            $givenName = $author->getGivenName($locale) ?? '';
            $familyName = $author->getFamilyName($locale) ?? '';
            $fullName = trim($givenName . ' ' . $familyName);

            $authors[] = [
                'name' => $fullName,    
                'orcid' => $author->getOrcid()
            ];
        }
        return $authors;
    }
}