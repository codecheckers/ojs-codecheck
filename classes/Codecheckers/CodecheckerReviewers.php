<?php

/**
 * @file classes/Codecheckers/CodecheckerReviewers.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerReviewers
 *
 * @brief The reviewers assigned to a submission, who are who its codecheckers
 *   can be (#13).
 *
 * Every codechecker is an OJS user assigned to the submission as a reviewer,
 * through "Add Reviewer", and an entry of the record's codechecker list is
 * linked to that account by its `userId`. A reviewer's assignment in force is
 * found the way OJS's `ReviewAssignmentAccessPolicy` finds it: the one in the
 * latest round they were assigned in, and none when that one was cancelled or
 * declined.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class CodecheckerReviewers
{
    /**
     * Each reviewer's assignment in force on a submission, by reviewer.
     *
     * @return array<int, ReviewAssignment> user id => assignment
     */
    public static function currentAssignments(int $submissionId): array
    {
        if ($submissionId <= 0) {
            return [];
        }

        return self::currentOf(
            Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$submissionId])->getMany()
        );
    }

    /**
     * The user's assignment in force on a submission, if they have one, by
     * the same rule as everyone's: currentOf().
     */
    public static function currentAssignmentOf(int $userId, int $submissionId): ?ReviewAssignment
    {
        if ($submissionId <= 0) {
            return null;
        }

        return self::currentOf(
            Repo::reviewAssignment()->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->filterByReviewerIds([$userId])
                ->getMany()
        )[$userId] ?? null;
    }

    /**
     * The rule of currentAssignments(), without the lookup: per reviewer, the
     * assignment of the latest stage and round, as OJS's last-round filter
     * orders them, dropped when it was cancelled or declined.
     *
     * @param iterable<ReviewAssignment> $assignments
     *
     * @return array<int, ReviewAssignment> user id => assignment
     */
    public static function currentOf(iterable $assignments): array
    {
        $latest = [];
        foreach ($assignments as $assignment) {
            $reviewerId = (int) $assignment->getReviewerId();
            $known = $latest[$reviewerId] ?? null;
            if (!$known || self::order($assignment) > self::order($known)) {
                $latest[$reviewerId] = $assignment;
            }
        }

        return array_filter(
            $latest,
            fn (ReviewAssignment $assignment) => !$assignment->getCancelled() && !$assignment->getDeclined()
        );
    }

    /** Stage, then round, then the assignment's own id. */
    private static function order(ReviewAssignment $assignment): array
    {
        return [(int) $assignment->getStageId(), (int) $assignment->getRound(), (int) $assignment->getId()];
    }

    /**
     * Whether the user is a codechecker of this submission: linked to an entry
     * of its codechecker list, and assigned to it as a reviewer now.
     */
    public static function isLinkedCodechecker(?User $user, int $submissionId): bool
    {
        if (!$user || $submissionId <= 0) {
            return false;
        }

        $codecheckers = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->value('codecheckers');
        if (!in_array((int) $user->getId(), CodecheckCodecheckers::linkedUserIds($codecheckers), true)) {
            return false;
        }

        return self::currentAssignmentOf((int) $user->getId(), $submissionId) !== null;
    }

    /**
     * The reviewers assigned to a submission now, as the "add codechecker"
     * dialog offers them: who they are, from their account, and how their
     * review is held.
     *
     * @return array<int, array{userId: int, name: string, orcid: string, github: string, doubleAnonymous: bool}>
     */
    public static function describeAssigned(int $submissionId): array
    {
        $reviewers = [];
        foreach (self::currentAssignments($submissionId) as $userId => $assignment) {
            $user = Repo::user()->get($userId, true);
            if (!$user) {
                continue;
            }

            $reviewers[] = self::entryFor($user) + [
                'doubleAnonymous' => self::isDoubleAnonymous($assignment),
            ];
        }

        usort($reviewers, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $reviewers;
    }

    /**
     * A codechecker entry for an account: its name, ORCID iD and GitHub
     * username, copied when the codechecker is added and kept with the
     * submission from then on.
     *
     * @return array{userId: int, name: string, orcid: string, github: string}
     */
    public static function entryFor(User $user): array
    {
        return ['userId' => (int) $user->getId()] + CodecheckCodecheckers::normalizedEntry([
            'name' => $user->getFullName(),
            'orcid' => $user->getOrcid(),
            'github' => GithubUsernameField::readFor((int) $user->getId()),
        ]);
    }

    /**
     * The codechecker list a save stores (#13), or why it is refused.
     *
     * Every codechecker is a reviewer assigned to the submission, linked by
     * `userId`. A link already stored keeps its stored entry, whatever the
     * request says about it; a link the save introduces must name a reviewer
     * assigned now, and its entry is copied from the account. An entry without
     * a link is kept only as stored — from before codecheckers were reviewers —
     * and a new one is refused. An account listed twice is listed once.
     *
     * @param callable(int): ?array $accountEntry the entry for a reviewer
     *   assigned now (entryFor()), `null` for anyone else
     *
     * @return array{entries: array<int, array>, refusedKey: ?string, refusedName: ?string}
     */
    public static function resolveEntries(mixed $incoming, mixed $stored, callable $accountEntry): array
    {
        $storedEntries = CodecheckCodecheckers::withStoredEntries($stored);
        $storedLinked = array_column(array_filter($storedEntries, fn (array $entry) => $entry['userId'] !== null), null, 'userId');
        $storedUnlinked = array_values(array_filter($storedEntries, fn (array $entry) => $entry['userId'] === null));

        $refusal = fn (string $key, array $entry) => ['entries' => [], 'refusedKey' => $key, 'refusedName' => $entry['name']];

        $entries = [];
        $seen = [];
        foreach (CodecheckCodecheckers::withStoredEntries($incoming) as $entry) {
            $userId = $entry['userId'];
            if ($userId === null) {
                if (!in_array($entry, $storedUnlinked, true)) {
                    return $refusal('plugins.generic.codecheck.codecheckers.notLinkedRefused', $entry);
                }
                $entries[] = $entry;
                continue;
            }

            if (isset($seen[$userId])) {
                continue;
            }
            $seen[$userId] = true;

            $resolved = $storedLinked[$userId] ?? $accountEntry($userId);
            if ($resolved === null) {
                return $refusal('plugins.generic.codecheck.codecheckers.notAReviewer', $entry);
            }
            $entries[] = $resolved;
        }

        return ['entries' => $entries, 'refusedKey' => null, 'refusedName' => null];
    }

    /**
     * Moves the codechecker links of a user OJS merges into another account
     * onto that account, as OJS moves their review assignments (#13).
     *
     * Hook `UserAction::mergeUsers`: `[&$oldUserId, &$newUserId]`. Registered
     * for every journal, since the merge spans them.
     */
    public static function moveLinksOnMerge(string $hookName, array $args): bool
    {
        [$oldUserId, $newUserId] = [(int) $args[0], (int) $args[1]];

        if (!Schema::hasTable('codecheck_metadata')) {
            return false;
        }

        // Narrowed by text, then judged exactly: an id may be stored as a
        // number or as a string of digits.
        $rows = DB::table('codecheck_metadata')
            ->where(fn ($query) => $query
                ->where('codecheckers', 'like', '%"userId":' . $oldUserId . '%')
                ->orWhere('codecheckers', 'like', '%"userId":"' . $oldUserId . '"%'))
            ->get(['submission_id', 'codecheckers']);

        foreach ($rows as $row) {
            $codecheckers = json_decode($row->codecheckers ?? '[]', true);
            if (!is_array($codecheckers)) {
                continue;
            }

            $moved = CodecheckCodecheckers::withUserIdReplaced($codecheckers, $oldUserId, $newUserId);
            if ($moved !== $codecheckers) {
                DB::table('codecheck_metadata')
                    ->where('submission_id', $row->submission_id)
                    ->update(['codecheckers' => json_encode($moved)]);
            }
        }

        return false;
    }

    /**
     * Whether a review is double-anonymous: the codechecker then gets no
     * author names and no contact (#28).
     */
    private static function isDoubleAnonymous(ReviewAssignment $assignment): bool
    {
        return (int) $assignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
    }
}
