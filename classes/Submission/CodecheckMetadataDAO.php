<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

use Illuminate\Support\Facades\DB;
use Exception;

class CodecheckMetadataDAO
{
    /**
     * Get CODECHECK metadata for a submission
     */
    public function getBySubmissionId(int $submissionId): ?array
    {
        try {
            $result = DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->first();
            
            if (!$result) {
                return null;
            }
            
            return $this->fromRow($result);
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error getting metadata: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Insert or update CODECHECK metadata
     */
    public function insertOrUpdate(int $submissionId, array $data): bool
    {
        try {
            $data['updated_at'] = now();
            
            // Check if record exists
            $exists = DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->exists();
            
            if ($exists) {
                // Update existing record
                DB::table('codecheck_metadata')
                    ->where('submission_id', $submissionId)
                    ->update($data);
            } else {
                // Insert new record
                $data['submission_id'] = $submissionId;
                $data['created_at'] = now();
                DB::table('codecheck_metadata')->insert($data);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error saving metadata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete CODECHECK metadata for a submission
     */
    public function deleteBySubmissionId(int $submissionId): bool
    {
        try {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->delete();
            return true;
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error deleting metadata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all submissions with CODECHECK opt-in
     */
    public function getAllOptedIn(): array
    {
        try {
            $results = DB::table('codecheck_metadata')
                ->where('opt_in', true)
                ->get();
            
            return array_map([$this, 'fromRow'], $results->toArray());
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error getting opted-in submissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if identifier is unique
     */
    public function isIdentifierUnique(string $identifier, ?int $excludeSubmissionId = null): bool
    {
        try {
            $query = DB::table('codecheck_metadata')
                ->where('identifier', $identifier);
            
            if ($excludeSubmissionId) {
                $query->where('submission_id', '!=', $excludeSubmissionId);
            }
            
            return !$query->exists();
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error checking identifier uniqueness: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate next available identifier
     */
    public function generateNextIdentifier(): string
    {
        try {
            $year = date('Y');
            $prefix = "CODECHECK-{$year}-";
            
            // Get the highest number for this year
            $lastIdentifier = DB::table('codecheck_metadata')
                ->where('identifier', 'LIKE', $prefix . '%')
                ->orderBy('identifier', 'desc')
                ->value('identifier');
            
            if ($lastIdentifier) {
                $number = (int) substr($lastIdentifier, strlen($prefix));
                $number++;
            } else {
                $number = 1;
            }
            
            return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log("CODECHECK DAO: Error generating identifier: " . $e->getMessage());
            return "CODECHECK-" . date('Y') . "-" . uniqid();
        }
    }
    
    /**
     * Convert database row to array with decoded JSON fields
     */
    private function fromRow($row): array
    {
        $data = (array) $row;
        
        // Decode JSON fields
        $jsonFields = [
            'manifest_files',
            'paper_metadata',
            'codecheckers',
            'repositories'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $decoded = json_decode($data[$field], true);
                $data[$field] = $decoded ?? $data[$field];
            }
        }
        
        return $data;
    }
}