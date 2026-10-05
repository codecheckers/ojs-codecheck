<?php

/**
 * @file tests/TaskUnitTests/RefreshCodecheckListsUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class RefreshCodecheckListsUnitTest
 *
 * @brief When the scheduled refresh of the CODECHECK lists runs (#65). The
 *   refresh itself reads the network and writes the database and the cache,
 *   so only the decision is tested here.
 */

namespace APP\plugins\generic\codecheck\tests\TaskUnitTests;

use APP\plugins\generic\codecheck\classes\Tasks\RefreshCodecheckLists;
use PKP\tests\PKPTestCase;

class RefreshCodecheckListsUnitTest extends PKPTestCase
{
    private const NOW = 1_800_000_000;
    private const HOUR = 60 * 60;
    private const DAY = 24 * self::HOUR;
    private const WEEK = 7 * self::DAY;

    private static function daily(): ?int
    {
        return self::DAY;
    }

    private static function weekly(): ?int
    {
        return self::WEEK;
    }

    private static function isDue(?int $refreshedAt, callable $interval, bool $failedRecently = false): bool
    {
        return RefreshCodecheckLists::decide($interval, $refreshedAt, $failedRecently, self::NOW);
    }

    public function testNeverRefreshedIsDue()
    {
        $this->assertTrue(self::isDue(null, self::weekly(...)));
    }

    public function testNothingIsDueWhereNoJournalHasThePluginEnabled()
    {
        $this->assertFalse(self::isDue(null, fn () => null));
        $this->assertFalse(self::isDue(self::NOW - self::WEEK * 4, fn () => null));
    }

    public function testDailyIsDueOnceADayHasPassed()
    {
        $this->assertFalse(self::isDue(self::NOW - self::DAY + 1, self::daily(...)));
        $this->assertTrue(self::isDue(self::NOW - self::DAY, self::daily(...)));
    }

    public function testWeeklyIsDueOnceAWeekHasPassed()
    {
        $this->assertFalse(self::isDue(self::NOW - self::DAY * 3, self::weekly(...)));
        $this->assertTrue(self::isDue(self::NOW - self::WEEK, self::weekly(...)));
    }

    /** The journals are asked only once the shortest choice has passed. */
    public function testTheJournalsAreNotAskedWithinADay()
    {
        $asked = false;
        $interval = function () use (&$asked) {
            $asked = true;
            return self::DAY;
        };

        self::isDue(self::NOW - self::HOUR, $interval);
        $this->assertFalse($asked);
    }

    /** After a failure nothing is tried until its hour is up, however old the lists are. */
    public function testAFailedRefreshWaits()
    {
        $this->assertFalse(self::isDue(self::NOW - self::WEEK, self::daily(...), true));
        $this->assertFalse(self::isDue(null, self::daily(...), true));
    }

    public function testTheMostFrequentChoiceAmongTheJournalsApplies()
    {
        $this->assertSame(self::DAY, RefreshCodecheckLists::shortestInterval(['weekly', 'daily', 'weekly']));
        $this->assertSame(self::WEEK, RefreshCodecheckLists::shortestInterval(['weekly', 'weekly']));
        $this->assertNull(RefreshCodecheckLists::shortestInterval([]));
    }

    /**
     * The check runs as the schedule's filter, which nothing catches, so it
     * answers no rather than throwing — here because PHPUnit has no database,
     * on a real install because the labels table does not exist until a
     * journal enables the plugin.
     */
    public function testIsDueAnswersNoWhenTheLabelsCannotBeRead()
    {
        $this->assertFalse(RefreshCodecheckLists::isDue(self::daily(...)));
    }
}
