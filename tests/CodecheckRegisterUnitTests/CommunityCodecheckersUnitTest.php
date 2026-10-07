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
 *   Reading them is tested with the network answered by address (#191).
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckRegisterUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CommunityCodecheckers;
use APP\plugins\generic\codecheck\tests\Support\Network;
use Illuminate\Http\Client\Factory;
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

    private const MAIN = 'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/codecheckers.csv';
    private const AGILE = 'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/agile-codecheckers.csv';
    private const INSTITUTIONAL = 'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/institutional-codecheckers.csv';

    private function list(string $handle, string $orcid): string
    {
        return "name,handle,ORCID\nSomeone,@{$handle},{$orcid}\n";
    }

    private function http(array $answers): Factory
    {
        return Network::factory($answers);
    }

    public function testTheThreeListsAreReadTogether(): void
    {
        $http = $this->http([
            self::MAIN => Factory::response($this->list('first', '0000-0002-1825-0097')),
            self::AGILE => Factory::response($this->list('second', '0000-0001-5109-3700')),
            self::INSTITUTIONAL => Factory::response($this->list('third', '0000-0001-8607-8025')),
        ]);

        $read = CommunityCodecheckers::read($http);

        $this->assertTrue($read['complete']);
        $this->assertSame([
            '0000-0002-1825-0097' => 'first',
            '0000-0001-5109-3700' => 'second',
            '0000-0001-8607-8025' => 'third',
        ], $read['usernames']);
        $http->assertSentCount(3);
    }

    public function testAListThatDoesNotAnswerLeavesTheReadIncomplete(): void
    {
        $http = $this->http([]);
        $http->fake([
            self::MAIN => Factory::response($this->list('first', '0000-0002-1825-0097')),
            self::AGILE => $http->failedConnection('timed out'),
            self::INSTITUTIONAL => Factory::response('', 404),
        ]);

        $read = CommunityCodecheckers::read($http);

        // What did load is kept, but a refresh must not replace a whole list with part of it.
        $this->assertFalse($read['complete']);
        $this->assertSame(['0000-0002-1825-0097' => 'first'], $read['usernames']);
    }

    /** Pages served with 200 that hold no codechecker are not a read. */
    public function testListsThatYieldNobodyAreNotARead(): void
    {
        $http = $this->http([
            'raw.githubusercontent.com/*' => Factory::response('<html>Not found</html>'),
        ]);

        $read = CommunityCodecheckers::read($http);

        $this->assertFalse($read['complete']);
        $this->assertSame([], $read['usernames']);
    }
}
