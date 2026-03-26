<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use PKP\tests\PKPTestCase;

class CodecheckSubmissionUnitTest extends PKPTestCase
{
    public function testGetManifestDecodesJsonCorrectly()
    {
        $manifest = [['file' => 'output.png', 'comment' => 'Main result']];
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'manifest' => json_encode($manifest)
        ]);
        $this->assertSame($manifest, $submission->getManifest());
    }

    public function testGetManifestReturnsEmptyArrayWhenNotSet()
    {
        $submission = new CodecheckSubmission(['submission_id' => 1]);
        $this->assertSame([], $submission->getManifest());
    }

    public function testGetCodecheckersDecodesJsonCorrectly()
    {
        $codecheckers = [['name' => 'John Doe', 'orcid' => '0000-0001-2345-6789']];
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'codecheckers' => json_encode($codecheckers)
        ]);
        $this->assertSame($codecheckers, $submission->getCodecheckers());
    }

    public function testHasCompletedCheckReturnsTrueWithCertificate()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'certificate' => 'CODECHECK-2025-001'
        ]);
        $this->assertTrue($submission->hasCompletedCheck());
    }

    public function testHasCompletedCheckReturnsFalseWithoutCertificate()
    {
        $submission = new CodecheckSubmission(['submission_id' => 1]);
        $this->assertFalse($submission->hasCompletedCheck());
    }

    public function testHasAssignedCheckerReturnsTrueWithCodecheckers()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'codecheckers' => json_encode([['name' => 'John Doe']])
        ]);
        $this->assertTrue($submission->hasAssignedChecker());
    }

    public function testGetCertificateLinkBuildsUrlForCodecheckId()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'certificate' => 'CODECHECK-2025-001'
        ]);
        $this->assertSame(
            'https://codecheck.org.uk/certificate/CODECHECK-2025-001',
            $submission->getCertificateLink()
        );
    }

    public function testGetCertificateLinkReturnsUrlAsIs()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'certificate' => 'https://example.com/cert'
        ]);
        $this->assertSame('https://example.com/cert', $submission->getCertificateLink());
    }

    public function testGetDoiLinkBuildsCorrectUrl()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'report' => '10.5281/zenodo.123456'
        ]);
        $this->assertSame('https://doi.org/10.5281/zenodo.123456', $submission->getDoiLink());
    }

    public function testGetCodecheckerNamesReturnsCommaSeparatedString()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 1,
            'codecheckers' => json_encode([
                ['name' => 'John Doe'],
                ['name' => 'Jane Smith']
            ])
        ]);
        $this->assertSame('John Doe, Jane Smith', $submission->getCodecheckerNames());
    }
}