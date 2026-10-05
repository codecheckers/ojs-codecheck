<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\core\Request;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @file APP/plugins/generic/codecheck/tests/WorkflowUnitTests/CodecheckMetadataHandlerUnitTest.php
 *
 * @class CodecheckMetadataHandlerUnitTest
 *
 * @brief Tests for the CodecheckMetadataHandler class
 */
class CodecheckMetadataHandlerUnitTest extends PKPTestCase
{
    private CodecheckMetadataHandler $handler;
    private $mockRequest;
    private CurlApiClient $curlApiClient;
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

        /** mock GitHub client */
        $client = $this->createMock(\Github\Client::class);
        $this->curlApiClient = new CurlApiClient();

        $this->handler = new CodecheckMetadataHandler($this->mockRequest, $client, $this->curlApiClient);
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
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        // create a test content for the user variable 'submissionId'
        $expectedSubmissionId = 123;
        $_POST['submissionId'] = $expectedSubmissionId;

        $this->handler = new CodecheckMetadataHandler($request, $client, $this->curlApiClient);

        $actualSubmissionId = $this->handler->getSubmissionId();
        $this->assertEquals($expectedSubmissionId, $actualSubmissionId);
        $this->assertIsInt($actualSubmissionId);
    }

    /**
     * A GitHub client whose contents API answers `$file` (or throws it), and
     * which records what was asked for in `$requested`.
     */
    private function githubClient(mixed $file, ?array &$requested): \Github\Client
    {
        $requested = [];
        $contentsApi = $this->createMock(\Github\Api\Repository\Contents::class);
        $contentsApi->method('show')->willReturnCallback(
            function (string $owner, string $repo, string $path, ?string $ref) use ($file, &$requested) {
                $requested[] = compact('owner', 'repo', 'path', 'ref');
                if ($file instanceof \Throwable) {
                    throw $file;
                }
                return $file;
            }
        );

        $repoApi = $this->createMock(\Github\Api\Repo::class);
        $repoApi->expects($this->never())->method('show');
        $repoApi->method('contents')->willReturn($contentsApi);

        $client = $this->createMock(\Github\Client::class);
        $client->method('api')->willReturn($repoApi);

        return $client;
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
        $client = $this->githubClient(self::githubFile('test: yaml'), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository($address);

        $this->assertSame([compact('owner', 'repo', 'path', 'ref')], $requested);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $address, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    public function testImportMetadataFromGithubContentsShowException()
    {
        $client = $this->githubClient(new \Github\Exception\RuntimeException('Not Found', 404), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

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
        $client = $this->githubClient(new \Github\Exception\RuntimeException('API rate limit exceeded', 403), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(403, $response->getHttpResponseCode());
        $this->assertSame('API rate limit exceeded', $response->getPayloadArray()['error']);
    }

    public function testImportMetadataFromGithubSaysWhenGithubDidNotAnswer()
    {
        $client = $this->githubClient(new \Exception('cURL error 28: Operation timed out'), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);
        $unreachable = new \ReflectionProperty(GithubHttp::class, 'unreachable');
        $unreachable->setValue(null, true);

        try {
            $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');
        } finally {
            GithubHttp::reset();
        }

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
        $client = $this->githubClient(self::githubFile($yaml), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubRefusesAFolderNamedLikeTheFile()
    {
        $client = $this->githubClient([self::githubFile('test: yaml')], $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubAnswersAFileThatIsNotYaml()
    {
        $client = $this->githubClient(self::githubFile("a: b\n  c: : d"), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/testing-dev-register');

        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    public function testImportMetadataFromGithubRefusesAnAddressThatIsNotARepository()
    {
        $client = $this->githubClient(self::githubFile('test: yaml'), $requested);
        $this->handler = new CodecheckMetadataHandler(new Request(), $client, $this->curlApiClient);

        $response = $this->handler->importMetadataFromRepository('https://github.com/codecheckers/register/issues/5');

        $this->assertEquals(400, $response->getHttpResponseCode());
        $this->assertSame([], $requested);
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

    /** A handler whose repository answers with the given `codecheck.yml`. */
    private function handlerServing(string $yaml, string $repository): CodecheckMetadataHandler
    {
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willReturn($yaml);

        return new CodecheckMetadataHandler(new Request(), $this->createMock(\Github\Client::class), $curlApiClient);
    }

    public function testTheImportForASubmissionAcceptsATitleThatDiffersInCaseAndSpacing()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: 'the  PAPER on data'\nsummary: ok\n", $repository);

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertSame('ok', $response->getPayloadArray()['metadata']['summary']);
    }

    public function testTheImportForASubmissionRefusesAnotherPaper()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("paper:\n  title: Some other paper\nsummary: ok\n", $repository);

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');
        $payload = $response->getPayloadArray();

        $this->assertSame(422, $response->getHttpResponseCode());
        $this->assertFalse($payload['success']);
        $this->assertSame($repository, $payload['repository']);
        $this->assertArrayNotHasKey('metadata', $payload, 'nothing of the file is handed back');
    }

    public function testTheImportForASubmissionRefusesAFileWithNoPaperTitle()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $handler = $this->handlerServing("summary: ok\n", $repository);

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertSame(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
    }

    /** A repository that cannot be read is answered as it was, not as a title problem. */
    public function testTheImportForASubmissionLeavesAFailedFetchAlone()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willThrowException(new \RuntimeException('unreadable'));
        $handler = new CodecheckMetadataHandler(new Request(), $this->createMock(\Github\Client::class), $curlApiClient);

        $response = $handler->importMetadataForSubmission($repository, 'The Paper on Data');

        $this->assertNotSame(422, $response->getHttpResponseCode());
        $this->assertFalse($response->getPayloadArray()['success']);
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
        $this->handler = $this->handlerAnswering(['test: yaml'], $fetched);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame([$download], $fetched);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => true, 'repository' => $repository, 'metadata' => ['test' => 'yaml']],
            $response->getPayloadArray()
        );
    }

    /**
     * A handler whose cURL client answers `$answers` in turn and records the
     * addresses it was asked for in `$fetched`.
     */
    private function handlerAnswering(array $answers, ?array &$fetched): CodecheckMetadataHandler
    {
        $fetched = [];
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturnArgument(0);
        $curlApiClient->method('fetch')->willReturnCallback(function (string $url) use (&$answers, &$fetched) {
            $fetched[] = $url;
            return array_shift($answers);
        });

        return new CodecheckMetadataHandler(new Request(), $this->createMock(\Github\Client::class), $curlApiClient);
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
        $this->handler = $this->handlerAnswering([$listing, 'test: yaml'], $fetched);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame([
            'https://api.osf.io/v2/nodes/ymc3t/files/osfstorage/?filter%5Bname%5D=codecheck.yml',
            'https://files.de-1.osf.io/v1/resources/ymc3t/providers/osfstorage/66f8',
        ], $fetched);
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
        $this->handler = $this->handlerAnswering([$record, 'test: yaml'], $fetched);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertSame(["https://api.osf.io/v2/files/{$file}/", 'https://osf.io/download/5zu8b/'], $fetched);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertSame(['test' => 'yaml'], $response->getPayloadArray()['metadata']);
    }

    public function testImportMetadataFromOsfRefusesAFolderTheAddressNames()
    {
        $record = json_encode(['data' => self::osfFile('reports', kind: 'folder')]);
        $this->handler = $this->handlerAnswering([$record], $fetched);

        $response = $this->handler->importMetadataFromRepository('https://osf.io/ymc3t/files/osfstorage/66f8');

        $this->assertCount(1, $fetched);
        $this->assertEquals(404, $response->getHttpResponseCode());
    }

    public function testImportMetadataFromOsfNoDataFromOsfFilestorage()
    {
        $repository = 'https://osf.io/ymc3t/';
        $this->handler = $this->handlerAnswering([json_encode(['data' => null])], $fetched);

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
        $this->handler = $this->handlerAnswering([$listing], $fetched);

        $response = $this->handler->importMetadataFromRepository($repository);

        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertSame(
            ['success' => false, 'error' => 'codecheck.yml not found', 'repository' => $repository],
            $response->getPayloadArray()
        );
    }

    public function testImportMetadataFromOsfFetchRefused()
    {
        $errorCode = 500;
        $errorMessage = 'Error initializing the cURL API';
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlHttpException($errorMessage, $errorCode)));

        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
    }

    public function testImportMetadataFromOsfHostDidNotAnswer()
    {
        $errorMessage = 'https://example.org did not answer';
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlHttpException($errorMessage, 504)));

        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        // A cURL error number is not an HTTP status, so the response carries
        // a 500 rather than the code the exception happened to have (#130).
        $this->assertEquals(504, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
    }

    public function testImportMetadataFromGitlab()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willReturn('test: yaml');
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals(['test' => 'yaml'], $actualMetadataReturnArray['metadata']);
    }

    public function testReadYamlContentFetchRefused()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $errorCode = 500;
        $errorMessage = 'Error initializing the cURL API';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlHttpException($errorMessage, $errorCode)));
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
    }

    public function testReadYamlContentHostDidNotAnswer()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $errorMessage = 'https://example.org did not answer';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlHttpException($errorMessage, 504)));
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        // A cURL error number is not an HTTP status, so the response carries
        // a 500 rather than the code the exception happened to have (#130).
        $this->assertEquals(504, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
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
