<?php

/**
 * @file classes/Submission/CodecheckSubmissionAccess.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckSubmissionAccess
 *
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

use APP\core\Application;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
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

        return Repo::reviewAssignment()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$user->getId()])
            ->getCount() > 0;
    }

    /**
     * May this user write CODECHECK data for this submission?
     *
     * An editor on this submission may (see isEditorOn()), and a reviewer only
     * for the submission they are assigned to. Nothing else may.
     */
    public static function canWriteMetadata(?User $user, int $submissionId, int $contextId): bool
    {
        if (!$user) {
            return false;
        }

        return self::isEditorOn($user, $submissionId, $contextId)
            || self::isAssignedReviewer($user, $submissionId);
    }

    /**
     * Does this user act editorially on this submission?
     *
     * Judged per submission, as mayEditCodecheckers() is: a manager or site
     * administrator always (a site administrator holds no journal role, which
     * isEditor() asks for), a Section editor or Assistant only with a stage
     * assignment here. Their journal-wide role alone also covers a submission
     * they reach as its author or as an invited reviewer, where it is not an
     * editor's standing.
     */
    public static function isEditorOn(?User $user, int $submissionId, int $contextId): bool
    {
        if (!$user || $submissionId <= 0) {
            return false;
        }

        return self::isJournalManager($user, $contextId)
            || self::hasStageAssignment($user, $submissionId, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]);
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

    /**
     * May this user know who wrote this submission, and how to reach them (#28)?
     *
     * `SubmissionAccessPolicy` admits a reviewer without asking their review
     * method. OJS hides the authors from a reviewer only in double-anonymous
     * review, so that is the one case withheld here. Editors, and the
     * submission's own authors, are never in it.
     *
     * Standing is judged per submission, not by journal role: a Section editor
     * or Assistant who is invited as a blind reviewer reaches the submission
     * through that assignment alone, and the author role is held by nearly every
     * reviewer. Checked cheapest first, so a manager costs no query.
     */
    public static function mayKnowAuthors(?User $user, int $submissionId, int $contextId): bool
    {
        if (!$user || $submissionId <= 0) {
            return false;
        }

        return self::isJournalManager($user, $contextId)
            || self::hasStageAssignment($user, $submissionId, [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])
            || self::authorsVisible(false, self::currentReviewMethod($user, $submissionId));
    }

    /**
     * May this user change who the record names as codecheckers
     * (GHSA-4p3r-qgp4-g74r)?
     *
     * Their ORCID iDs decide whose account may be credited for the check, so
     * this is the editorial question asked per submission, as
     * mayKnowAuthors() asks it: a manager or site administrator always, a
     * Section editor or Assistant only with a stage assignment on this
     * submission. A journal-wide role is not enough — one invited as a reviewer
     * reaches the submission through that assignment alone, and could
     * otherwise name an account they hold and connect it.
     */
    public static function mayEditCodecheckers(?User $user, int $submissionId, int $contextId): bool
    {
        return self::isEditorOn($user, $submissionId, $contextId);
    }

    /**
     * May this user reserve, link or remove the certificate identifier, and so
     * write the register issue (#65)?
     *
     * The identifier and its issue are published under the journal's name in
     * the public register, so this is the question the `identifier` and
     * `issue` endpoints already ask by role (#173): a journal manager or a site
     * administrator, and nobody else.
     */
    public static function mayManageIdentifier(?User $user, int $contextId): bool
    {
        return $user !== null && self::isJournalManager($user, $contextId);
    }

    /**
     * What the user may do with this submission's CODECHECK record, for the
     * forms to offer only that (#127).
     *
     * Hints that save a request the server would refuse; every endpoint
     * enforces its own rule. `addCertificateReference` is only the part about
     * the user (false, without the lookup, when the journal does not list the
     * certificate): the endpoint also refuses a submission that is not opted
     * in and a published latest publication.
     *
     * @param bool $certificateReferenceOff the journal does not list the certificate
     *
     * @return array{write: bool, editCodecheckers: bool, manageIdentifier: bool, addCertificateReference: bool}
     */
    public static function permissions(
        ?User $user,
        int $submissionId,
        int $contextId,
        bool $certificateReferenceOff = false
    ): array {
        // Asked once: `write` is canWriteMetadata() and `editCodecheckers` is
        // mayEditCodecheckers(), and both start with this lookup.
        $editorOn = self::isEditorOn($user, $submissionId, $contextId);

        return [
            'write' => $editorOn || self::isAssignedReviewer($user, $submissionId),
            'editCodecheckers' => $editorOn,
            'manageIdentifier' => self::mayManageIdentifier($user, $contextId),
            // `references` asks for an editor on this submission, and the
            // publication must be one the user may edit (as does the endpoint).
            'addCertificateReference' => $user !== null
                && !$certificateReferenceOff
                && $editorOn
                && Repo::submission()->canEditPublication($submissionId, $user->getId()),
        ];
    }

    /**
     * Whose ORCID deposit the user may trigger on this submission: the one
     * place `orcid-status` and `orcid-deposit` both ask. The reviewer lookup
     * is only made for someone who is not an editor here.
     *
     * @return 'all'|'own'|'none'
     */
    public static function orcidDepositScopeFor(?User $user, int $submissionId, int $contextId): string
    {
        $isEditor = self::isEditorOn($user, $submissionId, $contextId);

        return self::orcidDepositScope($isEditor, !$isEditor && self::isAssignedReviewer($user, $submissionId));
    }

    /**
     * Whose ORCID deposit the user may trigger: every codechecker's (`all`,
     * an editor), only their own (`own`, an assigned reviewer) or none.
     *
     * @return 'all'|'own'|'none'
     */
    public static function orcidDepositScope(bool $isEditor, bool $isAssignedReviewer): string
    {
        if ($isEditor) {
            return 'all';
        }

        return $isAssignedReviewer ? 'own' : 'none';
    }

    /**
     * The rule of mayKnowAuthors(), without the lookups.
     *
     * Without a standing on the submission, authors are visible only through a
     * review assignment that is not double-anonymous — nothing else admits
     * such a user, so none means withheld.
     *
     * @param ?int $reviewMethod The method of the user's current review
     *                           assignment, null when they have none
     */
    public static function authorsVisible(bool $hasStanding, ?int $reviewMethod): bool
    {
        return $hasStanding
            || ($reviewMethod !== null
                && $reviewMethod !== ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS);
    }

    /**
     * The managers: a site administrator has no journal role, so that group
     * is asked for in the site context.
     */
    private static function isJournalManager(User $user, int $contextId): bool
    {
        return $user->hasRole([Role::ROLE_ID_MANAGER], $contextId)
            || $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID);
    }

    /** Is this user assigned to the submission in one of these roles? */
    private static function hasStageAssignment(User $user, int $submissionId, array $roleIds): bool
    {
        return StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($user->getId())
            ->withRoleIds($roleIds)
            ->exists();
    }

    /**
     * The method of the review assignment now in force for the user, found the
     * way OJS's ReviewAssignmentAccessPolicy finds it: the last round, and none
     * at all when it was cancelled or declined.
     */
    private static function currentReviewMethod(User $user, int $submissionId): ?int
    {
        $assignment = Repo::reviewAssignment()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$user->getId()], true)
            ->getMany()
            ->first();

        if (!$assignment || $assignment->getCancelled() || $assignment->getDeclined()) {
            return null;
        }

        return (int) $assignment->getReviewMethod();
    }
}
