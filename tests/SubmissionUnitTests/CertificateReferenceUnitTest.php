<?php

/**
 * @file tests/SubmissionUnitTests/CertificateReferenceUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #183 — the CODECHECK certificate as a line in the article's
 *        references, and how that line joins the references already there.
 */

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CertificateReference;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class CertificateReferenceUnitTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // CodecheckSubmission lives in the DAO's file.
        class_exists(CodecheckSubmissionDAO::class);
    }

    private function record(array $data = []): CodecheckSubmission
    {
        return new CodecheckSubmission($data + [
            'submission_id' => 5,
            'certificate' => '2026-001',
            'report' => 'https://doi.org/10.5281/zenodo.1234567',
            'check_time' => '2026-03-14 10:00:00',
            'codecheckers' => json_encode([['name' => 'Stephen Eglen'], ['name' => 'Daniel Nüst']]),
        ]);
    }

    private const LINE = 'Stephen Eglen, Daniel Nüst. (2026). CODECHECK certificate 2026-001. Zenodo. https://doi.org/10.5281/zenodo.1234567';

    public function testFormatWithEveryField()
    {
        $this->assertSame(self::LINE, CertificateReference::format($this->record()));
    }

    public function testFormatWithoutACheckTimeOmitsTheYear()
    {
        $this->assertSame(
            'Stephen Eglen, Daniel Nüst. CODECHECK certificate 2026-001. Zenodo. https://doi.org/10.5281/zenodo.1234567',
            CertificateReference::format($this->record(['check_time' => null]))
        );
    }

    public function testOnlyAZenodoDoiSaysZenodo()
    {
        $this->assertSame(
            'Stephen Eglen, Daniel Nüst. (2026). CODECHECK certificate 2026-001. https://doi.org/10.17605/osf.io/abc',
            CertificateReference::format($this->record(['report' => 'https://doi.org/10.17605/osf.io/abc']))
        );
    }

    public function testAReportAtAnotherAddressIsTheLink()
    {
        $this->assertSame(
            'Stephen Eglen, Daniel Nüst. (2026). CODECHECK certificate 2026-001. https://example.org/report.pdf',
            CertificateReference::format($this->record(['report' => 'https://example.org/report.pdf']))
        );
    }

    public function testWithoutAReportTheRegisterPageIsTheLink()
    {
        $this->assertSame(
            'Stephen Eglen, Daniel Nüst. (2026). CODECHECK certificate 2026-001. https://codecheck.org.uk/register/certs/2026-001/',
            CertificateReference::format($this->record(['report' => '']))
        );
    }

    public function testACertificateStoredAsAnAddressIsCitedWithoutAnIdentifier()
    {
        $this->assertSame(
            'Stephen Eglen, Daniel Nüst. (2026). CODECHECK certificate. https://example.org/cert',
            CertificateReference::format($this->record(['certificate' => 'https://example.org/cert', 'report' => '']))
        );
    }

    public function testTheOldPrefixIsDropped()
    {
        $this->assertStringContainsString(
            'CODECHECK certificate 2026-001.',
            CertificateReference::format($this->record(['certificate' => 'CODECHECK-2026-001']))
        );
    }

    public function testNoCodecheckersStartsWithTheYear()
    {
        $this->assertStringStartsWith('(2026). CODECHECK certificate 2026-001.', CertificateReference::format($this->record(['codecheckers' => null])));
    }

    public function testNothingToCiteWithoutACertificateOrALink()
    {
        $this->assertNull(CertificateReference::format($this->record(['certificate' => ''])));
        $this->assertNull(CertificateReference::format($this->record(['certificate' => 'pending', 'report' => ''])));
    }

    public function testMergeIntoNoReferences()
    {
        $this->assertSame(self::LINE, CertificateReference::merge('', self::LINE, $this->record()));
    }

    public function testMergeAppendsAfterTheLastReference()
    {
        $this->assertSame(
            "A. (2020). One.\nB. (2021). Two.\n" . self::LINE,
            CertificateReference::merge("A. (2020). One.\nB. (2021). Two.\n\n", self::LINE, $this->record())
        );
    }

    #[DataProvider('linesReferringToTheCertificate')]
    public function testMergeReplacesALineReferringToTheCertificateWhereItStands(string $existing)
    {
        $this->assertSame(
            "A. (2020). One.\n" . self::LINE . "\nB. (2021). Two.",
            CertificateReference::merge("A. (2020). One.\n{$existing}\nB. (2021). Two.", self::LINE, $this->record())
        );
    }

    public static function linesReferringToTheCertificate(): array
    {
        return [
            'by DOI, in other capitals' => ['Eglen. CODECHECK. 10.5281/ZENODO.1234567'],
            'by register address' => ['See https://codecheck.org.uk/register/certs/2026-001/'],
            'by identifier' => ['Eglen, S. (2025). codecheck certificate 2026-001. Zenodo.'],
        ];
    }

    public function testMergeReadsWindowsLineEnds()
    {
        $this->assertSame(
            "A. (2020). One.\n" . self::LINE,
            CertificateReference::merge("A. (2020). One.\r\nCODECHECK certificate 2026-001.", self::LINE, $this->record())
        );
    }

    /** Only an act of removal removes an entry (#170). */
    public function testMergeLeavesASecondLineForTheCertificateAlone()
    {
        $this->assertSame(
            self::LINE . "\nCODECHECK certificate 2026-001, again.",
            CertificateReference::merge("CODECHECK certificate 2026-001.\nCODECHECK certificate 2026-001, again.", self::LINE, $this->record())
        );
    }

    public function testMergeDoesNotMatchAnotherCertificate()
    {
        $this->assertSame(
            "CODECHECK certificate 2026-0010.\n" . self::LINE,
            CertificateReference::merge('CODECHECK certificate 2026-0010.', self::LINE, $this->record())
        );
    }

    /** A DOI or address that only begins the same way is another reference. */
    public function testMergeLeavesAReferenceThatOnlyBeginsLikeTheCertificateAlone()
    {
        $dataset = 'Dataset. Zenodo. https://doi.org/10.5281/zenodo.12345678';
        $otherCertificate = 'See https://codecheck.org.uk/register/certs/2026-0010/';

        $this->assertSame(
            $dataset . "\n" . $otherCertificate . "\n" . self::LINE,
            CertificateReference::merge($dataset . "\n" . $otherCertificate, self::LINE, $this->record())
        );
    }

    /** The DOI is still found where a full stop or a bracket closes it. */
    public function testMergeFindsTheDoiBeforeClosingPunctuation()
    {
        foreach (['Eglen (10.5281/zenodo.1234567).', 'Eglen, 10.5281/zenodo.1234567.'] as $existing) {
            $this->assertSame(self::LINE, CertificateReference::merge($existing, self::LINE, $this->record()), $existing);
        }
    }

    /**
     * A certificate stored as an address has no DOI and no identifier; the line
     * written for it is still recognised by the address it links to, so a
     * second write changes nothing rather than appending a copy.
     */
    public function testALineForACertificateStoredAsAnAddressIsFoundAgain()
    {
        $record = $this->record(['certificate' => 'https://example.org/cert', 'report' => '']);
        $line = CertificateReference::format($record);

        $once = CertificateReference::merge('A. (2020). One.', $line, $record);
        $this->assertSame($once, CertificateReference::merge($once, $line, $record));
    }

    /** Unchanged means identical, so a caller can compare and write nothing. */
    public function testMergeAnswersTheReferencesUnchangedWhenTheLineIsThere()
    {
        $references = "A. (2020). One.\r\n" . self::LINE . "\r\n";

        $this->assertSame($references, CertificateReference::merge($references, self::LINE, $this->record()));
    }
}
