<?php

/**
 * @file tests/OrcidUnitTests/OrcidDepositServiceUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class OrcidDepositServiceUnitTest
 *
 * @brief Which authorised codecheckers a deposit run is for.
 *
 * `depositForSubmission()` itself cannot be reached from here — its first lines
 * want a journal context, `Repo::submission()` and the database — so what is
 * pinned is the rule it now asks before touching ORCID at all: whether there is
 * anything to deposit (#182). The ordering that rule enforces is covered by the
 * live ORCID test, `dev/live-orcid-tests.md`.
 */

namespace APP\plugins\generic\codecheck\tests\OrcidUnitTests;

use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use Illuminate\Support\Collection;
use PKP\tests\PKPTestCase;

class OrcidDepositServiceUnitTest extends PKPTestCase
{
    /** A token row in the shape the DAO answers with. */
    private function row(string $orcidId): object
    {
        return (object) ['orcid_id' => $orcidId, 'access_token' => 'token-' . $orcidId];
    }

    public function testEveryAuthorizedCodecheckerIsATargetByDefault(): void
    {
        $rows = [$this->row('0000-0002-1825-0097'), $this->row('0000-0001-5109-3700')];

        $this->assertSame($rows, OrcidDepositService::depositTargets($rows));
    }

    /** A reviewer may deposit their own activity and nobody else's (#173). */
    public function testOnlyTheNamedRecordIsATargetWhenOneIsAskedFor(): void
    {
        $mine = $this->row('0000-0002-1825-0097');
        $theirs = $this->row('0000-0001-5109-3700');

        $this->assertSame(
            [$mine],
            OrcidDepositService::depositTargets([$theirs, $mine], '0000-0002-1825-0097')
        );
    }

    /**
     * The empty answer is what keeps a publish away from ORCID, so it is the
     * case worth being sure of.
     */
    public function testNothingIsATargetWithoutAnAuthorizedCodechecker(): void
    {
        $this->assertSame([], OrcidDepositService::depositTargets([]));
    }

    public function testNothingIsATargetWhenTheNamedRecordIsNotAmongThem(): void
    {
        $this->assertSame(
            [],
            OrcidDepositService::depositTargets([$this->row('0000-0001-5109-3700')], '0000-0002-1825-0097')
        );
    }

    /**
     * **The shape the DAO actually answers with**, which every other test here
     * does not: they hand over a plain array, so an `array` type would satisfy
     * them and fail in production.
     */
    public function testACollectionIsReadTheSameWayAsAnArray(): void
    {
        $mine = $this->row('0000-0002-1825-0097');
        $rows = new Collection([$this->row('0000-0001-5109-3700'), $mine]);

        $this->assertSame([$mine], OrcidDepositService::depositTargets($rows, '0000-0002-1825-0097'));
        $this->assertCount(2, OrcidDepositService::depositTargets($rows));
    }

    public function testAnEmptyCollectionIsNoTargets(): void
    {
        $this->assertSame([], OrcidDepositService::depositTargets(new Collection()));
    }

    /**
     * The two sides arrive in different shapes, and this is the case nothing
     * covered.
     *
     * `codecheck_orcid_tokens.orcid_id` holds the bare iD — a 20-character
     * column that could hold no more — while the API asks for a deposit on
     * behalf of a reviewer with `$user->getOrcid()`, which OJS stores as the
     * full URI. Compared as given, a reviewer depositing their own activity
     * matched nothing and the button did nothing, silently.
     */
    public function testAnIdentifierGivenAsAUriMatchesTheBareOneOnTheRow(): void
    {
        $mine = $this->row('0000-0002-1825-0097');

        $this->assertSame(
            [$mine],
            OrcidDepositService::depositTargets([$mine], 'https://orcid.org/0000-0002-1825-0097')
        );
    }

    public function testTheSandboxUriMatchesTheSameBareIdentifier(): void
    {
        $mine = $this->row('0000-0002-1825-0097');

        $this->assertSame(
            [$mine],
            OrcidDepositService::depositTargets([$mine], 'https://sandbox.orcid.org/0000-0002-1825-0097')
        );
    }

    /** Normalising must not make two different people match. */
    public function testADifferentIdentifierStillDoesNotMatch(): void
    {
        $this->assertSame(
            [],
            OrcidDepositService::depositTargets(
                [$this->row('0000-0001-5109-3700')],
                'https://orcid.org/0000-0002-1825-0097'
            )
        );
    }
}
