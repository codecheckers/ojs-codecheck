<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckSubmissionUnitTest.php
 *
 * @class CodecheckSubmissionUnitTest
 *
 * @brief Tests for the CodecheckSubmission class
 */
class CodecheckSubmissionUnitTest extends PKPTestCase
{
    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetSubmissionId()
    {
        $data = ['submission_id' => 123];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame(123, $submission->getSubmissionId());
    }

    public function testGetOptInTrue()
    {
        $data = ['submission_id' => 1, 'opt_in' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertTrue($submission->getOptIn());
    }

    public function testGetOptInFalse()
    {
        $data = ['submission_id' => 1, 'opt_in' => 0];
        $submission = new CodecheckSubmission($data);
        
        $this->assertFalse($submission->getOptIn());
    }

    public function testGetCodeRepository()
    {
        $data = [
            'submission_id' => 1,
            'code_repository' => 'https://github.com/test/repo'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('https://github.com/test/repo', $submission->getCodeRepository());
    }

    public function testGetCodeRepositoryReturnsEmptyStringWhenNotSet()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('', $submission->getCodeRepository());
    }

    public function testGetDataRepository()
    {
        $data = [
            'submission_id' => 1,
            'data_repository' => 'https://zenodo.org/record/123'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('https://zenodo.org/record/123', $submission->getDataRepository());
    }

    public function testGetDependencies()
    {
        $data = [
            'submission_id' => 1,
            'dependencies' => 'Python 3.8, numpy, pandas'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('Python 3.8, numpy, pandas', $submission->getDependencies());
    }

    public function testGetExecutionInstructions()
    {
        $data = [
            'submission_id' => 1,
            'execution_instructions' => 'Run python main.py'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('Run python main.py', $submission->getExecutionInstructions());
    }

    public function testGetCertificateDoi()
    {
        $data = [
            'submission_id' => 1,
            'certificate_doi' => '10.5281/zenodo.123456'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('10.5281/zenodo.123456', $submission->getCertificateDoi());
    }

    public function testGetCertificateUrl()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://codecheck.org.uk/certificate-2025-001'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('https://codecheck.org.uk/certificate-2025-001', $submission->getCertificateUrl());
    }

    public function testGetCodecheckerNames()
    {
        $data = [
            'submission_id' => 1,
            'codechecker_names' => 'John Doe, Jane Smith'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('John Doe, Jane Smith', $submission->getCodecheckerNames());
    }

    public function testGetCheckStatus()
    {
        $data = [
            'submission_id' => 1,
            'check_status' => 'completed'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('completed', $submission->getCheckStatus());
    }

    public function testGetCertificateDate()
    {
        $data = [
            'submission_id' => 1,
            'certificate_date' => '2025-01-15'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('2025-01-15', $submission->getCertificateDate());
    }

    public function testGetCertificateDateReturnsNullWhenNotSet()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertNull($submission->getCertificateDate());
    }

    public function testHasCompletedCheckReturnsTrueWithDoi()
    {
        $data = [
            'submission_id' => 1,
            'certificate_doi' => '10.5281/zenodo.123456'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertTrue($submission->hasCompletedCheck());
    }

    public function testHasCompletedCheckReturnsTrueWithUrl()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://codecheck.org.uk/certificate-2025-001'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertTrue($submission->hasCompletedCheck());
    }

    public function testHasCompletedCheckReturnsFalseWithoutDoiOrUrl()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertFalse($submission->hasCompletedCheck());
    }

    public function testHasAssignedCheckerReturnsFalse()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertFalse($submission->hasAssignedChecker());
    }

    public function testGetCertificateLinkReturnsUrlWhenAvailable()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://codecheck.org.uk/certificate-2025-001'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('https://codecheck.org.uk/certificate-2025-001', $submission->getCertificateLink());
    }

    public function testGetCertificateLinkReturnsEmptyStringWhenNotAvailable()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('', $submission->getCertificateLink());
    }

    public function testGetDoiLinkReturnsDoiWhenAvailable()
    {
        $data = [
            'submission_id' => 1,
            'certificate_doi' => '10.5281/zenodo.123456'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('10.5281/zenodo.123456', $submission->getDoiLink());
    }

    public function testGetDoiLinkReturnsEmptyStringWhenNotAvailable()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('', $submission->getDoiLink());
    }

    public function testGetFormattedCertificateLinkTextWithValidCertificateId()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://codecheck.org.uk/register/certificate-2025-001'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('CODECHECK 2025-001', $submission->getFormattedCertificateLinkText());
    }

    public function testGetFormattedCertificateLinkTextWithCertificatePrefix()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://example.com/certificate-2024-123'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('CODECHECK 2024-123', $submission->getFormattedCertificateLinkText());
    }

    public function testGetFormattedCertificateLinkTextFallbackWithoutPattern()
    {
        $data = [
            'submission_id' => 1,
            'certificate_url' => 'https://example.com/some-certificate'
        ];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('View Certificate', $submission->getFormattedCertificateLinkText());
    }

    public function testGetFormattedCertificateLinkTextReturnsEmptyStringWhenNoUrl()
    {
        $data = ['submission_id' => 1];
        $submission = new CodecheckSubmission($data);
        
        $this->assertSame('', $submission->getFormattedCertificateLinkText());
    }
}