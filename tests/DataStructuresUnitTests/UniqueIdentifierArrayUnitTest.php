<?php

namespace APP\plugins\generic\codecheck\tests\DataStructuresUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueIdentifierArray;
use PKP\tests\PKPTestCase;

/**
 * Reserving a certificate identifier walks the register's issues and collects
 * the identifiers already taken. Two issues naming 2026-001 are two different
 * objects for the same identifier, so uniqueness has to be decided on the
 * printed form rather than on object identity — otherwise the same number can
 * be handed out twice.
 */
class UniqueIdentifierArrayUnitTest extends PKPTestCase
{
    /** An entry as the reservation code builds them. */
    private function entry(int $year, int $number, string $title = ''): array
    {
        return [
            'identifier' => new CertificateIdentifier($year, $number),
            'title' => $title,
        ];
    }

    public function testTheSameIdentifierFromADifferentObjectCountsAsPresent()
    {
        $identifiers = new UniqueIdentifierArray();
        $identifiers->add($this->entry(2026, 1, 'first'));

        $this->assertTrue($identifiers->contains($this->entry(2026, 1, 'a different issue')));
    }

    public function testADifferentNumberOrYearIsNotPresent()
    {
        $identifiers = new UniqueIdentifierArray();
        $identifiers->add($this->entry(2026, 1));

        $this->assertFalse($identifiers->contains($this->entry(2026, 2)));
        $this->assertFalse($identifiers->contains($this->entry(2025, 1)));
    }

    public function testAddingTheSameIdentifierTwiceKeepsOneEntry()
    {
        $identifiers = new UniqueIdentifierArray();
        $identifiers->add($this->entry(2026, 7, 'first'));
        $identifiers->add($this->entry(2026, 7, 'second'));

        $this->assertCount(1, $identifiers->toArray());
        // The first one wins: a later issue naming the same identifier does not
        // overwrite what is already recorded.
        $this->assertSame('first', $identifiers->toArray()[0]['title']);
    }

    public function testAnEmptyArrayContainsNothing()
    {
        $this->assertFalse((new UniqueIdentifierArray())->contains($this->entry(2026, 1)));
    }
}
