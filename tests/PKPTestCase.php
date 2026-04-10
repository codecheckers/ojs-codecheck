<?php

namespace PKP\tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case class for PKP tests
 * This is a simplified version since OJS doesn't provide one
 */
class PKPTestCase extends TestCase
{
    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}