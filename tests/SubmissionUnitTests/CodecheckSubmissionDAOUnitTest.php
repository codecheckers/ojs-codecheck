<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use PKP\tests\PKPTestCase;
use Illuminate\Support\Facades\DB;

class CodecheckSubmissionDAOUnitTest extends PKPTestCase
{
    private CodecheckSubmissionDAO $dao;

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
            'version' => 'latest',
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => 'https://github.com/test/repo',
            'source' => '',
            'codecheckers' => '[]',
            'certificate' => '',
            'check_time' => null,
            'summary' => '',
            'report' => '',
            'additional_content' => '',
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
        $this->assertSame('https://github.com/test/repo', $result->getRepository());
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
                    && $data['repository'] === 'https://github.com/new/repo';
            }))
            ->andReturn(true);

        $this->dao->insertOrUpdate(456, [
            'repository' => 'https://github.com/new/repo'
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testInsertOrUpdateUpdatesExistingRecord()
    {
        $existingData = (object)[
            'submission_id' => 789,
            'version' => 'latest',
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => 'https://github.com/old/repo',
            'source' => '',
            'codecheckers' => '[]',
            'certificate' => '',
            'check_time' => null,
            'summary' => '',
            'report' => '',
            'additional_content' => '',
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
                return $data['repository'] === 'https://github.com/updated/repo';
            }))
            ->andReturn(1);

        $this->dao->insertOrUpdate(789, [
            'repository' => 'https://github.com/updated/repo'
        ]);

        $this->expectNotToPerformAssertions();
    }
}