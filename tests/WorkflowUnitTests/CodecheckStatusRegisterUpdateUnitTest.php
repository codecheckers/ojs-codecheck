<?php

/**
 * @file tests/WorkflowUnitTests/CodecheckStatusRegisterUpdateUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckStatusRegisterUpdateUnitTest
 *
 * @brief Tests the rule that decides which register labels a status change adds
 *   and removes (#174).
 *
 * The rest of the class reaches the database, the plugin settings and GitHub in
 * its first lines, and is covered by the live register test instead.
 */

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusRegisterUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class CodecheckStatusRegisterUpdateUnitTest extends PKPTestCase
{
    private const NEEDS = Constants::CODECHECK_REGISTER_NEEDS_CODECHECKER_LABEL;
    private const IN_PROGRESS = Constants::CODECHECK_REGISTER_WORK_IN_PROGRESS_LABEL;

    /** The whole rule as a table: status, labels on the issue, what changes. */
    public static function statusLabelCases(): array
    {
        return [
            'a check with no codechecker says so' => [
                Constants::CODECHECK_STATUS_NEEDS_CODECHECKER,
                [],
                ['add' => [self::NEEDS], 'remove' => []],
            ],
            // The transition #174 was reported for.
            'assigning a codechecker swaps the two labels' => [
                Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER,
                [self::NEEDS, 'id assigned', 'journal'],
                ['add' => [self::IN_PROGRESS], 'remove' => [self::NEEDS]],
            ],
            'a label already in place is not added again' => [
                Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => []],
            ],
            // Still open: the comment says it stalled and who it waits on.
            'a stalled author changes nothing' => [
                Constants::CODECHECK_STATUS_STALLED_AUTHOR,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => []],
            ],
            'a stalled codechecker changes nothing' => [
                Constants::CODECHECK_STATUS_STALLED_CODECHECKER,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => []],
            ],
            'an unsuccessful check is no longer in progress' => [
                Constants::CODECHECK_STATUS_COMPLETED_UNSUCCESSFUL,
                [self::NEEDS, self::IN_PROGRESS],
                ['add' => [], 'remove' => [self::NEEDS, self::IN_PROGRESS]],
            ],
            'a partial reproduction is no longer in progress' => [
                Constants::CODECHECK_STATUS_COMPLETED_PARTIAL_REPRODUCTION,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => [self::IN_PROGRESS]],
            ],
            'a full reproduction is no longer in progress' => [
                Constants::CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => [self::IN_PROGRESS]],
            ],
            'a published partial certificate is no longer in progress' => [
                Constants::CODECHECK_STATUS_PUBLISHED_PARTIAL_REPRODUCTION,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => [self::IN_PROGRESS]],
            ],
            'a published full certificate is no longer in progress' => [
                Constants::CODECHECK_STATUS_PUBLISHED_FULL_REPRODUCTION,
                [self::IN_PROGRESS],
                ['add' => [], 'remove' => [self::IN_PROGRESS]],
            ],
            'a finished check that carries nothing needs no change' => [
                Constants::CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION,
                ['id assigned', 'journal'],
                ['add' => [], 'remove' => []],
            ],
        ];
    }

    #[DataProvider('statusLabelCases')]
    public function testLabelChanges(string $status, array $currentLabels, array $expected)
    {
        $this->assertSame(
            $expected,
            CodecheckStatusRegisterUpdate::labelChanges(
                CodecheckStatusRegisterUpdate::wantedLabels($status),
                $currentLabels
            )
        );
    }

    /**
     * The labels a register issue carries are mostly not the plugin's: the
     * venue, the check's origin, and whatever a human added. No status change
     * may remove any of them (#174).
     */
    public function testLabelsThePluginDoesNotOwnAreNeverRemoved()
    {
        $foreign = [
            'id assigned',
            'journal',
            'check-nl',
            'buddy exchange',
            'help welcome',
            'metadata pending',
            'lifecycle journal',
        ];

        foreach (array_keys(Constants::CODECHECK_REGISTER_STATUS_LABELS) as $status) {
            $changes = CodecheckStatusRegisterUpdate::labelChanges(
                CodecheckStatusRegisterUpdate::wantedLabels($status),
                $foreign
            );

            $this->assertSame(
                [],
                array_intersect($changes['remove'], $foreign),
                "{$status} would remove a label the plugin does not own"
            );
        }
    }

    /**
     * "Unknown" is not a state to write into someone else's repository. No
     * status an editor can record reaches this — the map is total over
     * `CODECHECK_STATUSES` — but one added to the plugin without a mapping would.
     */
    public function testAnUnmappedStatusHasNoWantedLabels()
    {
        $this->assertNull(CodecheckStatusRegisterUpdate::wantedLabels(Constants::CODECHECK_STATUS_PENDING));
        $this->assertNull(CodecheckStatusRegisterUpdate::wantedLabels('plugins.generic.codecheck.status.inventedLater'));
    }

    /**
     * A recorded issue number is only an address together with the repository it
     * was recorded in: a journal that moves to another register keeps the old
     * numbers on its submissions, and that number in the new register belongs to
     * somebody else's check (#174).
     */
    public function testAnIssueInAnotherRegisterIsNotOurs()
    {
        $this->assertTrue(CodecheckStatusRegisterUpdate::issueUrlIsInRegister(
            'https://github.com/codecheckers/testing-dev-register/issues/12',
            'codecheckers',
            'testing-dev-register'
        ));

        $this->assertFalse(
            CodecheckStatusRegisterUpdate::issueUrlIsInRegister(
                'https://github.com/codecheckers/testing-dev-register/issues/12',
                'codecheckers',
                'register'
            ),
            'the same number in the production register is a different check'
        );

        $this->assertFalse(CodecheckStatusRegisterUpdate::issueUrlIsInRegister(
            'https://github.com/someone-else/register/issues/12',
            'codecheckers',
            'register'
        ));

        // A record from before the URL was stored is accepted, or the plugin
        // would stop updating issues it legitimately opened.
        foreach ([null, '', 42, []] as $noUrl) {
            $this->assertTrue(
                CodecheckStatusRegisterUpdate::issueUrlIsInRegister($noUrl, 'codecheckers', 'register'),
                'a record with no usable URL is accepted'
            );
        }
    }

    /** An unmapped status reaches labelChanges() as null and changes nothing. */
    public function testNoWantedLabelsMeansNoChanges()
    {
        $this->assertSame(
            ['add' => [], 'remove' => []],
            CodecheckStatusRegisterUpdate::labelChanges(null, [self::NEEDS, self::IN_PROGRESS])
        );
    }

    /** Every status an editor can record has a mapping, or it would do nothing. */
    public function testEveryRecordableStatusIsMapped()
    {
        foreach (Constants::CODECHECK_STATUSES as $status) {
            $this->assertArrayHasKey(
                $status,
                Constants::CODECHECK_REGISTER_STATUS_LABELS,
                "{$status} has no register label mapping"
            );
        }
    }

    /**
     * The managed list is the authority on what may be removed, and the map says
     * what belongs where — so a label the map asks for but the list does not
     * carry would be added and then never taken off again. The two are declared
     * separately on purpose; this is what keeps them in step.
     */
    public function testTheStatusMapOnlyAsksForManagedLabels()
    {
        foreach (Constants::CODECHECK_REGISTER_STATUS_LABELS as $status => $labels) {
            $this->assertSame(
                [],
                array_diff($labels, Constants::CODECHECK_REGISTER_MANAGED_LABELS),
                "{$status} wants a label the plugin does not manage"
            );
        }
    }
}
