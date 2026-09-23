<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

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
        $this->assertSame(
            [['url' => 'https://github.com/test/repo', 'containsCodecheckYaml' => false, 'isWebLink' => true]],
            $result->getPublicRepositories()
        );
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
    public function testGetPublicRepositoriesReturnsEmptyListForMalformedRepositoryData(string $repository)
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 123,
            'repository' => $repository,
        ]);

        $this->assertSame([], $submission->getPublicRepositories());
    }

    public static function malformedRepositoryProvider(): array
    {
        return [
            'legacy comma-separated string' => ['https://github.com/a/b, https://github.com/c/d'],
            'plain url' => ['https://github.com/a/b'],
            'json without repositories key' => ['{"other": 0}'],
            'json repositories not a list' => ['{"repositories": "https://github.com/a/b"}'],
            'invalid json' => ['{not json'],
        ];
    }

    /**
     * Issue #154: the article page renders one link per repository, so it needs
     * the list rather than a joined string — and it needs to know which entry
     * holds the codecheck.yml.
     *
     * @param array<int, array<string, mixed>> $repositories
     * @param array<int, array<string, mixed>> $expected
     */
    #[DataProvider('publicRepositoryProvider')]
    public function testGetPublicRepositories(array $repositories, array $expected)
    {
        $submission = new CodecheckSubmission([
            'submission_id' => 123,
            'repository' => json_encode(['repositories' => $repositories]),
        ]);

        $this->assertSame($expected, $submission->getPublicRepositories());
    }

    public static function publicRepositoryProvider(): array
    {
        return [
            'every public repository is listed, the marked one flagged' => [
                [
                    ['url' => 'https://github.com/author/repo'],
                    ['url' => 'https://github.com/codecheckers/repo', 'containsCodecheckYaml' => true],
                ],
                [
                    ['url' => 'https://github.com/author/repo', 'containsCodecheckYaml' => false, 'isWebLink' => true],
                    ['url' => 'https://github.com/codecheckers/repo', 'containsCodecheckYaml' => true, 'isWebLink' => true],
                ],
            ],

            // The flag is on the entry, so removing or reordering the ones
            // around it cannot move it to a repository nobody chose — which is
            // what a stored position did (Issue #154).
            'the flag stays with its entry when earlier ones are withheld' => [
                [
                    ['url' => 'https://github.com/public/one'],
                    ['url' => 'https://github.com/private/one', 'hidden' => true],
                    ['url' => 'https://github.com/public/two', 'containsCodecheckYaml' => true],
                ],
                [
                    ['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => false, 'isWebLink' => true],
                    ['url' => 'https://github.com/public/two', 'containsCodecheckYaml' => true, 'isWebLink' => true],
                ],
            ],

            // A hidden repository is not shown, so its flag is not shown either.
            'a hidden marked repository leaves nothing marked' => [
                [
                    ['url' => 'https://github.com/public/one'],
                    ['url' => 'https://github.com/private/one', 'hidden' => true, 'containsCodecheckYaml' => true],
                ],
                [['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => false, 'isWebLink' => true]],
            ],

            'nothing marked' => [
                [['url' => 'https://github.com/public/one']],
                [['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => false, 'isWebLink' => true]],
            ],

            'private repositories are withheld from readers' => [
                [
                    ['url' => 'https://github.com/public/one', 'hidden' => false, 'containsCodecheckYaml' => true],
                    ['url' => 'https://github.com/private/one', 'hidden' => true],
                    ['url' => 'https://github.com/public/two'],
                ],
                [
                    ['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => true, 'isWebLink' => true],
                    ['url' => 'https://github.com/public/two', 'containsCodecheckYaml' => false, 'isWebLink' => true],
                ],
            ],

            // Nothing validates a repository URL on the way in, so an address
            // that cannot safely become a link is kept but flagged.
            'a non-http address is listed but not linkable' => [
                [['url' => 'javascript:alert(1)']],
                [['url' => 'javascript:alert(1)', 'containsCodecheckYaml' => false, 'isWebLink' => false]],
            ],

            // The flag is read from the entry, not re-derived by comparing
            // addresses: two entries can share a URL, and a stored stray space
            // must not cost the mark or the link.
            'a duplicate address marks the flagged entry, not the first match' => [
                [
                    ['url' => 'https://github.com/same/repo'],
                    ['url' => 'https://github.com/same/repo', 'containsCodecheckYaml' => true],
                ],
                [
                    ['url' => 'https://github.com/same/repo', 'containsCodecheckYaml' => false, 'isWebLink' => true],
                    ['url' => 'https://github.com/same/repo', 'containsCodecheckYaml' => true, 'isWebLink' => true],
                ],
            ],

            'entries without a url are skipped' => [
                [
                    ['url' => ''],
                    ['comment' => 'no url at all'],
                    ['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => true],
                ],
                [['url' => 'https://github.com/public/one', 'containsCodecheckYaml' => true, 'isWebLink' => true]],
            ],
        ];
    }
}
