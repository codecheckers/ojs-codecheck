<?php

/**
 * @file classes/Codecheckers/CodecheckerNeededNotice.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerNeededNotice
 *
 * @brief Emails an editor when they are assigned to a submission that takes
 *   part in a CODECHECK and has no codechecker yet (#31).
 *
 * Sent on the editor's stage assignment, including the one OJS makes when the
 * submission is submitted, so it reaches the editor when they take the
 * submission on. A task in OJS's Tasks list cannot carry a link for a plugin's
 * own notification type on OJS 3.5 (#192), so this is an email only.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\emailTemplate\EmailTemplate;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class CodecheckerNeededNotice
{
    /** The roles an editor's stage assignment is in. */
    public const EDITOR_ROLE_IDS = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /**
     * Whether a submission that takes part and is still in the editorial
     * workflow needs a codechecker: none is recorded, linked to an account or
     * not, and the check has not got past *needs codechecker*.
     */
    public static function needsCodechecker(?string $codecheckStatus, array $codecheckers): bool
    {
        return $codecheckers === [] && !CodecheckStatusHandler::assignsCodechecker($codecheckStatus);
    }

    /**
     * Listens to new stage assignments. Never throws: the assignment is made
     * whatever happens to the email.
     */
    public function onStageAssignmentCreated(StageAssignment $assignment): void
    {
        try {
            $this->notify($assignment);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not email editor #' . $assignment->userId . ' that submission #' . $assignment->submissionId . ' needs a codechecker: ' . $e->getMessage());
        }
    }

    /**
     * Cheapest questions first: this runs for every stage assignment in every
     * journal, the author's on each submission included.
     */
    private function notify(StageAssignment $assignment): void
    {
        $roleId = DB::table('user_groups')->where('user_group_id', $assignment->userGroupId)->value('role_id');
        if (!in_array((int) $roleId, self::EDITOR_ROLE_IDS, true)) {
            return;
        }

        $submissionId = (int) $assignment->submissionId;
        $contextId = CodecheckSubmissionDAO::contextIdOf($submissionId);
        if (!$this->plugin->isEnabledIn($contextId) || !CodecheckSubmissionDAO::isOptedIn($submissionId)) {
            return;
        }

        // A draft is not taken on yet: its submitter, in an editor's role, is
        // assigned when they start it (OJS clears the progress on submitting,
        // before it assigns the editors).
        $submission = Repo::submission()->get($submissionId);
        if ($submission->getData('status') !== Submission::STATUS_QUEUED || $submission->getData('submissionProgress')) {
            return;
        }

        // One email per editor, who may be assigned in two editorial roles.
        $editorAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId((int) $assignment->userId)
            ->withRoleIds(self::EDITOR_ROLE_IDS)
            ->count();
        if ($editorAssignments > 1) {
            return;
        }

        $codecheckers = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->value('codecheckers');
        if (!self::needsCodechecker(
            CodecheckStatusHandler::getCurrentStatusData($submissionId)->status,
            CodecheckCodecheckers::withNormalizedEntries($codecheckers)
        )) {
            return;
        }

        $editor = Repo::user()->get((int) $assignment->userId);
        $template = self::template($contextId);
        if (!$editor || !$template) {
            CodecheckLogger::warning('No editor or no "' . CodecheckerNeededEmail::getEmailTemplateKey() . '" email template to tell that submission #' . $submissionId . ' needs a codechecker.');
            return;
        }

        $context = Application::getContextDAO()->getById($contextId);
        $mailable = (new CodecheckerNeededEmail($context, $submission))
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$editor])
            ->subject($template->getLocalizedData('subject'))
            ->body($template->getLocalizedData('body'));

        Mail::send($mailable);
    }

    /**
     * The journal's template, installed first where the plugin was enabled
     * before the template existed: the install migration only runs on enable
     * and on upgrade.
     */
    private static function template(int $contextId): ?EmailTemplate
    {
        $key = CodecheckerNeededEmail::getEmailTemplateKey();
        $template = Repo::emailTemplate()->getByKey($contextId, $key);
        if ($template) {
            return $template;
        }

        Repo::emailTemplate()->dao->installEmailTemplates(CodecheckPlugin::emailTemplatesFile(), [], $key, true);
        return Repo::emailTemplate()->getByKey($contextId, $key);
    }
}
