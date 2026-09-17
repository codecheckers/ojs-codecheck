<?php

/**
 * @file tests/MigrationUnitTests/I154MoveCodecheckYamlFlagOntoRepositoryUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Issue #154 — the index-to-flag conversion, which decides which
 *        repository an existing install keeps its codecheck.yml mark on.
 */

namespace APP\plugins\generic\codecheck\tests\MigrationUnitTests;

use APP\plugins\generic\codecheck\classes\migration\upgrade\I154_MoveCodecheckYamlFlagOntoRepository as Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class I154MoveCodecheckYamlFlagOntoRepositoryUnitTest extends PKPTestCase
{
    public function testTheIndexedRepositoryIsTheOneFlagged()
    {
        $converted = Migration::convert(json_encode([
            'repositories' => [
                ['url' => 'https://github.com/author/repo'],
                ['url' => 'https://github.com/codecheckers/repo'],
            ],
            'repoWithCodecheckYaml' => 1,
        ]));

        $this->assertSame(
            [
                'repositories' => [
                    ['url' => 'https://github.com/author/repo', 'containsCodecheckYaml' => false],
                    ['url' => 'https://github.com/codecheckers/repo', 'containsCodecheckYaml' => true],
                ],
            ],
            json_decode($converted, true)
        );
    }

    /**
     * A null index, and one pointing past the end of the list — which is the
     * state the old code could leave behind — both flag nothing rather than
     * guessing.
     */
    #[DataProvider('nothingFlaggedProvider')]
    public function testTheseFlagNothingAndDropTheIndex(mixed $index)
    {
        $converted = Migration::convert(json_encode([
            'repositories' => [['url' => 'https://github.com/only/repo']],
            'repoWithCodecheckYaml' => $index,
        ]));

        $decoded = json_decode($converted, true);

        $this->assertArrayNotHasKey('repoWithCodecheckYaml', $decoded);
        $this->assertFalse($decoded['repositories'][0]['containsCodecheckYaml']);
    }

    public static function nothingFlaggedProvider(): array
    {
        return ['nothing was selected' => [null], 'the index is past the end' => [3]];
    }

    public function testOtherKeysSurvive()
    {
        $converted = Migration::convert(json_encode([
            'repositories' => [
                ['url' => 'https://github.com/a/b', 'hidden' => true, 'providedByAuthor' => true],
            ],
            'repoWithCodecheckYaml' => 0,
        ]));

        $entry = json_decode($converted, true)['repositories'][0];

        $this->assertTrue($entry['hidden']);
        $this->assertTrue($entry['providedByAuthor']);
        $this->assertTrue($entry['containsCodecheckYaml']);
    }

    /** Rows already converted, or with nothing to convert, are left untouched. */
    #[DataProvider('unchangedProvider')]
    public function testNothingToConvert(?string $repository)
    {
        $this->assertNull(Migration::convert($repository));
    }

    public static function unchangedProvider(): array
    {
        return [
            'null'            => [null],
            'empty'           => ['' ],
            'already migrated' => ['{"repositories":[{"url":"https://github.com/a/b","containsCodecheckYaml":true}]}'],
            'not json'        => ['{not json'],
        ];
    }
}
