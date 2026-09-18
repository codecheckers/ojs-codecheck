<?php
namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
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
            'issueUrl' => $data['issueUrl'] ?? '',
            'issueNumber' => $data['issueNumber'] ?? null,
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

    /**
     * The repositories a reader may see, each with the flag saying whether it is
     * the one holding the `codecheck.yml` file.
     *
     * Hidden repositories are removed. Which entry holds the `codecheck.yml` is
     * recorded on the entry itself, so it survives the list being reordered,
     * filtered or edited; a hidden entry holding it simply leaves nothing
     * marked here.
     *
     * `isWebLink` says whether the address may be rendered as a link. Nothing
     * validates a repository URL on the way in — `saveMetadata()` stores what it
     * is given — so a `javascript:` or `data:` URL would otherwise reach the
     * `href` of a public article page.
     *
     * @return array<int, array{url: string, containsCodecheckYaml: bool, isWebLink: bool}>
     */
    public function getPublicRepositories(): array
    {
        $raw = $this->data['repository'] ?? '';
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !is_array($decoded['repositories'] ?? null)) {
            CodecheckLogger::warning("Repository data is not in expected format. Raw value: " . $raw);
            return [];
        }

        $marked = false;

        $public = [];
        foreach (CodecheckRepositories::publicEntries($decoded) as $entry) {
            $url = $entry['url'];
            // Read the entry's own flag. Re-deriving it by comparing addresses
            // loses the mark to a stray space and puts it on the wrong row when
            // two entries share a URL.
            $isSelected = !$marked && !empty($entry['containsCodecheckYaml']);
            $marked = $marked || $isSelected;

            $public[] = [
                'url' => $url,
                'containsCodecheckYaml' => $isSelected,
                'isWebLink' => Constants::isWebUrl($url),
            ];
        }

        return $public;
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

    public function getIssueUrl(): string 
    { 
        return $this->data['issueUrl'] ?? ''; 
    }

    public function getIssueNumber(): string 
    { 
        return $this->data['issueNumber'] ?? ''; 
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
        
        // If it's already a URL, return it. Not filter_var(): that accepts
        // `javascript://…` — see Constants::isWebUrl().
        if (Constants::isWebUrl($certificate)) {
            return $certificate;
        }
        
        // Otherwise it is a register identifier — stored as YYYY-NNN, though
        // older records carry a CODECHECK- prefix — and the link is its landing
        // page in the register.
        return Constants::getRegisterCertificateUrl($certificate);
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
        
        // Anything that is not an http(s) address is not a link. The value is
        // still stored; it is simply never turned into one, because this is
        // rendered straight into an href on the public article page and
        // `javascript:` there executes for every reader.
        return Constants::isWebUrl($report) ? $report : '';
    }
}