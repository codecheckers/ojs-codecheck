<?php

/**
 * @file classes/Codecheckers/CodecheckerReviewClosing.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerReviewClosing
 *
 * @brief Closing a codechecker's review assignment once the check is completed
 *   (#13), for a codechecker whose only task was the codecheck.
 *
 * Closing does what a reviewer's own submission of the review does in OJS
 * (`PKPReviewerReviewStep3Form::execute()`): the completion date, the
 * recommendation "See comments", a review comment naming the certificate and
 * its register entry, the notification and email to the editors, removal of
 * the reviewer's task, and the event log entry. That form cannot be driven
 * without the reviewer's session, so its steps are repeated here; its
 * `reviewerreviewstep3form::execute` hook is not raised, as its listeners
 * expect the form. The closed review then counts
 * where OJS counts reviews: the reviewer statistics, the masthead's list of
 * reviewers and OJS's own ORCID review deposit, which closing hands it to.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\orcid\actions\SendReviewToOrcid;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusRegisterUpdate;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewCompleteNotifyEditors;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\user\User;

class CodecheckerReviewClosing
{
    /** The step OJS sets once a review is submitted. */
    private const SUBMITTED_STEP = 4;

    /**
     * Whether a status is "completed" or later: from then on, a codechecker's
     * review can be closed.
     */
    public static function isClosableStatus(?string $status): bool
    {
        $index = array_search($status, Constants::CODECHECK_STATUSES, true);

        return $index !== false
            && $index >= array_search(Constants::CODECHECK_STATUS_COMPLETED_UNSUCCESSFUL, Constants::CODECHECK_STATUSES, true);
    }

    /**
     * The reviews of the submission's codecheckers that are not submitted
     * yet: each linked entry's assignment in force without a completion date.
     *
     * @return array<int, array{reviewAssignmentId: int, userId: int, name: string, round: int}>
     */
    public static function openReviews(int $submissionId): array
    {
        $codecheckers = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->value('codecheckers');
        $linked = array_column(
            array_filter(CodecheckCodecheckers::withStoredEntries($codecheckers), fn (array $entry) => $entry['userId'] !== null),
            null,
            'userId'
        );
        if ($linked === []) {
            return [];
        }

        $reviews = [];
        foreach (CodecheckerReviewers::currentAssignments($submissionId) as $userId => $assignment) {
            if (isset($linked[$userId]) && !$assignment->getDateCompleted()) {
                $reviews[] = [
                    'reviewAssignmentId' => (int) $assignment->getId(),
                    'userId' => (int) $userId,
                    'name' => $linked[$userId]['name'],
                    'round' => (int) $assignment->getRound(),
                ];
            }
        }

        return $reviews;
    }

    /**
     * The reviews that can be closed now: openReviews(), once the check is
     * completed, and none before.
     *
     * @return array<int, array{reviewAssignmentId: int, userId: int, name: string, round: int}>
     */
    public static function closableReviews(int $submissionId): array
    {
        return self::isClosableStatus(CodecheckStatusHandler::getCurrentStatusData($submissionId)->status ?? null)
            ? self::openReviews($submissionId)
            : [];
    }

    /**
     * Closes the review as the reviewer's own submission would.
     *
     * The review is claimed first, by setting its completion date only where
     * it has none, so two requests cannot both close it. What follows the
     * claim does not depend on the notifications, which cannot fail it.
     *
     * @param User $actor the editor who closes it, for the event log
     * @param string $registerPath `organisation/repository` of the journal's
     *   register: only an issue there is named in the comment
     *
     * @return bool false when the review was closed already
     */
    public static function close(ReviewAssignment $assignment, Submission $submission, Context $context, User $actor, string $registerPath): bool
    {
        $now = Core::getCurrentDate();
        $claimed = DB::table('review_assignments')
            ->where('review_id', $assignment->getId())
            ->whereNull('date_completed')
            ->update(['date_completed' => $now]);
        if ($claimed === 0) {
            return false;
        }

        Repo::reviewAssignment()->edit($assignment, [
            'dateConfirmed' => $assignment->getDateConfirmed() ?? $now,
            'dateCompleted' => $now,
            'recommendation' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS,
            'step' => max((int) $assignment->getStep(), self::SUBMITTED_STEP),
        ]);
        $assignment = Repo::reviewAssignment()->get($assignment->getId());

        self::addComment($assignment, $submission, $registerPath);

        Notification::withAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $assignment->getId())
            ->withUserId($assignment->getReviewerId())
            ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
            ->delete();

        $reviewer = Repo::user()->get($assignment->getReviewerId(), true);
        Repo::eventLog()->add(Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_READY,
            // The administrator behind "Login as", as OJS logs it.
            'userId' => Validation::loggedInAs() ?? $actor->getId(),
            'message' => 'log.review.reviewReady',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'reviewAssignmentId' => $assignment->getId(),
            'reviewerName' => $reviewer?->getFullName() ?? '',
            'submissionId' => $submission->getId(),
            'round' => $assignment->getRound(),
        ]));

        Repo::reviewAssignment()->getAccessInvitation($assignment)?->finalize();

        self::notifyEditors($assignment, $submission, $context);

        // Where OJS deposits reviews to ORCID, the plugin deposits nothing
        // (#13), so the closed review is handed to OJS's deposit, as
        // confirming a review in its grid does. Queued; it needs the
        // codechecker's ORCID iD on their OJS profile.
        if (CodecheckPlugin::ojsDepositsReviews($context)) {
            try {
                (new SendReviewToOrcid($assignment->getId()))->execute();
            } catch (\Throwable $e) {
                CodecheckLogger::warning('Could not hand the closed review #' . $assignment->getId() . ' to OJS\'s ORCID deposit: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * The review comment: the check is done, and where its certificate and
     * register entry are. Visible to the authors whatever the review method:
     * a codechecker is never anonymous to them, as the certificate names them.
     */
    private static function addComment(ReviewAssignment $assignment, Submission $submission, string $registerPath): void
    {
        $metadata = DB::table('codecheck_metadata')->where('submission_id', $submission->getId())->first();
        $issueUrl = json_decode($metadata->issue ?? 'null', true)['url'] ?? null;
        [$organization, $repository] = array_pad(explode('/', $registerPath, 2), 2, '');
        $inRegister = is_string($issueUrl) && $issueUrl !== ''
            && CodecheckStatusRegisterUpdate::issueUrlIsInRegister($issueUrl, $organization, $repository);

        $commentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var \PKP\submission\SubmissionCommentDAO $commentDao */
        $comment = $commentDao->newDataObject();
        $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
        $comment->setRoleId(Role::ROLE_ID_REVIEWER);
        $comment->setAssocId($assignment->getId());
        $comment->setSubmissionId($submission->getId());
        $comment->setAuthorId($assignment->getReviewerId());
        $comment->setComments(self::commentText(
            $metadata->certificate ?? null,
            $metadata->report ?? null,
            $inRegister ? $issueUrl : null
        ));
        $comment->setCommentTitle('');
        $comment->setViewable(true);
        $comment->setDatePosted(Core::getCurrentDate());
        $commentDao->insertObject($comment);
    }

    /**
     * The comment's HTML, from the record: every value escaped, and a link
     * only for a web address.
     */
    public static function commentText(?string $certificate, ?string $report, ?string $issueUrl): string
    {
        $link = fn (?string $url, string $text) => $url !== null && Constants::isWebUrl($url)
            ? '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($text) . '</a>'
            : htmlspecialchars($text);

        $paragraphs = [__('plugins.generic.codecheck.closeReview.comment.done')];
        if ($certificate !== null && trim($certificate) !== '') {
            $paragraphs[] = __('plugins.generic.codecheck.closeReview.comment.certificate', [
                'certificate' => $link($report, trim($certificate)),
            ]);
        }
        if ($issueUrl !== null && Constants::isWebUrl($issueUrl)) {
            $paragraphs[] = __('plugins.generic.codecheck.closeReview.comment.register', [
                'issue' => $link($issueUrl, $issueUrl),
            ]);
        }

        return implode('', array_map(fn (string $paragraph) => '<p>' . $paragraph . '</p>', $paragraphs));
    }

    /**
     * The notification and email the editors get when a review is submitted,
     * as OJS sends them, to the managers and Section editors of the stage:
     * the loop of `PKPReviewerReviewStep3Form::execute()` in OJS 3.5, which
     * this follows step for step.
     */
    private static function notifyEditors(ReviewAssignment $assignment, Submission $submission, Context $context): void
    {
        $notificationManager = new NotificationManager();
        $subscriptions = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $subscriptions */
        $request = Application::get()->getRequest();

        $editorIds = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$submission->getData('stageId')])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->get()
            ->pluck('userId')
            ->unique();

        foreach ($editorIds as $userId) {
            // The review is closed whatever happens here; one editor's
            // notification failing costs nobody else theirs.
            try {
                self::notifyEditor($userId, $assignment, $submission, $context, $notificationManager, $subscriptions, $request);
            } catch (\Throwable $e) {
                CodecheckLogger::warning('Could not notify editor #' . $userId . ' of the closed review #' . $assignment->getId() . ': ' . $e->getMessage());
            }
        }
    }

    private static function notifyEditor(
        int $userId,
        ReviewAssignment $assignment,
        Submission $submission,
        Context $context,
        NotificationManager $notificationManager,
        NotificationSubscriptionSettingsDAO $subscriptions,
        $request
    ): void {
        $notification = $notificationManager->createNotification(
            $userId,
            Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT,
            $context->getId(),
            PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
            $assignment->getId()
        );
        $blocked = $subscriptions->getNotificationSubscriptionSettings(
            NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
            $userId,
            (int) $context->getId()
        );
        if (!$notification || in_array(Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT, $blocked)) {
            return;
        }

        $mailable = new ReviewCompleteNotifyEditors($context, $submission, $assignment);
        $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewCompleteNotifyEditors::getEmailTemplateKey());
        if (!$template) {
            $template = Repo::emailTemplate()->getByKey($context->getId(), 'NOTIFICATION');
            $mailable->addData([
                'notificationContents' => $notificationManager->getNotificationContents($request, $notification),
                'notificationUrl' => $notificationManager->getNotificationUrl($request, $notification),
            ]);
        }

        $editor = Repo::user()->get($userId);
        $mailable
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$editor])
            ->subject($template->getLocalizedData('subject'))
            ->body($template->getLocalizedData('body'))
            ->allowUnsubscribe($notification);

        Mail::send($mailable);
        Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_COMPLETE, $mailable, $submission, $editor);
    }
}
