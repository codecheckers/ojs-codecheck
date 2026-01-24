<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class CodecheckMetadataHandler
{
    private mixed $submissionId;

    /**
     * `CodecheckMetadataHandler`
     * @param \APP\core\Request $request The API Request
     */
    public function __construct(\APP\core\Request $request)
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

    public function getMetadata($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();
        
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        $response = [
            'submissionId' => $submissionId,
            'submission' => [
                'id' => $submission->getId(),
                'title' => $publication ? $publication->getLocalizedTitle() : '',
                'authors' => $this->getAuthors($publication),
                'doi' => $publication ? $publication->getStoredPubId('doi') : null,
                'codeRepository' => $submission->getData('codeRepository'),
                'dataRepository' => $submission->getData('dataRepository'),
                'manifestFiles' => $submission->getData('manifestFiles'),
                'dataAvailabilityStatement' => $submission->getData('dataAvailabilityStatement'),
            ],
            'codecheck' => $metadata ? [
                'version' => $metadata->version ?? 'latest',
                'publicationType' => $metadata->publication_type ?? 'doi',
                'manifest' => json_decode($metadata->manifest ?? '[]', true),
                'repository' => $metadata->repository,
                'codecheckers' => json_decode($metadata->codecheckers ?? '[]', true),
                'source' => $metadata->source,
                'certificate' => $metadata->certificate,
                'check_time' => $metadata->check_time,
                'summary' => $metadata->summary,
                'report' => $metadata->report,
                'additionalContent' => $metadata->additional_content,
            ] : null
        ];
        
        return $response;
    }

    public function saveMetadata($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['success' => false, 'error' => 'Submission not found'];
        }

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        
        $nullIfEmpty = function($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };
        
        $metadataData = [
            'submission_id' => $submissionId,
            'version' => $data['version'] ?? 'latest',
            'publication_type' => $data['publication_type'] ?? 'doi',
            'manifest' => json_encode($data['manifest'] ?? []),
            'repository' => $nullIfEmpty($data['repository'] ?? null),
            'source' => $nullIfEmpty($data['source'] ?? null),
            'codecheckers' => json_encode($data['codecheckers'] ?? []),
            'certificate' => $nullIfEmpty($data['certificate'] ?? null),
            'check_time' => $nullIfEmpty($data['check_time'] ?? null),
            'summary' => $nullIfEmpty($data['summary'] ?? null),    
            'report' => $nullIfEmpty($data['report'] ?? null),
            'additional_content' => $nullIfEmpty($data['additional_content'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $exists = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->exists();

        if ($exists) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($metadataData);
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
        }

        return [
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully'
        ];
    }

    public function generateYaml($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();
        
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        if (!$metadata) {
            return ['error' => 'No CODECHECK metadata found'];
        }

        $yaml = $this->buildYaml($publication, $metadata);

        return [
            'yaml' => $yaml,
            'filename' => 'codecheck.yml'
        ];
    }

    private function buildYaml($publication, $metadata): string
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
    private function getAuthors($publication): array
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