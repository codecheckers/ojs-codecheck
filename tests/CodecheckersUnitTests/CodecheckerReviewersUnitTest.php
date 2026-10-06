<?php

/**
 * @file tests/CodecheckersUnitTests/CodecheckerReviewersUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerReviewersUnitTest
 *
 * @brief Which of a reviewer's assignments is in force (#13): the rule behind
 *   who may be a codechecker. Reading the assignments needs the database and
 *   is covered by e2e.
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckersUnitTests;

use APP\plugins\generic\codecheck\classes\Codecheckers\CodecheckerReviewers;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\tests\PKPTestCase;

class CodecheckerReviewersUnitTest extends PKPTestCase
{
    private static function assignment(int $id, int $reviewerId, int $round, bool $cancelled = false, bool $declined = false): ReviewAssignment
    {
        $assignment = new ReviewAssignment();
        $assignment->setId($id);
        $assignment->setReviewerId($reviewerId);
        $assignment->setRound($round);
        $assignment->setCancelled($cancelled);
        $assignment->setDeclined($declined);

        return $assignment;
    }

    private static function idsByReviewer(array $assignments): array
    {
        return array_map(fn (ReviewAssignment $assignment) => $assignment->getId(), CodecheckerReviewers::currentOf($assignments));
    }

    public function testEachReviewersAssignmentOfTheLatestRoundIsInForce(): void
    {
        $this->assertSame([7 => 3, 8 => 2], self::idsByReviewer([
            self::assignment(1, 7, 1),
            self::assignment(2, 8, 1),
            self::assignment(3, 7, 2),
        ]));
    }

    /** A cancelled or declined latest assignment leaves none in force, whatever came before. */
    public function testACancelledOrDeclinedLatestAssignmentLeavesNone(): void
    {
        $this->assertSame([], self::idsByReviewer([
            self::assignment(1, 7, 1),
            self::assignment(2, 7, 2, cancelled: true),
            self::assignment(3, 8, 1, declined: true),
        ]));
    }

    public function testAnEarlierCancelledAssignmentDoesNotCount(): void
    {
        $this->assertSame([7 => 2], self::idsByReviewer([
            self::assignment(1, 7, 1, cancelled: true),
            self::assignment(2, 7, 2),
        ]));
    }

    public function testNoAssignmentsMeanNoReviewers(): void
    {
        $this->assertSame([], CodecheckerReviewers::currentOf([]));
    }

    /** The account of a reviewer assigned now; nobody else is one. */
    private static function accountEntry(int $userId): ?array
    {
        return $userId === 7 ? ['userId' => 7, 'name' => 'Cora Codechecker', 'orcid' => '0000-0002-1694-233X', 'github' => 'cora'] : null;
    }

    private static function resolve(array $incoming, array $stored): array
    {
        return CodecheckerReviewers::resolveEntries($incoming, $stored, self::accountEntry(...));
    }

    public function testANewLinkIsCopiedFromTheAccountWhateverTheRequestSays(): void
    {
        $result = self::resolve([['userId' => 7, 'name' => 'Someone else', 'orcid' => '0000-0002-1825-0097']], []);

        $this->assertNull($result['refusedKey']);
        $this->assertSame([self::accountEntry(7)], $result['entries']);
    }

    public function testANewLinkToSomeoneNotAssignedIsRefused(): void
    {
        $result = self::resolve([['userId' => 4, 'name' => 'Not a reviewer']], []);

        $this->assertSame('plugins.generic.codecheck.codecheckers.notAReviewer', $result['refusedKey']);
        $this->assertSame('Not a reviewer', $result['refusedName']);
    }

    /** A stored link keeps its stored entry, and is kept even when the reviewer is no longer assigned. */
    public function testAStoredLinkKeepsItsStoredEntry(): void
    {
        $stored = [['userId' => 5, 'name' => 'Former', 'orcid' => '', 'github' => '']];
        $result = self::resolve([['userId' => 5, 'name' => 'Changed', 'orcid' => '0000-0002-1825-0097']], $stored);

        $this->assertSame([['userId' => 5, 'name' => 'Former', 'orcid' => '', 'github' => '']], $result['entries']);
    }

    public function testAnUnlinkedEntryIsKeptOnlyAsStored(): void
    {
        $stored = [['name' => 'From an older record', 'orcid' => '0000-0002-1825-0097']];

        $kept = self::resolve($stored, $stored);
        $this->assertNull($kept['refusedKey']);
        $this->assertSame([['userId' => null, 'name' => 'From an older record', 'orcid' => '0000-0002-1825-0097', 'github' => '']], $kept['entries']);

        $changed = self::resolve([['name' => 'From an older record', 'orcid' => '']], $stored);
        $this->assertSame('plugins.generic.codecheck.codecheckers.notLinkedRefused', $changed['refusedKey']);

        $typed = self::resolve([['name' => 'Typed in']], []);
        $this->assertSame('plugins.generic.codecheck.codecheckers.notLinkedRefused', $typed['refusedKey']);
    }

    public function testAnAccountListedTwiceIsListedOnce(): void
    {
        $result = self::resolve([['userId' => 7], ['userId' => '7']], []);

        $this->assertSame([self::accountEntry(7)], $result['entries']);
    }
}
