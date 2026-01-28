<?php
namespace APP\plugins\generic\codecheck\classes\Submission;

use Illuminate\Support\Facades\DB;

class CodecheckSubmissionDAO
{
    /**
     * Get CODECHECK data by submission ID
     */
    public function getBySubmissionId(int $submissionId): ?CodecheckSubmission
    {
        $result = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        if ($result) {
            return new CodecheckSubmission((array) $result);
        }

        return null;
    }

    /**
     * Insert or update CODECHECK data
     */
    public function insertOrUpdate(int $submissionId, array $data): void
    {
        $existing = $this->getBySubmissionId($submissionId);

        $recordData = [
            'version' => $data['version'] ?? 'latest',
            'publication_type' => $data['publication_type'] ?? 'doi',
            'manifest' => isset($data['manifest']) ? json_encode($data['manifest']) : null,
            'repository' => $data['repository'] ?? '',
            'source' => $data['source'] ?? '',
            'codecheckers' => isset($data['codecheckers']) ? json_encode($data['codecheckers']) : null,
            'certificate' => $data['certificate'] ?? '',
            'check_time' => $data['check_time'] ?? null,
            'summary' => $data['summary'] ?? '',
            'report' => $data['report'] ?? '',
            'additional_content' => $data['additional_content'] ?? '',
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($recordData);
        } else {
            $recordData['submission_id'] = $submissionId;
            $recordData['created_at'] = now();
            DB::table('codecheck_metadata')->insert($recordData);
        }
    }
}

/**
 * CODECHECK submission data object
 */
class CodecheckSubmission
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getSubmissionId(): int 
    { 
        return (int) $this->data['submission_id']; 
    }

    public function getVersion(): string 
    { 
        return $this->data['version'] ?? 'latest'; 
    }

    public function getPublicationType(): string 
    { 
        return $this->data['publication_type'] ?? 'doi'; 
    }

    public function getManifest(): array 
    { 
        if (empty($this->data['manifest'])) {
            return [];
        }
        $decoded = json_decode($this->data['manifest'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getRepository(): string 
    { 
        return $this->data['repository'] ?? ''; 
    }

    public function getSource(): string 
    { 
        return $this->data['source'] ?? ''; 
    }

    public function getCodecheckers(): array 
    { 
        if (empty($this->data['codecheckers'])) {
            return [];
        }
        $decoded = json_decode($this->data['codecheckers'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getCertificate(): string 
    { 
        return $this->data['certificate'] ?? ''; 
    }

    public function getCheckTime(): ?string 
    { 
        return $this->data['check_time'] ?? null; 
    }

    public function getSummary(): string 
    { 
        return $this->data['summary'] ?? ''; 
    }

    public function getReport(): string 
    { 
        return $this->data['report'] ?? ''; 
    }

    public function getAdditionalContent(): string 
    { 
        return $this->data['additional_content'] ?? ''; 
    }

    // Legacy getters for backward compatibility
    public function getCodeRepository(): string 
    { 
        return $this->getRepository(); 
    }

    public function getDataRepository(): string 
    { 
        return ''; // Not in new schema
    }

    public function getCodecheckerNames(): string 
    { 
        $codecheckers = $this->getCodecheckers();
        if (empty($codecheckers)) {
            return '';
        }
        return implode(', ', array_column($codecheckers, 'name'));
    }

    public function getCertificateDate(): ?string 
    { 
        return $this->getCheckTime(); 
    }

    /**
     * Check if this submission has a completed CODECHECK
     */
    public function hasCompletedCheck(): bool 
    {
        return !empty($this->getCertificate());
    }

    /**
     * Check if a codechecker has been assigned to this submission
     */
    public function hasAssignedChecker(): bool
    {
        return !empty($this->getCodecheckers());
    }

    /**
     * Get the primary certificate link
     */
    public function getCertificateLink(): string 
    {
        $certificate = $this->getCertificate();
        
        // If it's already a URL, return it
        if (filter_var($certificate, FILTER_VALIDATE_URL)) {
            return $certificate;
        }
        
        // If it's a CODECHECK ID, build the URL
        if (preg_match('/^CODECHECK-\d{4}-\d+$/', $certificate)) {
            return 'https://codecheck.org.uk/certificate/' . $certificate;
        }
        
        return '';
    }

    /**
     * Get DOI link if available
     */
    public function getDoiLink(): string 
    {
        $report = $this->getReport();
        
        // If report is empty, return empty
        if (empty($report)) {
            return '';
        }
        
        // Check if report is a valid DOI format
        if (preg_match('/^(https?:\/\/)?(doi\.org\/)?(.+)$/', $report, $matches)) {
            $doi = $matches[3];
            
            // Validate DOI format (should contain at least one slash, e.g., 10.xxxx/yyyy)
            if (strpos($doi, '/') !== false && preg_match('/^10\.\d+\//', $doi)) {
                return 'https://doi.org/' . $doi;
            }
        }
        
        // Return raw value as fallback
        return $report;
    }
}