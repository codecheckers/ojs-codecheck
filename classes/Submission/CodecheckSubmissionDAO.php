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

        if ($existing) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update([
                    'opt_in' => $data['opt_in'] ? 1 : 0,
                    'code_repository' => $data['code_repository'] ?? '',
                    'data_repository' => $data['data_repository'] ?? '',
                    'dependencies' => $data['dependencies'] ?? '',
                    'execution_instructions' => $data['execution_instructions'] ?? '',
                    'certificate_doi' => $data['certificate_doi'] ?? '',
                    'certificate_url' => $data['certificate_url'] ?? '',
                    'codechecker_names' => $data['codechecker_names'] ?? '',
                    'check_status' => $data['check_status'] ?? '',
                    'certificate_date' => $data['certificate_date'] ?? null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('codecheck_metadata')->insert([
                'submission_id' => $submissionId,
                'opt_in' => $data['opt_in'] ? 1 : 0,
                'code_repository' => $data['code_repository'] ?? '',
                'data_repository' => $data['data_repository'] ?? '',
                'dependencies' => $data['dependencies'] ?? '',
                'execution_instructions' => $data['execution_instructions'] ?? '',
                'certificate_doi' => $data['certificate_doi'] ?? '',
                'certificate_url' => $data['certificate_url'] ?? '',
                'codechecker_names' => $data['codechecker_names'] ?? '',
                'check_status' => $data['check_status'] ?? '',
                'certificate_date' => $data['certificate_date'] ?? null,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Create object from database row
     */
    private function fromRow(array $row): CodecheckSubmission
    {
        return new CodecheckSubmission($row);
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

    public function getSubmissionId(): int { return (int) $this->data['submission_id']; }
    public function getOptIn(): bool { return (bool) $this->data['opt_in']; }
    public function getCodeRepository(): string { return $this->data['code_repository'] ?? ''; }
    public function getDataRepository(): string { return $this->data['data_repository'] ?? ''; }
    public function getDependencies(): string { return $this->data['dependencies'] ?? ''; }
    public function getExecutionInstructions(): string { return $this->data['execution_instructions'] ?? ''; }
    
    // New certificate getters
    public function getCertificateDoi(): string { return $this->data['certificate_doi'] ?? ''; }
    public function getCertificateUrl(): string { return $this->data['certificate_url'] ?? ''; }
    public function getCodecheckerNames(): string { return $this->data['codechecker_names'] ?? ''; }
    public function getCheckStatus(): string { return $this->data['check_status'] ?? ''; }
    public function getCertificateDate(): ?string { return $this->data['certificate_date'] ?? null; }
    
    /**
     * Check if this submission has a completed CODECHECK
     */
    public function hasCompletedCheck(): bool {
        return !empty($this->getCertificateDoi()) || !empty($this->getCertificateUrl());
    }

    /**
     * Check if a codechecker has been assigned to this submission
     * 
     * @return bool
     */
    public function hasAssignedChecker(): bool
    {
        return false;
    }

    /**
     * Get the primary certificate link (prefer DOI over URL)
     */
    public function getCertificateLink(): string {
        if (!empty($this->getCertificateUrl())) {
            return $this->getCertificateUrl();
        }
        return '';
    }

    /**
     * Get DOI link if available
     */
    public function getDoiLink(): string {
        if (!empty($this->getCertificateDoi())) {
            return $this->getCertificateDoi();
        }
        return '';
    }

    /**
     * Get formatted certificate link text
     */
    public function getFormattedCertificateLinkText(): string {
        $url = $this->getCertificateUrl();
        if (empty($url)) {
            return '';
        }
        
        // Extract certificate ID from URL
        if (preg_match('/certificate-(\d{4}-\d{3})/', $url, $matches)) {
            return 'CODECHECK ' . $matches[1];
        }
        
        // Fallback: extract last part of URL
        $parts = explode('/', rtrim($url, '/'));
        $lastPart = end($parts);
        
        if (strpos($lastPart, 'certificate-') === 0) {
            return 'CODECHECK ' . str_replace('certificate-', '', $lastPart);
        }
        
        return 'View Certificate';
    }
}