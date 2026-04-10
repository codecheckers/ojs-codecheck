<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckMetadataDAO;
use PKP\tests\PKPTestCase;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckMetadataDAOUnitTest.php
 *
 * @class CodecheckMetadataDAOUnitTest
 *
 * @brief Tests for the CodecheckMetadataDAO class
 */
class CodecheckMetadataDAOUnitTest extends PKPTestCase
{
    private CodecheckMetadataDAO $dao;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new CodecheckMetadataDAO();
    }

    // Test getBySubmissionId()
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

    public function testGetBySubmissionIdReturnsArrayWhenDataExists()
    {
        $mockData = (object)[
            'submission_id' => 123,
            'identifier' => '2025-001',
            'opt_in' => 1,
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
        
        $this->assertIsArray($result);
        $this->assertSame(123, $result['submission_id']);
        $this->assertSame('2025-001', $result['identifier']);
    }

    public function testGetBySubmissionIdDecodesJsonFields()
    {
        $mockData = (object)[
            'submission_id' => 123,
            'manifest_files' => '["file1.py", "file2.py"]',
            'repositories' => '["https://github.com/test/repo"]',
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
        
        $this->assertIsArray($result['manifest_files']);
        $this->assertSame(['file1.py', 'file2.py'], $result['manifest_files']);
        $this->assertIsArray($result['repositories']);
    }

    public function testGetBySubmissionIdReturnsNullOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->getBySubmissionId(123);
        
        $this->assertNull($result);
    }

    // Test insertOrUpdate()
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
        
        DB::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        DB::shouldReceive('insert')
            ->once()
            ->with(\Mockery::on(function ($data) {
                return $data['submission_id'] === 456
                    && isset($data['created_at'])
                    && isset($data['updated_at']);
            }))
            ->andReturn(true);

        $data = ['identifier' => '2025-002', 'opt_in' => true];
        $result = $this->dao->insertOrUpdate(456, $data);
        
        $this->assertTrue($result);
    }

    public function testInsertOrUpdateUpdatesExistingRecord()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->twice()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 789)
            ->twice()
            ->andReturnSelf();
        
        DB::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        DB::shouldReceive('update')
            ->once()
            ->with(\Mockery::on(function ($data) {
                return isset($data['updated_at']) && $data['identifier'] === '2025-003';
            }))
            ->andReturn(1);

        $data = ['identifier' => '2025-003'];
        $result = $this->dao->insertOrUpdate(789, $data);
        
        $this->assertTrue($result);
    }

    public function testInsertOrUpdateReturnsFalseOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->insertOrUpdate(999, []);
        
        $this->assertFalse($result);
    }

    // Test deleteBySubmissionId()
    public function testDeleteBySubmissionIdReturnsTrue()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', 123)
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('delete')
            ->once()
            ->andReturn(1);

        $result = $this->dao->deleteBySubmissionId(123);
        
        $this->assertTrue($result);
    }

    public function testDeleteBySubmissionIdReturnsFalseOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->deleteBySubmissionId(123);
        
        $this->assertFalse($result);
    }

    // Test getAllOptedIn()
    public function testGetAllOptedInReturnsArray()
    {
        $mockCollection = \Mockery::mock('Illuminate\Support\Collection');
        $mockCollection->shouldReceive('toArray')
            ->once()
            ->andReturn([
                (object)['submission_id' => 1, 'opt_in' => 1],
                (object)['submission_id' => 2, 'opt_in' => 1],
            ]);

        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('opt_in', true)
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn($mockCollection);

        $result = $this->dao->getAllOptedIn();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetAllOptedInReturnsEmptyArrayOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->getAllOptedIn();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // Test isIdentifierUnique()
    public function testIsIdentifierUniqueReturnsTrueWhenUnique()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', '2025-999')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $result = $this->dao->isIdentifierUnique('2025-999');
        
        $this->assertTrue($result);
    }

    public function testIsIdentifierUniqueReturnsFalseWhenNotUnique()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', '2025-001')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        $result = $this->dao->isIdentifierUnique('2025-001');
        
        $this->assertFalse($result);
    }

    public function testIsIdentifierUniqueExcludesSubmissionId()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', '2025-001')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('submission_id', '!=', 123)
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $result = $this->dao->isIdentifierUnique('2025-001', 123);
        
        $this->assertTrue($result);
    }

    public function testIsIdentifierUniqueReturnsFalseOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->isIdentifierUnique('2025-001');
        
        $this->assertFalse($result);
    }

    // Test generateNextIdentifier()
    public function testGenerateNextIdentifierFirstOfYear()
    {
        $currentYear = date('Y');
        
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', 'LIKE', "CODECHECK-{$currentYear}-%")
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->with('identifier', 'desc')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('value')
            ->with('identifier')
            ->once()
            ->andReturn(null);

        $result = $this->dao->generateNextIdentifier();
        
        $this->assertSame("CODECHECK-{$currentYear}-0001", $result);
    }

    public function testGenerateNextIdentifierIncrementsExisting()
    {
        $currentYear = date('Y');
        
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', 'LIKE', "CODECHECK-{$currentYear}-%")
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->with('identifier', 'desc')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('value')
            ->with('identifier')
            ->once()
            ->andReturn("CODECHECK-{$currentYear}-0042");

        $result = $this->dao->generateNextIdentifier();
        
        $this->assertSame("CODECHECK-{$currentYear}-0043", $result);
    }

    public function testGenerateNextIdentifierHandlesLargeNumbers()
    {
        $currentYear = date('Y');
        
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->with('identifier', 'LIKE', "CODECHECK-{$currentYear}-%")
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->with('identifier', 'desc')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('value')
            ->with('identifier')
            ->once()
            ->andReturn("CODECHECK-{$currentYear}-9999");

        $result = $this->dao->generateNextIdentifier();
        
        $this->assertSame("CODECHECK-{$currentYear}-10000", $result);
    }

    public function testGenerateNextIdentifierReturnsUniqueIdOnException()
    {
        DB::shouldReceive('table')
            ->with('codecheck_metadata')
            ->once()
            ->andThrow(new Exception('Database error'));

        $result = $this->dao->generateNextIdentifier();
        
        $this->assertStringStartsWith('CODECHECK-' . date('Y') . '-', $result);
    }
}