<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use Illuminate\Support\Facades\DB;
use APP\plugins\generic\codecheck\classes\Constants;

class CodecheckStatusHandler {
    public static function getCurrentStatusData(int $submissionId): object|null {
        return DB::table('codecheck_status')
            ->where('submission_id', $submissionId)
            ->orderBy('timestamp', 'desc')
            ->orderBy('status_id', 'desc')
            ->first();
    }

    public static function getStatusDataHistory(int $submissionId): object|null {
        return DB::table('codecheck_status')
            ->where('submission_id', $submissionId)
            ->orderBy('timestamp', 'desc')
            ->orderBy('status_id', 'desc')
            ->get();
    }

    public static function updateStatus(int $submissionId, string $status, int $userId): object|false {
        $newRecord = [
            'submission_id' => $submissionId,
            'status' => $status,
            'timestamp' => now(),
            'user_id' => $userId
        ];

        $insertWorked = DB::table('codecheck_status')->insert($newRecord);

        if(!$insertWorked) {
            return false;
        }

        return CodecheckStatusHandler::getCurrentStatusData($submissionId);
    }

    public static function automaticStatusUpdate(array $submissionMetadata): object|null {
        $submissionId = $submissionMetadata['submissionId'];
        $statusHistory = CodecheckStatusHandler::getStatusDataHistory($submissionId);
    
        error_log("Status History: " . json_encode($statusHistory));
        error_log("Status History count: " . $statusHistory->count());
        error_log("Is null: " . ($statusHistory === null ? 'true' : 'false'));

        if($statusHistory == null || $statusHistory->count() < 2) {
            $status = Constants::CODECHECK_STATUS_NEEDS_CODECHECKER;
            if(!empty($submissionMetadata['codecheck']['codecheckers'])) {
                $status = Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER;
            }
            return CodecheckStatusHandler::updateStatus($submissionId, $status, -1);
        }
        return CodecheckStatusHandler::getCurrentStatusData($submissionId);
    }
}