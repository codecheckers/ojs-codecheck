<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use PKP\tests\PKPTestCase;
use Illuminate\Support\Facades\DB;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckSubmissionDAOUnitTest.php
 *
 * @class CodecheckSubmissionDAOUnitTest
 *
 * @brief Tests for the CodecheckSubmissionDAO class
 */
class CodecheckSubmissionDAOUnitTest extends PKPTestCase
{
    private CodecheckSubmissionDAO $dao;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new CodecheckSubmissionDAO();
    }

    public function testGetBySubmissionIdReturnsNullWhenNoData()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 123)
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('first')
            ->once()
            ->andReturn(null);

        $result = $this->dao->getBySubmissionId(123);
        
        $this->assertNull($result);
    }

    public function testGetBySubmissionIdReturnsCodecheckSubmissionWhenDataExists()
    {
        $mockData = (object)[
            'submission_id' => 123,
            'opt_in' => 1,
            'code_repository' => 'https://github.com/test/repo',
            'data_repository' => '',
            'dependencies' => 'Python 3.8',
            'execution_instructions' => 'Run main.py',
            'certificate_doi' => '',
            'certificate_url' => '',
            'codechecker_names' => '',
            'check_status' => '',
            'certificate_date' => null,
        ];

        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 123)
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('first')
            ->once()
            ->andReturn($mockData);

        $result = $this->dao->getBySubmissionId(123);
        
        $this->assertInstanceOf(CodecheckSubmission::class, $result);
        $this->assertSame(123, $result->getSubmissionId());
        $this->assertTrue($result->getOptIn());
        $this->assertSame('https://github.com/test/repo', $result->getCodeRepository());
    }

    public function testInsertOrUpdateInsertsNewRecord()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->twice()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 456)
            ->twice()
            ->andReturnSelf();
        
        DB::shouldReceive('first')
            ->once()
            ->andReturn(null);

        DB::shouldReceive('insert')
            ->once()
            ->with(\Mockery::on(function ($data) {
                return $data['submission_id'] === 456
                    && $data['opt_in'] === 1
                    && $data['code_repository'] === 'https://github.com/new/repo';
            }))
            ->andReturn(true);

        $data = [
            'opt_in' => true,
            'code_repository' => 'https://github.com/new/repo',
        ];

        $this->dao->insertOrUpdate(456, $data);

        $this->assertTrue(true); // Test completed without exceptions
    }

    public function testInsertOrUpdateUpdatesExistingRecord()
    {
        $existingData = (object)[
            'submission_id' => 789,
            'opt_in' => 0,
            'code_repository' => 'https://github.com/old/repo',
        ];

        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->times(3)
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 789)
            ->times(3)
            ->andReturnSelf();
        
        DB::shouldReceive('first')
            ->once()
            ->andReturn($existingData);

        DB::shouldReceive('update')
            ->once()
            ->with(\Mockery::on(function ($data) {
                return $data['opt_in'] === 1
                    && $data['code_repository'] === 'https://github.com/updated/repo';
            }))
            ->andReturn(1);

        $data = [
            'opt_in' => true,
            'code_repository' => 'https://github.com/updated/repo',
        ];

        $this->dao->insertOrUpdate(789, $data);

        $this->assertTrue(true); // Test completed without exceptions
    }
}