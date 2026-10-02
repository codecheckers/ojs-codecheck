<?php

/**
 * @file tests/DoiDepositUnitTests/CodecheckDepositLinksUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #19 — which CODECHECK links an article's DOI deposit carries.
 */

namespace APP\plugins\generic\codecheck\tests\DoiDepositUnitTests;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DoiDeposit\CodecheckDepositLinks;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use PKP\tests\PKPTestCase;

class CodecheckDepositLinksUnitTest extends PKPTestCase
{
    private const PUBLISHED = Constants::CODECHECK_STATUS_PUBLISHED_FULL_REPRODUCTION;

    protected function setUp(): void
    {
        parent::setUp();
        // CodecheckSubmission lives in the DAO's file.
        class_exists(CodecheckSubmissionDAO::class);
    }

    private function record(string $report, array $repositories = []): CodecheckSubmission
    {
        return new CodecheckSubmission([
            'submission_id' => 5,
            'report' => $report,
            'repository' => json_encode(['repositories' => $repositories]),
        ]);
    }

    public function testThePublishedCertificateIsTheReviewAndThePublicRepositoriesTheSupplements()
    {
        $record = $this->record('https://doi.org/10.5281/zenodo.1234567', [
            ['url' => 'https://github.com/example/code', 'containsCodecheckYaml' => true],
            ['url' => 'https://doi.org/10.5281/zenodo.7654321'],
            ['url' => 'https://github.com/example/private', 'hidden' => true],
        ]);

        $this->assertSame([
            ['relation' => 'review', 'type' => 'doi', 'identifier' => '10.5281/zenodo.1234567'],
            ['relation' => 'supplement', 'type' => 'url', 'identifier' => 'https://github.com/example/code'],
            ['relation' => 'supplement', 'type' => 'doi', 'identifier' => '10.5281/zenodo.7654321'],
        ], CodecheckDepositLinks::forCheck(true, self::PUBLISHED, $record));
    }

    /**
     * Before the certificate is published its DOI may not exist, and Crossref
     * refuses a relation to a DOI it cannot find.
     */
    public function testNothingIsLinkedBeforeTheCertificateIsPublished()
    {
        $record = $this->record('https://doi.org/10.5281/zenodo.1234567', [['url' => 'https://github.com/example/code']]);

        foreach (array_diff(Constants::CODECHECK_STATUSES, Constants::CODECHECK_STATUSES_CERTIFICATE_PUBLISHED) as $status) {
            $this->assertSame([], CodecheckDepositLinks::forCheck(true, $status, $record), $status);
        }
        $this->assertSame([], CodecheckDepositLinks::forCheck(true, Constants::CODECHECK_STATUS_PENDING, $record));
        $this->assertNotSame([], CodecheckDepositLinks::forCheck(true, Constants::CODECHECK_STATUS_PUBLISHED_PARTIAL_REPRODUCTION, $record));
    }

    public function testNothingIsLinkedForASubmissionThatTakesNoPartOrHasNoRecord()
    {
        $record = $this->record('https://doi.org/10.5281/zenodo.1234567');

        $this->assertSame([], CodecheckDepositLinks::forCheck(false, self::PUBLISHED, $record));
        $this->assertSame([], CodecheckDepositLinks::forCheck(true, self::PUBLISHED, null));
    }

    /** A report that is not a DOI is not a certificate DOI to relate to. */
    public function testAReportThatIsNotADoiIsNotLinked()
    {
        foreach (['', 'https://example.org/report.pdf', 'javascript:alert(1)', '2026-001'] as $report) {
            $this->assertSame([], CodecheckDepositLinks::forCheck(true, self::PUBLISHED, $this->record($report)), $report);
        }

        $bare = CodecheckDepositLinks::forCheck(true, self::PUBLISHED, $this->record('10.5281/zenodo.42'));
        $this->assertSame([['relation' => 'review', 'type' => 'doi', 'identifier' => '10.5281/zenodo.42']], $bare);
    }

    /** A stored address that is not a web address never reaches a deposit (#170). */
    public function testARepositoryThatIsNotAWebAddressIsNotLinked()
    {
        $record = $this->record('', [['url' => 'javascript:alert(1)'], ['url' => 'ftp://example.org/data']]);

        $this->assertSame([], CodecheckDepositLinks::forCheck(true, self::PUBLISHED, $record));
    }

    public function testDoiFromUrl()
    {
        $this->assertSame('10.5281/zenodo.1', CodecheckDepositLinks::doiFromUrl('https://doi.org/10.5281/zenodo.1'));
        $this->assertSame('10.5281/zenodo.1', CodecheckDepositLinks::doiFromUrl(' http://dx.doi.org/10.5281/zenodo.1 '));
        $this->assertSame('10.1000.10/abc', CodecheckDepositLinks::doiFromUrl('https://DOI.org/10.1000.10/abc'));
        $this->assertNull(CodecheckDepositLinks::doiFromUrl('https://zenodo.org/records/1'));
        $this->assertNull(CodecheckDepositLinks::doiFromUrl('10.5281/zenodo.1'));
        $this->assertNull(CodecheckDepositLinks::doiFromUrl('https://doi.org/'));
    }
}
