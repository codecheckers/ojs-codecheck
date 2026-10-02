<?php

/**
 * @file tests/CodecheckRegisterUnitTests/CommunityCodecheckersUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CommunityCodecheckersUnitTest
 *
 * @brief Reading the community's lists of codecheckers (#186). The fixtures are
 *   the headers of the three real lists, with rows of the shapes they hold.
 *   Fetching is not tested: it is the network.
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CommunityCodecheckers;
use PKP\tests\PKPTestCase;

class CommunityCodecheckersUnitTest extends PKPTestCase
{
    public function testTheMainListMapsAnOrcidToTheHandleWithoutItsAt(): void
    {
        $csv = "name,handle,ORCID,contact,fields,languages,ecr_until,ecr_checked,fediverse\n"
            . "Stephen Eglen,@sje30,0000-0001-8607-8025,see ORCID page,neuroscience,\"R, Matlab\",2005-07,2026-08;https://orcid.org/0000-0001-8607-8025,@sje@fosstodon.org\n"
            . "Daniel Nüst,@nuest,0000-0002-0024-5046,see ORCID page,\"geospatial data analysis,containers\",\"R, Python, JavaScript, Java\",2030-02,2026-08,@nuest@mstdn.social\n";

        $this->assertSame([
            '0000-0001-8607-8025' => 'sje30',
            '0000-0002-0024-5046' => 'nuest',
        ], CommunityCodecheckers::parse($csv));
    }

    public function testColumnsAreFoundByHeaderAndNaIsSkipped(): void
    {
        $csv = "name,handle,ORCID,institution,fediverse\r\n"
            . "Alex Dewar,@alexdewar,NA,Imperial College London,\r\n"
            . "Sam Langton,@langtonhugh,0000-0002-1322-1553,Amsterdam UMC,\r\n";

        $this->assertSame(['0000-0002-1322-1553' => 'langtonhugh'], CommunityCodecheckers::parse($csv));
    }

    public function testAnIdentifierThatIsNotOneOrAHandleThatIsNotOneIsSkipped(): void
    {
        $csv = "name,ORCID,handle\n"
            . "Mistyped,0000-0002-1825-0098,@someone\n"
            . "No handle,0000-0002-1825-0097,\n"
            . "Not a username,0000-0001-5109-3700,@two words\n";

        $this->assertSame([], CommunityCodecheckers::parse($csv));
    }

    public function testTheFirstRowForAnIdentifierWins(): void
    {
        $csv = "name,handle,ORCID\n"
            . "A,@first,0000-0002-1825-0097\n"
            . "B,@second,0000-0002-1825-0097\n";

        $this->assertSame(['0000-0002-1825-0097' => 'first'], CommunityCodecheckers::parse($csv));
    }

    public function testAListWithoutTheColumnsGivesNothing(): void
    {
        $this->assertSame([], CommunityCodecheckers::parse(''));
        $this->assertSame([], CommunityCodecheckers::parse("name,ORCID\nA,0000-0002-1825-0097\n"));
        $this->assertSame([], CommunityCodecheckers::parse('<html>Not found</html>'));
    }
}
