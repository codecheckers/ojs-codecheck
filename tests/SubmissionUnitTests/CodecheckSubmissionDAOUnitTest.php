<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/test/repo', 'hidden' => false],
                ],
                'repoWithCodecheckYaml' => null,
            ]),
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
        $this->assertSame(['https://github.com/test/repo'], $result->getRepositories());
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

    /**
     * Regression test: this branch logs through CodecheckLogger::warning(),
     * which did not exist, so malformed stored repository data raised
     * "Call to undefined method" instead of degrading to an empty list.
     */
    #[DataProvider('malformedRepositoryProvider')]
    public function testGetRepositoriesReturnsEmptyListForMalformedRepositoryData(string $repository)
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 123,
            'repository' => $repository,
        ]);

        $this->assertSame([], $submission->getRepositories());
    }

    public static function malformedRepositoryProvider(): array
    {
        return [
            'legacy comma-separated string' => ['https://github.com/a/b, https://github.com/c/d'],
            'plain url'                     => ['https://github.com/a/b'],
            'json without repositories key' => ['{"repoWithCodecheckYaml": 0}'],
            'json repositories not a list'  => ['{"repositories": "https://github.com/a/b"}'],
            'invalid json'                  => ['{not json'],
        ];
    }

    public function testGetRepositoriesFiltersOutPrivateRepositories()
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 123,
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/public/one', 'hidden' => false],
                    ['url' => 'https://github.com/private/one', 'hidden' => true],
                    ['url' => 'https://github.com/public/two'],
                ],
                'repoWithCodecheckYaml' => 0,
            ]),
        ]);

        $this->assertSame(
            ['https://github.com/public/one', 'https://github.com/public/two'],
            $submission->getRepositories()
        );
    }
}