<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

use PKP\db\DAORegistry;

class CodecheckSubmissionDAO
{
    /**
     * Get CODECHECK data by submission ID
     */
    public function getBySubmissionId(int $submissionId): ?CodecheckSubmission
    {
        $db = DAORegistry::getDAO('XMLDAO');
        $result = $db->retrieve(
            'SELECT * FROM codecheck_metadata WHERE submission_id = ?',
            [$submissionId]
        );

        if ($result->RecordCount() > 0) {
            $row = $result->getRowAssoc(false);
            return $this->fromRow($row);
        }

        return null;
    }

    /**
     * Insert or update CODECHECK data
     */
    public function insertOrUpdate(int $submissionId, array $data): void
    {
        $db = DAORegistry::getDAO('XMLDAO');

        $existing = $this->getBySubmissionId($submissionId);

        if ($existing) {
            $db->update(
                'UPDATE codecheck_metadata SET 
                    opt_in = ?, code_repository = ?, data_repository = ?, 
                    dependencies = ?, execution_instructions = ?, updated_at = NOW() 
                WHERE submission_id = ?',
                [
                    $data['opt_in'] ? 1 : 0,
                    $data['code_repository'] ?? '',
                    $data['data_repository'] ?? '',
                    $data['dependencies'] ?? '',
                    $data['execution_instructions'] ?? '',
                    $submissionId
                ]
            );
        } else {
            $db->update(
                'INSERT INTO codecheck_metadata 
                (submission_id, opt_in, code_repository, data_repository, dependencies, execution_instructions, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [
                    $submissionId,
                    $data['opt_in'] ? 1 : 0,
                    $data['code_repository'] ?? '',
                    $data['data_repository'] ?? '',
                    $data['dependencies'] ?? '',
                    $data['execution_instructions'] ?? ''
                ]
            );
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
}