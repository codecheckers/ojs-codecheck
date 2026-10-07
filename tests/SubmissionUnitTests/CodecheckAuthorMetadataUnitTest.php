<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckAuthorMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;
use ReflectionMethod;

/**
 * @file APP/plugins/generic/codecheck/tests/SubmissionUnitTests/CodecheckAuthorMetadataUnitTest.php
 *
 * @class CodecheckAuthorMetadataUnitTest
 *
 * @brief Tests for merging the author's wizard entries into the CODECHECK record
 *
 * The author and the codechecker now write to one list. The merge decides what
 * survives when both have touched it, and getting it wrong loses work quietly:
 * a codechecker's hidden flag or comment silently reverting, or an entry the
 * codechecker added disappearing because the author did not list it.
 *
 * merge() is private because nothing outside the class should call it; it is
 * exercised directly here because it is the whole of the risk and the rest of
 * save() is a database write.
 */
class CodecheckAuthorMetadataUnitTest extends PKPTestCase
{
    private function merge(array $stored, array $submitted, string $key, callable $make): array
    {
        $method = new ReflectionMethod(CodecheckAuthorMetadata::class, 'merge');
        $method->setAccessible(true);

        return $method->invoke(new CodecheckAuthorMetadata(1), $stored, $submitted, $key, $make);
    }

    private function mergeRepositories(array $stored, array $submitted): array
    {
        return $this->merge(
            $stored,
            $submitted,
            'url',
            fn ($url) => ['url' => $url, 'hidden' => false, 'providedByAuthor' => true]
        );
    }

    public function testAddsSubmittedEntriesToAnEmptyRecord()
    {
        $merged = $this->mergeRepositories([], ['https://github.com/a/b']);

        $this->assertSame(
            [['url' => 'https://github.com/a/b', 'hidden' => false, 'providedByAuthor' => true]],
            $merged
        );
    }

    public function testKeepsTheAuthorsOrdering()
    {
        $merged = $this->mergeRepositories([], ['https://z.example/one', 'https://a.example/two']);

        $this->assertSame(
            ['https://z.example/one', 'https://a.example/two'],
            array_column($merged, 'url')
        );
    }

    /**
     * The codechecker hid an author's repository. Saving the wizard again must
     * not un-hide it.
     */
    public function testPreservesTheHiddenFlagSetByTheCodechecker()
    {
        $stored = [
            ['url' => 'https://github.com/a/b', 'hidden' => true, 'providedByAuthor' => true],
        ];

        $merged = $this->mergeRepositories($stored, ['https://github.com/a/b']);

        $this->assertCount(1, $merged);
        $this->assertTrue($merged[0]['hidden']);
    }

    /**
     * Entries the codechecker added are theirs; the author's list says nothing
     * about them and must not remove them.
     */
    public function testKeepsEntriesTheCodecheckerAdded()
    {
        $stored = [
            ['url' => 'https://github.com/codechecker/added', 'hidden' => false, 'providedByAuthor' => false],
        ];

        $merged = $this->mergeRepositories($stored, ['https://github.com/author/new']);

        $urls = array_column($merged, 'url');
        $this->assertContains('https://github.com/codechecker/added', $urls);
        $this->assertContains('https://github.com/author/new', $urls);
    }

    /**
     * An author who removes a repository from their submission removes it from
     * the record — but only their own.
     */
    public function testDropsAuthorEntriesTheAuthorNoLongerLists()
    {
        $stored = [
            ['url' => 'https://github.com/author/gone', 'hidden' => false, 'providedByAuthor' => true],
            ['url' => 'https://github.com/author/kept', 'hidden' => false, 'providedByAuthor' => true],
            ['url' => 'https://github.com/codechecker/added', 'hidden' => false, 'providedByAuthor' => false],
        ];

        $merged = $this->mergeRepositories($stored, ['https://github.com/author/kept']);

        $urls = array_column($merged, 'url');
        $this->assertNotContains('https://github.com/author/gone', $urls);
        $this->assertContains('https://github.com/author/kept', $urls);
        $this->assertContains('https://github.com/codechecker/added', $urls);
    }

    public function testDoesNotDuplicateAnEntrySubmittedAgain()
    {
        $stored = [
            ['url' => 'https://github.com/a/b', 'hidden' => false, 'providedByAuthor' => true],
        ];

        $merged = $this->mergeRepositories($stored, ['https://github.com/a/b']);

        $this->assertCount(1, $merged);
    }

    /**
     * A repository the codechecker added and the author then also lists becomes
     * author-provided, so it gains the protection from deletion.
     */
    public function testMarksAPreviouslyCodecheckerEntryAsAuthorProvidedWhenSubmitted()
    {
        $stored = [
            ['url' => 'https://github.com/a/b', 'hidden' => false, 'providedByAuthor' => false],
        ];

        $merged = $this->mergeRepositories($stored, ['https://github.com/a/b']);

        $this->assertCount(1, $merged);
        $this->assertTrue($merged[0]['providedByAuthor']);
    }

    public function testMergesManifestEntriesOnTheFileName()
    {
        $stored = [
            ['file' => 'figure2.png', 'comment' => 'Figure 2 of the manuscript', 'hidden' => false, 'providedByAuthor' => true],
        ];

        $merged = $this->merge(
            $stored,
            ['figure2.png', 'table1.csv'],
            'file',
            fn ($file) => ['file' => $file, 'comment' => '', 'hidden' => false, 'providedByAuthor' => true]
        );

        $this->assertCount(2, $merged);
        // The codechecker's comment survives a re-save.
        $this->assertSame('Figure 2 of the manuscript', $merged[0]['comment']);
        $this->assertSame('table1.csv', $merged[1]['file']);
    }

    public function testIgnoresStoredEntriesWithoutTheIdentifyingField()
    {
        $stored = [['comment' => 'malformed, no url'], null, 'not an array'];

        $merged = $this->mergeRepositories($stored, ['https://github.com/a/b']);

        $this->assertSame(['https://github.com/a/b'], array_column($merged, 'url'));
    }

    /**
     * The wizard's expected-output field writes `file - comment`, and the whole
     * line was stored as the file name.
     */
    public function testSplitsAManifestLineIntoFileAndComment()
    {
        $this->assertSame(
            ['file' => 'outputs/figure1.png', 'comment' => 'Figure 1'],
            CodecheckAuthorMetadata::parseManifestLine('outputs/figure1.png - Figure 1')
        );
    }

    public function testReadsAManifestLineWithoutACommentAsTheFileAlone()
    {
        $this->assertSame(
            ['file' => 'table1.csv', 'comment' => ''],
            CodecheckAuthorMetadata::parseManifestLine('  table1.csv  ')
        );
    }

    /** Only the first separator divides them; the comment may contain one. */
    public function testKeepsAFurtherSeparatorInTheComment()
    {
        $this->assertSame(
            ['file' => 'fig.png', 'comment' => 'left - right panels'],
            CodecheckAuthorMetadata::parseManifestLine('fig.png - left - right panels')
        );
    }

    private function withAuthorComments(array $lines, array $manifest): array
    {
        $metadata = new CodecheckAuthorMetadata(1);
        $metadata->setManifest($lines);

        $method = new ReflectionMethod(CodecheckAuthorMetadata::class, 'withAuthorComments');
        $method->setAccessible(true);

        return $method->invoke($metadata, $manifest);
    }

    public function testPutsTheAuthorsCommentOnTheirEntry()
    {
        $manifest = $this->withAuthorComments(
            ['figure1.png - Figure 1'],
            [['file' => 'figure1.png', 'comment' => '', 'hidden' => false, 'providedByAuthor' => true]]
        );

        $this->assertSame('Figure 1', $manifest[0]['comment']);
    }

    /** An author who left the comment blank does not wipe the codechecker's. */
    public function testKeepsTheStoredCommentWhenTheAuthorGaveNone()
    {
        $manifest = $this->withAuthorComments(
            ['figure2.png'],
            [['file' => 'figure2.png', 'comment' => 'Figure 2 of the manuscript', 'providedByAuthor' => true]]
        );

        $this->assertSame('Figure 2 of the manuscript', $manifest[0]['comment']);
    }

    public function testLeavesTheCodecheckersEntriesAlone()
    {
        $manifest = $this->withAuthorComments(
            ['figure1.png - Figure 1'],
            [['file' => 'figure1.png', 'comment' => 'the codechecker\'s', 'providedByAuthor' => false]]
        );

        $this->assertSame('the codechecker\'s', $manifest[0]['comment']);
    }

    /** What an existing check gives the wizard (#190): its repositories and outputs only. */
    public function testTakesRepositoriesAndManifestFromACodecheckYaml()
    {
        $entries = CodecheckAuthorMetadata::entriesFromCodecheckYaml([
            'paper' => ['title' => 'A Paper'],
            'repository' => ['https://github.com/a/b', ' https://osf.io/abcde ', 'https://github.com/a/b'],
            'manifest' => [
                ['file' => 'fig1.png', 'comment' => 'Figure 1'],
                ['file' => 'table.csv'],
            ],
            'certificate' => '2026-001',
        ]);

        $this->assertSame([
            'repositories' => ['https://github.com/a/b', 'https://osf.io/abcde'],
            'manifest' => [
                ['file' => 'fig1.png', 'comment' => 'Figure 1'],
                ['file' => 'table.csv', 'comment' => ''],
            ],
        ], $entries);
    }

    /** The wizard's field holds one `file - comment` line per entry. */
    public function testKeepsEachOutputToOneLineOfTheWizardsField()
    {
        $entries = CodecheckAuthorMetadata::entriesFromCodecheckYaml(['manifest' => [
            ['file' => 'fig.png', 'comment' => "Figure 1,\nleft panel"],
            ['file' => 'Figure 1 - main.png'],
            ['file' => "two\nlines.png"],
        ]]);

        $this->assertSame([['file' => 'fig.png', 'comment' => 'Figure 1, left panel']], $entries['manifest']);
    }

    public function testReadsASingleRepositoryGivenAsAString()
    {
        $entries = CodecheckAuthorMetadata::entriesFromCodecheckYaml(['repository' => 'https://github.com/a/b']);

        $this->assertSame(['https://github.com/a/b'], $entries['repositories']);
        $this->assertSame([], $entries['manifest']);
    }

    /** The wizard would refuse what is not a web address, so it is not offered. */
    public function testLeavesOutWhatTheWizardWouldRefuse()
    {
        $entries = CodecheckAuthorMetadata::entriesFromCodecheckYaml([
            'repository' => ['javascript://x', 'ftp://example.org', 42, ['nested']],
            'manifest' => ['just a string', ['comment' => 'no file'], ['file' => '  ']],
        ]);

        $this->assertSame(['repositories' => [], 'manifest' => []], $entries);
    }

    public static function existingCheckAddressProvider(): array
    {
        return [
            'no pointer' => ['', true],
            'null' => [null, true],
            'a web address' => ['https://zenodo.org/records/14900193', true],
            'a DOI link' => ['https://doi.org/10.5281/zenodo.14900193', true],
            'a bare DOI' => ['10.5281/zenodo.14900193', true],
            'a prefixed DOI' => ['doi:10.5281/zenodo.14900193', true],
            'another scheme' => ['javascript:alert(1)', false],
            'a word' => ['zenodo', false],
            'a DOI with spaces' => ['10.5281/zenodo 1', false],
        ];
    }

    #[DataProvider('existingCheckAddressProvider')]
    public function testJudgesAPointerToAnExistingCheck(?string $value, bool $expected)
    {
        $this->assertSame($expected, CodecheckAuthorMetadata::isExistingCheckAddress($value));
    }
}
