<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\core\Request;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\tests\Support\GithubFake;
use APP\plugins\generic\codecheck\tests\Support\Network;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @file APP/plugins/generic/codecheck/tests/WorkflowUnitTests/CodecheckMetadataHandlerUnitTest.php
 *
 * @class CodecheckMetadataHandlerUnitTest
 *
 * @brief Tests for the CodecheckMetadataHandler class. The repositories the
 *   import reads answer by address — GitHub through GithubFake, every other
 *   host through Laravel's `Factory::fake()` — so a test says what is asked
 *   for and what comes back, not which methods are called (#191).
 */
class CodecheckMetadataHandlerUnitTest extends PKPTestCase
{
    private CodecheckMetadataHandler $handler;
    private $mockRequest;
    private CurlApiClient $curlApiClient;
    private Factory $http;
    private GithubFake $github;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRequest = $this->createMock(\APP\core\Request::class);
        $this->mockRequest->method('getUserVar')
            ->with('submissionId')
            ->willReturn(1);

        GithubHttp::reset();
        $this->http = Network::factory();
        $this->github = new GithubFake();
        $this->curlApiClient = new CurlApiClient($this->http);

        $this->handler = new CodecheckMetadataHandler($this->mockRequest, $this->github->client(), $this->curlApiClient);
    }

    protected function tearDown(): void
    {
        GithubHttp::reset();
        parent::tearDown();
    }

    /** A handler on the fakes the test has set up. */
    private function handlerOnTheFakes(): CodecheckMetadataHandler
    {
        return new CodecheckMetadataHandler(new Request(), $this->github->client(), $this->curlApiClient);
    }

    /** @return list<string> the addresses asked of non-GitHub hosts, in order */
    private function fetched(): array
    {
        return array_map(fn (array $exchange) => $exchange[0]->url(), $this->http->recorded()->all());
    }

    /**
     * The generated codecheck.yml is validated at publication and deposited in
     * the public register, so a value must come back out of it as the string it
     * went in as.
     */
    #[DataProvider('scalarsThatUsedToChangeTypeProvider')]
    public function testNormalisingTheYamlKeepsScalarsAsStrings(string $value, string $why)
    {
        $dumped = Yaml::dump(['title' => $value], 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $normalise = new \ReflectionMethod(CodecheckMetadataHandler::class, 'normalizeYamlOutput');
        $normalise->setAccessible(true);
        $yaml = $normalise->invoke($this->handler, $dumped);

        $this->assertSame($value, Yaml::parse($yaml)['title'], $why);
    }

    public static function scalarsThatUsedToChangeTypeProvider(): array
    {
        return [
            ['[a, b]', 'an unquoted [a, b] parses as a sequence, not a title'],
            ['*x', 'an unquoted *x is a YAML alias and does not parse at all'],
            ['1.0', 'an unquoted 1.0 is a float, and config versions look exactly like this'],
            ["it's fine", 'a doubled quote must survive'],
            ['yes', 'an unquoted yes is a boolean in YAML 1.1'],
            ['Ordinary title', 'the common case must be unaffected'],
        ];
    }

    public function testAddressesAreStillUnquotedForReadability()
    {
        $dumped = Yaml::dump(['repository' => 'https://github.com/codecheckers/repo'], 10, 2);

        $normalise = new \ReflectionMethod(CodecheckMetadataHandler::class, 'normalizeYamlOutput');
        $normalise->setAccessible(true);
        $yaml = $normalise->invoke($this->handler, $dumped);

        // Cosmetic, and safe: an http(s) address round-trips either way.
        $this->assertStringContainsString('https://github.com/codecheckers/repo', $yaml);
        $this->assertStringNotContainsString("'https://", $yaml);
        $this->assertSame('https://github.com/codecheckers/repo', Yaml::parse($yaml)['repository']);
    }

    public function testConstructorSetsSubmissionId()
    {
        $mockRequest = $this->createMock(\APP\core\Request::class);
        $mockRequest->method('getUserVar')
            ->with('submissionId')
            ->willReturn(123);

        $handler = new CodecheckMetadataHandler($mockRequest);

        $this->assertInstanceOf(CodecheckMetadataHandler::class, $handler);
        $this->assertSame(123, $handler->getSubmissionId());
    }

    public function testGetSubmissionIdReturnsCorrectValue()
    {
        $this->assertSame(1, $this->handler->getSubmissionId());
    }

    public function testGetAuthorsReturnsEmptyArrayForNullPublication()
    {
        $result = $this->handler->getAuthors(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** An author as the publication hands it over. */
    private function author(int $id, string $given, string $family, string $email, ?string $orcid = null): \APP\author\Author
    {
        $author = $this->createMock(\APP\author\Author::class);
        $author->method('getId')->willReturn($id);
        $author->method('getDefaultLocale')->willReturn('en');
        $author->method('getGivenName')->willReturn($given);
        $author->method('getFamilyName')->willReturn($family);
        $author->method('getEmail')->willReturn($email);
        $author->method('getOrcid')->willReturn($orcid);

        return $author;
    }

    /** A publication with two authors, the second of them its primary contact. */
    private function publicationWithAuthors(?int $primaryContactId = 2): \APP\publication\Publication
    {
        $authors = [
            $this->author(1, 'Stephen', 'Eglen', 'eglen@example.org'),
            $this->author(2, 'Daniel', 'Nüst', 'nuest@example.org', 'https://orcid.org/0000-0002-1825-0097'),
        ];
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getStoredPubId')->willReturn(null);
        $publication->method('getData')->willReturnCallback(fn (string $key) => match ($key) {
            'authors' => $authors,
            default => null,
        });
        // OJS's own lookup, which compares the author's id with `primaryContactId`.
        $publication->method('getPrimaryAuthor')->willReturn(
            collect($authors)->first(fn ($author) => $author->getId() === $primaryContactId)
        );

        return $publication;
    }

    /** The codechecker asks the author the submission names as its contact (#28). */
    public function testTheContactIsThePrimaryContact()
    {
        $this->assertSame(
            ['name' => 'Daniel Nüst', 'email' => 'nuest@example.org'],
            $this->handler->getContact($this->publicationWithAuthors(2))
        );
    }

    /** Nobody chosen means nobody named — not the first author. */
    public function testThereIsNoContactWithoutAPrimaryContact()
    {
        $this->assertNull($this->handler->getContact($this->publicationWithAuthors(null)));
        $this->assertNull($this->handler->getContact(null));
    }

    public function testAPrimaryContactWhoIsNoLongerAnAuthorIsNoContact()
    {
        $this->assertNull($this->handler->getContact($this->publicationWithAuthors(99)));
    }

    /** The email is for the form alone; the `codecheck.yml` is published. */
    public function testTheYamlCarriesNoEmail()
    {
        $yaml = $this->handler->buildYaml($this->publicationWithAuthors(2), $this->buildYamlMetadata('latest'));

        $this->assertStringContainsString('Daniel Nüst', $yaml);
        $this->assertStringNotContainsString('@example.org', $yaml);
        $this->assertStringNotContainsStringIgnoringCase('email', $yaml);
    }

    /** A viewer who may not know the authors gets a file that names none (#28). */
    public function testTheYamlLeavesTheAuthorsOutWhenTheyAreWithheld()
    {
        $yaml = $this->handler->buildYaml($this->publicationWithAuthors(2), $this->buildYamlMetadata('latest'), false);
        $paper = \Symfony\Component\Yaml\Yaml::parse($yaml)['paper'];

        $this->assertSame('Test Paper', $paper['title']);
        $this->assertArrayNotHasKey('authors', $paper);
        $this->assertStringNotContainsString('Nüst', $yaml);
        $this->assertStringNotContainsString('0000-0002-1825-0097', $yaml);
    }

    public function testGetSubmissionId()
    {
        $request = new Request();
        // create a test content for the user variable 'submissionId'
        $expectedSubmissionId = 123;
        $_POST['submissionId'] = $expectedSubmissionId;

        $this->handler = new CodecheckMetadataHandler($request, $this->github->client(), $this->curlApiClient);

        $actualSubmissionId = $this->handler->getSubmissionId();
        $this->assertEquals($expectedSubmissionId, $actualSubmissionId);
        $this->assertIsInt($actualSubmissionId);
    }

    /**
     * GitHub answering `$answer` for the contents of any file: a response, an
     * array (as JSON) or an exception for the transport failing.
     */
    private function githubAnswering(mixed $answer): void
    {
        $this->github->route('GET /repos/*/contents/*', $answer);
    }

    private static function githubFile(string $yaml): array
    {
        return ['type' => 'file', 'content' => base64_encode($yaml)];
    }

    public static function githubAddressProvider(): array
    {
        return [
            'repository root, any owner' => ['https://github.com/reproducible-agile/AGILECA', 'reproducible-agile', 'AGILECA', 'codecheck.yml', null],
            'repository root with a trailing slash' => ['https://github.com/codecheckers/testing-dev-register/', 'codecheckers', 'testing-dev-register', 'codecheck.yml', null],
            'clone address' => ['https://github.com/codecheckers/Piccolo-2020.git', 'codecheckers', 'Piccolo-2020', 'codecheck.yml', null],
            'folder' => ['https://github.com/reproducible-agile/reviews-2025/tree/main/reports/08', 'reproducible-agile', 'reviews-2025', 'reports/08/codecheck.yml', 'main'],
            'file' => ['https://github.com/codecheckers/lifecycle-journal-codechecks/blob/main/7/codecheck.yml', 'codecheckers', 'lifecycle-journal-codechecks', '7/codecheck.yml', 'main'],
            'file with a query' => ['https://github.com/codecheckers/lifecycle-journal-codechecks/blob/main/7/codecheck.yml?plain=1', 'codecheckers', 'lifecycle-journal-codechecks', '7/codecheck.yml', 'main'],
            'raw file' => ['https://raw.githubusercontent.com/codecheckers/lifecycle-journal-codechecks/refs/heads/main/7/codecheck.yml', 'codecheckers', 'lifecycle-journal-codechecks', '7/codecheck.yml', 'refs/heads/main'],
            'raw file on a short ref' => ['https://raw.githubusercontent.com/codecheckers/lifecycle-journal-codechecks/main/7/codecheck.yml', 'codecheckers', 'lifecycle-journal-codechecks', '7/codecheck.yml', 'main'],
        ];
    }

    #[DataProvider('githubAddressProvider')]
    public function testImportMetadataFromGithubReadsTheFileTheAddressNames(string $address, string $owner, string $repo, string $path, ?string $ref)
    {
        $this->githubAnswering(self::githubFile('test: yaml'));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository($address);

        // The one file the address names, and nothing else: not the repository's default branch.
        $requests = $this->github->requests();
        $this->assertCount(1, $requests);
        $this->assertSame("GET /repos/{$owner}/{$repo}/contents/{$path}", $requests[0]->getMethod() . ' ' . urldecode($requests[0]->getUri()->getPath()));
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame($ref, $query['ref'] ?? null);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $address, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    public function testImportMetadataFromGithubContentsShowException()
    {
        $this->githubAnswering(new Response(404, [], '{"message": "Not Found"}'));
        $this->handler = $this->handlerOnTheFakes();

        $repositoryUrl = 'https://github.com/codecheckers/testing-dev-register/';
        $response = $this->handler->importMetadataFromRepository($repositoryUrl);
        $payload = $response->getPayloadArray();
        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertCount(3, $payload);
        $this->assertFalse($payload['success']);
        $this->assertEquals($repositoryUrl, $payload['repository']);
    }

    public function testImportMetadataFromGithubDoesNotCallAFailedRequestAMissingFile()
    {
        $this->githubAnswering(new Response(403, ['Content-Type' => 'application/json'], '{"message": "API rate limit exceeded"}'));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(403, $response->getHttpResponseCode());
        $this->assertSame('API rate limit exceeded', $response->getPayloadArray()['error']);
    }

    public function testImportMetadataFromGithubSaysWhenGithubDidNotAnswer()
    {
        $this->githubAnswering(new ConnectException('cURL error 28: Operation timed out', new PsrRequest('GET', 'https://api.github.com/')));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(504, $response->getHttpResponseCode());
        $this->assertSame('plugins.generic.codecheck.repositories.githubUnreachable', $response->getPayloadArray()['error']);
    }

    public static function notAMappingProvider(): array
    {
        return [
            'empty' => [''],
            'a bare value' => ['just a sentence'],
            'a list' => ["- a\n- b"],
            'not JSON' => ['summary: .inf'],
        ];
    }

    #[DataProvider('notAMappingProvider')]
    public function testImportMetadataRefusesAFileThatHoldsNoMetadata(string $yaml)
    {
        $this->githubAnswering(self::githubFile($yaml));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubRefusesAFolderNamedLikeTheFile()
    {
        $this->githubAnswering([self::githubFile('test: yaml')]);
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubAnswersAFileThatIsNotYaml()
    {
        $this->githubAnswering(self::githubFile("a: b\n  c: : d"));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubRefusesAnAddressThatIsNotARepository()
    {
        $this->githubAnswering(self::githubFile('test: yaml'));
        $this->handler = $this->handlerOnTheFakes();

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/register/issues/5');

        $this->assertEquals(400, $response->getHttpResponseCode());
        $this->assertSame([], $this->github->addresses(), 'nothing is asked of GitHub');
    }

    #[DataProvider('titleComparisonProvider')]
    public function testTitlesMatch(mixed $first, mixed $second, bool $expected)
    {
        $this->assertSame($expected, CodecheckMetadataHandler::titlesMatch($first, $second));
    }

    /** Spacing and capitals do not make another paper (#28). */
    public static function titleComparisonProvider(): array
    {
        return [
            'identical' => ['A Paper', 'A Paper', true],
            'capitals' => ['a paper ON data', 'A Paper on Data', true],
            'capitals beyond ASCII' => ['Über Ökologie', 'über ökologie', true],
            'runs of spaces' => ['A   Paper', 'A Paper', true],
            'edges' => ["  A Paper \n", 'A Paper', true],
            'a line break inside a folded YAML scalar' => ["A\nPaper", 'A Paper', true],
            'a non-breaking space' => ["A\u{00A0}Paper", 'A Paper', true],
            'another paper' => ['A Paper', 'Another Paper', false],
            'a word missing' => ['A Paper on Data', 'A Paper', false],
            'punctuation still counts' => ['A Paper.', 'A Paper', false],
            'two empty titles are no title' => ['', '', false],
            'only spaces' => ['   ', '   ', false],
            'no title in the file' => [null, 'A Paper', false],
            'a title that is not a string' => [['A Paper'], 'A Paper', false],
        ];
    }

    /** The address a Zenodo record's `codecheck.yml` is downloaded from. */
    private const ZENODO_RECORD_YAML = 'https://zenodo.org/records/14900193/files/codecheck.yml?download=1';

    /** A handler whose Zenodo record holds the given `codecheck.yml`. */
    private function handlerServing(string $yaml): CodecheckMetadataHandler
    {
        $this->http->fake([self::ZENODO_RECORD_YAML => Factory::response($yaml)]);

        return $this->handlerOnTheFakes();
    }

    public function testTheImportForASubmissionAcceptsATitleThatDiffersInCaseAndSpacing()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: 'the  PAPER on data'\nsummary: ok\n");

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertSame('ok', $response->getPayloadArray()['metadata']['summary']);
    }

    public function testTheImportForASubmissionRefusesAnotherPaper()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: Some other paper\nsummary: ok\n");

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');
        $payload = $response->getPayloadArray();

        $this->assertSame(422, $response->getHttpResponseCode());
        $this->assertFalse($payload['success']);
        $this->assertSame($repository, $payload['repository']);
        $this->assertArrayNotHasKey('metadata', $payload, 'nothing of the file is handed back');
        $this->assertTrue($payload['titleMismatch']);
        $this->assertSame('Some other paper', $payload['paperTitle'], 'so the editor can be asked');
    }

    /** An editor who confirmed both titles may load a checked preprint (#190). */
    public function testTheImportForASubmissionTakesAnotherTitleOnceAccepted()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: The preprint\nsummary: ok\n");

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data', true);

        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertSame('ok', $response->getPayloadArray()['metadata']['summary']);
    }

    public function testTheImportForASubmissionRefusesAFileWithNoPaperTitle()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("summary: ok\n");

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertSame(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    /** A repository that cannot be read is answered as it was, not as a title problem. */
    public function testTheImportForASubmissionLeavesAFailedFetchAlone()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $this->http->fake([self::ZENODO_RECORD_YAML => Factory::response('unreadable', 500)]);
        $handler = $this->handlerOnTheFakes();

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertNotSame(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    /**
     * The wizard's load from an existing check (#190) reports a different
     * title rather than refusing it, and answers only the wizard's entries.
     */
    public function testThePreviewForAnAuthorReportsAnotherTitleWithoutRefusing()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing(
            "paper:\n  title: The preprint's title\n  authors:\n    - name: Ann\nrepository: https://github.com/a/b\nmanifest:\n  - file: fig.png\n    comment: Figure\nsummary: ok\n"
        );

        $response = $handler->previewForAuthor($repository, 'The Paper on Data');
        $payload = $response->getPayloadArray();

        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['titleMatches']);
        $this->assertSame(['https://github.com/a/b'], $payload['repositories']);
        $this->assertSame([['file' => 'fig.png', 'comment' => 'Figure']], $payload['manifest']);
        $this->assertSame(
            ['success', 'repository', 'titleMatches', 'repositories', 'manifest'],
            array_keys($payload),
            'nothing else of the file is handed to the author'
        );
    }

    public function testThePreviewForAnAuthorConfirmsTheSamePaper()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: the paper on data\n");

        $payload = $handler->previewForAuthor($repository, 'The Paper on Data')->getPayloadArray();

        $this->assertTrue($payload['titleMatches']);
        $this->assertSame([], $payload['repositories']);
    }

    public function testThePreviewForAnAuthorPassesAFailedFetchOn()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $this->http->fake([self::ZENODO_RECORD_YAML => Factory::response('unreadable', 500)]);
        $handler = $this->handlerOnTheFakes();

        $this->assertFalse($handler->previewForAuthor($repository, 'The Paper on Data')->getPayloadArray()['success']);
    }

    public static function zenodoAddressProvider(): array
    {
        return [
            'record' => ['https://zenodo.org/records/14900193', 'https://zenodo.org/records/14900193/files/codecheck.yml?download=1'],
            'older record, shorter id' => ['https://zenodo.org/record/3674056/', 'https://zenodo.org/records/3674056/files/codecheck.yml?download=1'],
            'file' => ['https://zenodo.org/records/14900193/files/paper.yaml?download=1', 'https://zenodo.org/records/14900193/files/paper.yaml?download=1'],
        ];
    }

    #[DataProvider('zenodoAddressProvider')]
    public function testImportMetadataFromZenodo(string $repository, string $download)
    {
        $this->handler = $this->handlerAnswering([$download => 'test: yaml']);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame([$download], $this->fetched());
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $repository, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    /**
     * A handler on a network that answers these addresses with these bodies.
     * Which are asked, and in which order, is read back with `fetched()`.
     *
     * @param array<string, string> $answers address → body
     */
    private function handlerAnswering(array $answers): CodecheckMetadataHandler
    {
        $this->http->fake(array_map(fn (string $body) => Factory::response($body), $answers));

        return $this->handlerOnTheFakes();
    }

    private static function osfFile(string $name, string $download = 'https://osf.io/download/5zu8b/', string $kind = 'file'): array
    {
        return ['attributes' => ['name' => $name, 'kind' => $kind], 'links' => ['download' => $download]];
    }

    public function testImportMetadataFromOsf()
    {
        $repository = 'https://osf.io/ymc3t/';
        $listing = json_encode(['data' => [
            self::osfFile('codecheck.yml', 'https://osf.io/download/5zu8b/', 'folder'),
            self::osfFile('codecheck.yml.bak', 'https://osf.io/download/4co4h/'),
            self::osfFile('codecheck.yml', 'https://files.de-1.osf.io/v1/resources/ymc3t/providers/osfstorage/66f8'),
        ]]);
        $this->handler = $this->handlerAnswering([
            'https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml' => $listing,
            'https://files.de-1.osf.io/v1/resources/ymc3t/providers/osfstorage/66f8' => 'test: yaml',
        ]);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame([
            'https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml',
            'https://files.de-1.osf.io/v1/resources/ymc3t/providers/osfstorage/66f8',
        ], $this->fetched());
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $repository, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    public static function osfFileAddressProvider(): array
    {
        return [
            'file page' => ['https://osf.io/ymc3t/files/osfstorage/66f86b012218196732a8604f', '66f86b012218196732a8604f'],
            'file by its short identifier' => ['https://osf.io/ymc3t/files/5zu8b', '5zu8b'],
        ];
    }

    #[DataProvider('osfFileAddressProvider')]
    public function testImportMetadataFromOsfReadsTheFileTheAddressNames(string $repository, string $file)
    {
        $record = json_encode(['data' => self::osfFile('paper.yml')]);
        $this->handler = $this->handlerAnswering([
            "https://api.osf.io/v2/files/{$file}/" => $record,
            'https://osf.io/download/5zu8b/' => 'test: yaml',
        ]);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame(["https://api.osf.io/v2/files/{$file}/", 'https://osf.io/download/5zu8b/'], $this->fetched());
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(['test' => 'yaml'], $response->getPayloadArray()['metadata']);
    }

    public function testImportMetadataFromOsfRefusesAFolderTheAddressNames()
    {
        $record = json_encode(['data' => self::osfFile('reports', kind: 'folder')]);
        $this->handler = $this->handlerAnswering(['https://api.osf.io/v2/files/66f8/' => $record]);

        $response = $this->handler->importMetadataFromRepository('https://osf.io/ymc3t/files/osfstorage/66f8');

        // The folder is not downloaded.
        $this->assertSame(['https://api.osf.io/v2/files/66f8/'], $this->fetched());
        $this->assertEquals(404, $response->getHttpResponseCode());
    }

    public function testImportMetadataFromOsfNoDataFromOsfFilestorage()
    {
        $repository = 'https://osf.io/ymc3t/';
        $this->handler = $this->handlerAnswering(['https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml' => json_encode(['data' => null])]);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => false, 'error' => 'Invalid OSF API response', 'repository' => $repository],
            $response->getPayloadArray()
        );
    }

    public function testImportMetadataFromOsfWithoutTheFile()
    {
        $repository = 'https://osf.io/ymc3t/';
        $listing = json_encode(['data' => []]);
        $this->handler = $this->handlerAnswering(['https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml' => $listing]);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => false, 'error' => 'codecheck.yml not found', 'repository' => $repository],
            $response->getPayloadArray()
        );
    }

    public function testImportMetadataFromOsfFetchRefused()
    {
        $repository = 'https://osf.io/ymc3t/';
        $this->http->fake(['https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml' => Factory::response('', 500)]);

        $response = $this->handlerOnTheFakes()->importMetadataFromRepository($repository);
        $payload = $response->getPayloadArray();

        // The host's own status is kept, and the error says which address refused.
        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertCount(3, $payload);
        $this->assertFalse($payload['success']);
        $this->assertEquals($repository, $payload['repository']);
        $this->assertStringContainsString('HTTP status 500', $payload['error']);
    }

    public function testImportMetadataFromOsfHostDidNotAnswer()
    {
        $repository = 'https://osf.io/ymc3t/';
        $this->http->fake(['https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml' => $this->http->failedConnection('timed out')]);

        $response = $this->handlerOnTheFakes()->importMetadataFromRepository($repository);
        $payload = $response->getPayloadArray();

        $this->assertEquals(504, $response->getHttpResponseCode());
        $this->assertCount(3, $payload);
        $this->assertFalse($payload['success']);
        $this->assertEquals($repository, $payload['repository']);
        $this->assertStringContainsString('did not answer', $payload['error']);
    }

    public static function gitlabAddressProvider(): array
    {
        return [
            'project' => ['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT', 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT/-/raw/main/codecheck.yml?inline=false'],
            'project of another group' => ['https://gitlab.com/codecheckers/Piccolo-2020', 'https://gitlab.com/codecheckers/Piccolo-2020/-/raw/main/codecheck.yml?inline=false'],
            'file on a branch' => ['https://gitlab.com/codecheckers/Piccolo-2020/-/blob/dev/7/codecheck.yml', 'https://gitlab.com/codecheckers/Piccolo-2020/-/raw/dev/7/codecheck.yml?inline=false'],
        ];
    }

    #[DataProvider('gitlabAddressProvider')]
    public function testImportMetadataFromGitlab(string $repository, string $download)
    {
        $this->handler = $this->handlerAnswering([$download => 'test: yaml']);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame([$download], $this->fetched());
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $repository, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    public function testReadYamlContentFetchRefused()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $this->http->fake(['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT/-/raw/main/codecheck.yml?inline=false' => Factory::response('', 500)]);

        $response = $this->handlerOnTheFakes()->importMetadataFromRepository($repository);
        $payload = $response->getPayloadArray();

        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertCount(3, $payload);
        $this->assertFalse($payload['success']);
        $this->assertEquals($repository, $payload['repository']);
        $this->assertStringContainsString('HTTP status 500', $payload['error']);
    }

    public function testReadYamlContentHostDidNotAnswer()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $this->http->fake(['https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT/-/raw/main/codecheck.yml?inline=false' => $this->http->failedConnection('timed out')]);

        $response = $this->handlerOnTheFakes()->importMetadataFromRepository($repository);
        $payload = $response->getPayloadArray();

        // A host that did not answer is a 504, not the 500 a failure of ours would be (#130).
        $this->assertEquals(504, $response->getHttpResponseCode());
        $this->assertCount(3, $payload);
        $this->assertFalse($payload['success']);
        $this->assertEquals($repository, $payload['repository']);
        $this->assertStringContainsString('did not answer', $payload['error']);
    }

    public function testBuildYamlDeclaresTheRecordedConfigVersion()
    {
        // The version is a real choice in the metadata form, so the file has to
        // declare the specification the codechecker filled it in against rather
        // than a fixed one.
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        $yaml = $this->handler->buildYaml($publication, $this->buildYamlMetadata('2.0'));
        $this->assertStringContainsString('https://codecheck.org.uk/spec/config/2.0/', $yaml);

        // An empty version, or one the plugin no longer knows, falls back to
        // the default rather than emitting a broken URL or declaring a
        // specification the file was not built for.
        foreach (['', 'latest', '1.0'] as $version) {
            $yaml = $this->handler->buildYaml($publication, $this->buildYamlMetadata($version));
            $this->assertStringContainsString('https://codecheck.org.uk/spec/config/2.0/', $yaml, $version);
        }
    }

    /** A minimal codecheck_metadata row carrying the given config version. */
    private function buildYamlMetadata(string $version): object
    {
        return (object) [
            'spec_version' => $version,
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => '{"repositories":null}',
            'codecheckers' => '[]',
            'source' => null,
            'summary' => null,
            'check_time' => null,
            'certificate' => null,
            'report' => null,
            'additional_content' => null,
        ];
    }

    public function testBuildYamlExcludesPrivateRepositories()
    {
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        $metadata = (object) [
            'spec_version' => '2.0',
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/public/repo', 'hidden' => false],
                    ['url' => 'https://github.com/private/repo', 'hidden' => true],
                ],
            ]),
            'codecheckers' => '[]',
            'source' => null,
            'summary' => null,
            'check_time' => null,
            'certificate' => null,
            'report' => null,
            'additional_content' => null,
        ];

        $yaml = $this->handler->buildYaml($publication, $metadata);

        $this->assertStringContainsString('github.com/public/repo', $yaml);
        $this->assertStringNotContainsString('github.com/private/repo', $yaml);
    }

    public function testBuildYamlExcludesRepositoryKeyWhenAllPrivate()
    {
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        $metadata = (object) [
            'spec_version' => '2.0',
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/private/repo-one', 'hidden' => true],
                    ['url' => 'https://github.com/private/repo-two', 'hidden' => true],
                ],
            ]),
            'codecheckers' => '[]',
            'source' => null,
            'summary' => null,
            'check_time' => null,
            'certificate' => null,
            'report' => null,
            'additional_content' => null,
        ];

        $yaml = $this->handler->buildYaml($publication, $metadata);

        $this->assertStringNotContainsString('repository', $yaml);
    }

    /**
     * Issue #154: several repositories are a YAML sequence, which the
     * specification allows ("A URL or a list of URLs"). They used to be joined
     * with ", " into a single scalar that no consumer could resolve — the same
     * defect the article page had.
     */
    public function testBuildYamlWritesSeveralRepositoriesAsAList()
    {
        $yaml = $this->handler->buildYaml(
            $this->buildYamlPublication(),
            $this->buildYamlMetadataWithRepositories([
                ['url' => 'https://github.com/public/repo-one', 'hidden' => false],
                ['url' => 'https://github.com/public/repo-two', 'hidden' => false],
            ])
        );

        $parsed = Yaml::parse($yaml);
        $this->assertSame(
            ['https://github.com/public/repo-one', 'https://github.com/public/repo-two'],
            $parsed['repository']
        );
    }

    /** A single repository stays a plain scalar, as it always was. */
    public function testBuildYamlWritesASingleRepositoryAsAString()
    {
        $yaml = $this->handler->buildYaml(
            $this->buildYamlPublication(),
            $this->buildYamlMetadataWithRepositories([
                ['url' => 'https://github.com/public/repo-one', 'hidden' => false],
            ])
        );

        $parsed = Yaml::parse($yaml);
        $this->assertSame('https://github.com/public/repo-one', $parsed['repository']);
    }

    /**
     * A private repository between two public ones: the remaining two are still
     * written as a sequence, and the private one appears nowhere in the file.
     */
    public function testBuildYamlWritesASequenceWhenAPrivateRepositorySitsBetweenPublicOnes()
    {
        $yaml = $this->handler->buildYaml(
            $this->buildYamlPublication(),
            $this->buildYamlMetadataWithRepositories([
                ['url' => 'https://github.com/public/repo-one', 'hidden' => false],
                ['url' => 'https://github.com/private/repo', 'hidden' => true],
                ['url' => 'https://github.com/public/repo-two', 'hidden' => false],
            ])
        );

        $parsed = Yaml::parse($yaml);
        $this->assertSame(
            ['https://github.com/public/repo-one', 'https://github.com/public/repo-two'],
            $parsed['repository']
        );
        $this->assertStringNotContainsString('private/repo', $yaml);
    }

    private function buildYamlPublication(): \APP\publication\Publication
    {
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        return $publication;
    }

    /** @param array<int, array<string, mixed>> $repositories */
    private function buildYamlMetadataWithRepositories(array $repositories): object
    {
        return (object) [
            'spec_version' => '2.0',
            'publication_type' => 'doi',
            'manifest' => '[]',
            'repository' => json_encode([
                'repositories' => $repositories,
            ]),
            'codecheckers' => '[]',
            'source' => null,
            'summary' => null,
            'check_time' => null,
            'certificate' => null,
            'report' => null,
            'additional_content' => null,
        ];
    }
}
