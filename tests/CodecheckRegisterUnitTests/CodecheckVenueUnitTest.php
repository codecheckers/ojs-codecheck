<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenue;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/RetrieveReserveIdentifiersUnitTests/CodecheckVenueUnitTest.php
 *
 * @class CodecheckVenueUnitTest
 *
 * @brief Tests for the CodecheckVenue class
 */
class CodecheckVenueUnitTest extends PKPTestCase
{
    protected function setUp(): void
	{
		parent::setUp();
	}

    public function testSetAndGetVenueName()
    {
        $oldVenueName = 'testVenueName';
        $venueName = 'check-nl';
        $venue = new CodecheckVenue('testVenueType', $oldVenueName);
        $venue->setVenueName($venueName);
        $this->assertSame($venueName, $venue->getVenueName());
        $this->assertNotEquals($oldVenueName, $venue->getVenueName());
    }

    public function testSetAndGetVenueType()
    {
        $oldVenueType = 'testVenueType';
        $venueType = 'community';
        $venue = new CodecheckVenue($oldVenueType, 'testVenueName');
        $venue->setVenueType($venueType);
        $this->assertSame($venueType, $venue->getVenueType());
        $this->assertNotEquals($oldVenueType, $venue->getVenueType());
    }
}