<?php

/**
 * @file classes/Tasks/RefreshCodecheckLists.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class RefreshCodecheckLists
 *
 * @brief Reads the lists the plugin keeps a copy of — the register's venues,
 *   whose issue labels the editorial form offers, and the community's lists of
 *   codecheckers, which suggest a GitHub username — daily or weekly (#65).
 *
 * Both copies are shared by every journal on the site, so the most frequent
 * choice among the journals that have the plugin enabled applies.
 *
 * The task is registered to run every minute and filtered by `isDue()`,
 * because OJS's web task runner only runs what is due in the minute a request
 * happens to arrive: a task registered `daily()` would run only if somebody
 * opened a page at midnight. Until a day has passed the filter reads one cache
 * entry and when the venue labels were stored, and a filtered run writes
 * nothing to the scheduled task log.
 *
 * Nothing else that reaches GitHub belongs here: reserving an identifier must
 * read the register as it is, and the rest are writes that follow an editor's
 * action.
 */

namespace APP\plugins\generic\codecheck\classes\Tasks;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CommunityCodecheckers;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\Cache;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;

class RefreshCodecheckLists extends ScheduledTask
{
    /** Set for an hour after a failed refresh, so it is not tried every minute. */
    private const FAILED_CACHE_KEY = 'codecheck-lists-refresh-failed';

    /** How long a failed refresh waits before it is tried again. */
    public const RETRY_SECONDS = 60 * 60;

    /** @copydoc ScheduledTask::getName() */
    public function getName(): string
    {
        return __('plugins.generic.codecheck.task.refreshLists');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     *
     * When the lists were last read is when the venue labels were stored, so
     * a run whose community lists failed counts as a refresh once its hour is
     * up; their cached copy is kept, and a lookup reads them itself if none is.
     *
     * A list that cannot be read is not the task failing: the stored copy is
     * still offered, and OJS emails the site administrator about every failed
     * run. So it is written to the task's log and the run reports success.
     */
    protected function executeActions(): bool
    {
        $problems = [];
        try {
            CodecheckIssueLabels::fromApi();
        } catch (\Throwable $e) {
            $problems[] = 'Could not refresh the CODECHECK venue list: ' . $e->getMessage();
        }
        if (!CommunityCodecheckers::refresh()) {
            $problems[] = 'Could not read every CODECHECK community list of codecheckers; the cached copy is kept.';
        }

        foreach ($problems as $problem) {
            CodecheckLogger::warning($problem);
            $this->addExecutionLogEntry($problem, ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
        }
        if ($problems !== []) {
            self::recordFailure();
        }

        return true;
    }

    /** Whether a read of the lists failed within the last hour. */
    public static function failedRecently(): bool
    {
        try {
            return Cache::has(self::FAILED_CACHE_KEY);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Holds off the next read of the lists for an hour. */
    public static function recordFailure(): void
    {
        try {
            Cache::put(self::FAILED_CACHE_KEY, true, self::RETRY_SECONDS);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not record a failed read of the CODECHECK lists: ' . $e->getMessage());
        }
    }

    /**
     * Whether a refresh is due.
     *
     * @param callable(): ?int $interval the shortest refresh interval in seconds
     *   among the journals that have the plugin enabled, `null` for none. It is
     *   asked only once a day has passed, since a day is the shortest choice.
     *
     * It never throws: it runs as the schedule's filter, which nothing in OJS
     * or Laravel catches, so an exception here would end the whole scheduler
     * run. On an install where no journal ever enabled the plugin, the labels
     * table does not exist and the answer is no.
     */
    public static function isDue(callable $interval): bool
    {
        try {
            return self::decide($interval, CodecheckIssueLabels::lastUpdated(), self::failedRecently(), time());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The decision behind `isDue()`, given when the lists were last read.
     *
     * @param callable(): ?int $interval as for `isDue()`
     */
    public static function decide(callable $interval, ?int $refreshedAt, bool $failedRecently, int $now): bool
    {
        if ($failedRecently) {
            return false;
        }

        if ($refreshedAt !== null && $now - $refreshedAt < min(Constants::CODECHECK_LISTS_REFRESH_SECONDS)) {
            return false;
        }

        $seconds = $interval();
        if ($seconds === null) {
            return false;
        }

        return $refreshedAt === null || $now - $refreshedAt >= $seconds;
    }

    /**
     * The shortest interval among these choices, `null` when there are none.
     *
     * @param string[] $choices each journal's choice, as `getListsRefresh()` reads it
     */
    public static function shortestInterval(array $choices): ?int
    {
        if ($choices === []) {
            return null;
        }

        return min(array_map(fn (string $choice) => Constants::CODECHECK_LISTS_REFRESH_SECONDS[$choice], $choices));
    }
}
