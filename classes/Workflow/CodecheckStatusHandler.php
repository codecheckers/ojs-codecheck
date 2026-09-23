<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;

class CodecheckStatusHandler
{
    public static function getCurrentStatusData(int $submissionId): object
    {
        $codecheckStatus = DB::table('codecheck_status')
            ->where('submission_id', $submissionId)
            ->orderBy('timestamp', 'desc')
            ->orderBy('status_id', 'desc')
            ->first();

        return $codecheckStatus ?? (object) ['status' => 'plugins.generic.codecheck.status.pending'];
    }

    public static function getStatusDataHistory(int $submissionId): object|null
    {
        $statusHistory = DB::table('codecheck_status')
            ->where('submission_id', $submissionId)
            ->orderBy('timestamp', 'desc')
            ->orderBy('status_id', 'desc')
            ->get();

        return $statusHistory->isEmpty() ? null : $statusHistory;
    }

    public static function updateStatus(int $submissionId, string $status, int $userId): object|false
    {
        $newRecord = [
            'submission_id' => $submissionId,
            'status' => $status,
            'timestamp' => now(),
            'user_id' => $userId
        ];

        $insertWorked = DB::table('codecheck_status')->insert($newRecord);

        if (!$insertWorked) {
            return false;
        }

        // Every recorded status passes through here, so the register issue hears
        // about all of them — from the workflow form and from the automatic
        // update alike. Best-effort: it never fails the status change (#150).
        CodecheckStatusRegisterComment::post($submissionId, $status);

        return CodecheckStatusHandler::getCurrentStatusData($submissionId);
    }

    public static function automaticStatusUpdate(array $submissionMetadata): object|null
    {
        $submissionId = $submissionMetadata['submissionId'];
        $statusHistory = CodecheckStatusHandler::getStatusDataHistory($submissionId);

        CodecheckLogger::debug('Status History: ' . json_encode($statusHistory));

        if (empty($statusHistory) || $statusHistory->count() < 2) {
            $status = Constants::CODECHECK_STATUS_NEEDS_CODECHECKER;
            if (!empty($submissionMetadata['codecheck']['codecheckers'])) {
                $status = Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER;
            }
            return CodecheckStatusHandler::updateStatus($submissionId, $status, -1);
        }
        return CodecheckStatusHandler::getCurrentStatusData($submissionId);
    }
}
