<?php
/**
 * @file classes/Submission/CodecheckSubmissionAccess.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckSubmissionAccess
 * @brief Whether a user may act on one particular submission.
 *
 * The API handler's role check asks whether a user holds a role *anywhere in the
 * journal* (`$user->hasRole($roles, $contextId)`), which is the right question for
 * an editor and the wrong one for a reviewer: reviewers are invited per
 * submission, so a journal-wide write role lets any of them act on every
 * submission, and on the public CODECHECK register (Issue #173).
 *
 * This answers the per-submission half. It is deliberately separate from
 * a role list, which cannot express it.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\facades\Repo;
use PKP\security\Role;
use PKP\user\User;

class CodecheckSubmissionAccess
{
    /**
     * Is this user a reviewer assigned to this submission?
     *
     * Assignment is what makes a reviewer the codechecker of a submission, so
     * this is the gate for the things a codechecker records about their own
     * check.
     */
    public static function isAssignedReviewer(?User $user, int $submissionId): bool
    {
        if (!$user || $submissionId <= 0) {
            return false;
        }

        $assignments = Repo::reviewAssignment()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$user->getId()])
            ->getMany();

        return $assignments->isNotEmpty();
    }

    /**
     * May this user write CODECHECK data for this submission?
     *
     * Editors may, for any submission in their journal. A reviewer may only for
     * the submission they are assigned to. Nothing else may.
     */
    public static function canWriteMetadata(?User $user, int $submissionId, int $contextId): bool
    {
        if (!$user) {
            return false;
        }

        if (self::isEditor($user, $contextId)) {
            return true;
        }

        return self::isAssignedReviewer($user, $submissionId);
    }

    /**
     * The journal roles that act editorially.
     *
     * Interactions with the public CODECHECK register are reserved to these —
     * not to reviewers, and not to the codechecker either, because an entry in
     * the register is published under the journal's name.
     */
    public static function isEditor(?User $user, int $contextId): bool
    {
        return (bool) $user?->hasRole(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            $contextId
        );
    }
}
