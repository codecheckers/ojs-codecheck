<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use Illuminate\Support\Facades\DB;

class CodecheckMetadataHandler
{
    private CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getMetadata($request, $submissionId): array
    {
        error_log("[CODECHECK Metadata] getMetadata called for submission: $submissionId");
        
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
                'configVersion' => $metadata->config_version ?? 'latest',
                'publicationType' => $metadata->publication_type ?? 'doi',
                'manifest' => json_decode($metadata->manifest ?? '[]', true),
                'repository' => $metadata->repository,
                'codecheckers' => json_decode($metadata->codecheckers ?? '[]', true),
                'certificate' => $metadata->certificate,
                'checkTime' => $metadata->check_time,
                'summary' => $metadata->summary,
                'reportUrl' => $metadata->report_url,
            ] : null
        ];

        error_log("[CODECHECK Metadata] Response: " . json_encode($response));
        
        return $response;
    }

    public function saveMetadata($request, $submissionId): array
    {
        error_log("[CODECHECK Metadata] saveMetadata called for submission: $submissionId");
        
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['success' => false, 'error' => 'Submission not found'];
        }

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        
        error_log("[CODECHECK Metadata] Received data: " . $jsonData);

        $nullIfEmpty = function($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };
        $metadataData = [
            'submission_id' => $submissionId,
            'config_version' => $data['config_version'] ?? 'latest',
            'publication_type' => $data['publication_type'] ?? 'doi',
            'manifest' => json_encode($data['manifest'] ?? []),
            'repository' => $nullIfEmpty($data['repository'] ?? null),
            'codecheckers' => json_encode($data['codecheckers'] ?? []),
            'certificate' => $nullIfEmpty($data['certificate'] ?? null),
            'check_time' => $nullIfEmpty($data['check_time'] ?? null),
            'summary' => $nullIfEmpty($data['summary'] ?? null),    
            'report_url' => $nullIfEmpty($data['report_url'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $exists = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->exists();

        if ($exists) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($metadataData);
            error_log("[CODECHECK Metadata] Updated existing record");
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
            error_log("[CODECHECK Metadata] Created new record");
        }

        return [
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully'
        ];
    }

    public function generateYaml($request, $submissionId): array
    {
        error_log("[CODECHECK Metadata] generateYaml called for submission: $submissionId");
        
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

    private function getAuthors($publication): array
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

    private function escapeYaml(string $text): string
    {
        return str_replace('"', '\\"', $text);
    }
}