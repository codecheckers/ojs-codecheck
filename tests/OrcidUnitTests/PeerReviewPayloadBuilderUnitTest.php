<?php

/**
 * @file tests/OrcidUnitTests/PeerReviewPayloadBuilderUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class PeerReviewPayloadBuilderUnitTest
 *
 * @brief The ORCID peer-review group id a journal's deposits are filed under.
 *
 * `build()` itself wants a publication and the database, so what is pinned here
 * is the one rule that two classes have to agree on: the payload cites the
 * group id and `OrcidDepositService` registers it, and they derived it
 * separately until #182. A disagreement is not silent, but it is misreported:
 * ORCID refuses the item, and the reason recorded against the codechecker names
 * the group rather than the two derivations having drifted apart.
 */

namespace APP\plugins\generic\codecheck\tests\OrcidUnitTests;

use APP\plugins\generic\codecheck\classes\Orcid\PeerReviewPayloadBuilder;
use PKP\tests\PKPTestCase;

class PeerReviewPayloadBuilderUnitTest extends PKPTestCase
{
    public function testTheGroupIdIsTheJournalsIssnWhenItHasOne(): void
    {
        $this->assertSame('issn:2050-084X', PeerReviewPayloadBuilder::groupIdFor(['issn' => '2050-084X']));
    }

    public function testTheIssnIsTrimmed(): void
    {
        $this->assertSame('issn:2050-084X', PeerReviewPayloadBuilder::groupIdFor(['issn' => "  2050-084X\n"]));
    }

    /**
     * A journal with no ISSN still needs one group to file under, and it is the
     * same one for every such journal — deliberately, since ORCID has nothing
     * else to distinguish them by.
     */
    public function testAJournalWithoutAnIssnFallsBackToTheGeneratedGroup(): void
    {
        $generated = 'orcid-generated:codecheck-ojs';

        $this->assertSame($generated, PeerReviewPayloadBuilder::groupIdFor(['issn' => '']));
        $this->assertSame($generated, PeerReviewPayloadBuilder::groupIdFor(['issn' => '   ']));
        $this->assertSame($generated, PeerReviewPayloadBuilder::groupIdFor(['issn' => null]));
        $this->assertSame($generated, PeerReviewPayloadBuilder::groupIdFor([]));
    }
}
