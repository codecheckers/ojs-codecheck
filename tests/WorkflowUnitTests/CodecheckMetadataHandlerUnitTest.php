<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\core\Request;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
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

    public function testImportMetadataFromGithub()
    {
        /** mock contents API */
        $contentsApi = $this->createMock(\Github\Api\Repository\Contents::class);
        $contentsApi->method('show')
            ->willReturnOnConsecutiveCalls(
                // 1st call: folder contents
                [
                    [
                        'type' => 'file',
                        'name' => 'codecheck.yml',
                        'path' => 'codecheck.yml'
                    ]
                ],

                // 2nd call: file contents
                [
                    'content' => base64_encode('test: yaml')
                ]
            );

        /** mock Repo API */
        $repoApi = $this->createMock(\Github\Api\Repo::class);

        // mock show() for default branch
        $repoApi->method('show')
            ->willReturn(['default_branch' => 'root']);

        // mock contents()
        $repoApi->method('contents')
            ->willReturn($contentsApi);

        /** mock GitHub client */
        $client = $this->createMock(\Github\Client::class);

        // client->api('repo') must return Github\Api\Repo because of return types
        $client->method('api')->willReturn($repoApi);


        $request = new Request();

        $this->handler = new CodecheckMetadataHandler($request, $client, $this->curlApiClient);

        $owner = 'codecheckers';
        $repo = 'testing-dev-register';
        $repositoryUrl = 'https://github.com/' . $owner . '/' . $repo . '/';
        $response = $this->handler->importMetadataFromRepository($repositoryUrl);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray['success']);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray['repository']);
        $this->assertEquals(['test' => 'yaml'], $actualMetadataReturnArray['metadata']);
    }

    public function testImportMetadataFromGithubDefaultBranchMain()
    {
        // mock contents API
        $contentsApi = $this->createMock(\Github\Api\Repository\Contents::class);
        $contentsApi->method('show')
            ->willReturnOnConsecutiveCalls(
                // 1st call: folder contents
                [
                    [
                        'type' => 'file',
                        'name' => 'codecheck.yml',
                        'path' => 'codecheck.yml'
                    ]
                ],

                // 2nd call: file contents
                [
                    'content' => base64_encode('test: yaml')
                ]
            );

        // mock Repo API
        $repoApi = $this->createMock(\Github\Api\Repo::class);

        // mock show() for default branch main
        $repoApi->method('show')
            ->will($this->throwException(new \Exception('No default branch found.')));

        $repoApi->expects($this->once())->method('show');

        // mock contents()
        $repoApi->method('contents')
            ->willReturn($contentsApi);

        // mock GitHub client
        $client = $this->createMock(\Github\Client::class);

        // client->api('repo') must return Github\Api\Repo because of return types
        $client->method('api')->willReturn($repoApi);


        $request = new Request();

        $this->handler = new CodecheckMetadataHandler($request, $client, $this->curlApiClient);

        $owner = 'codecheckers';
        $repo = 'testing-dev-register';
        $repositoryUrl = 'https://github.com/' . $owner . '/' . $repo . '/';
        $this->handler->importMetadataFromRepository($repositoryUrl);
    }

    public function testImportMetadataFromGithubContentsShowException()
    {
        // mock contents API
        $contentsApi = $this->createMock(\Github\Api\Repository\Contents::class);
        // mock show() for the GitHub Repo contents
        $contentsApi->method('show')
            ->will($this->throwException(new \Exception('Failed to load the repository data.')));

        $contentsApi->expects($this->once())->method('show');

        // mock Repo API
        $repoApi = $this->createMock(\Github\Api\Repo::class);

        // mock show() for default branch
        $repoApi->method('show')
            ->willReturn(['default_branch' => 'root']);

        // mock contents()
        $repoApi->method('contents')
            ->willReturn($contentsApi);

        // mock GitHub client
        $client = $this->createMock(\Github\Client::class);

        // client->api('repo') must return Github\Api\Repo because of return types
        $client->method('api')->willReturn($repoApi);


        $request = new Request();

        $this->handler = new CodecheckMetadataHandler($request, $client, $this->curlApiClient);

        $owner = 'codecheckers';
        $repo = 'testing-dev-register';
        $repositoryUrl = 'https://github.com/' . $owner . '/' . $repo . '/';
        $response = $this->handler->importMetadataFromRepository($repositoryUrl);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray['repository']);
    }

    public function testImportMetadataFromGithubNoCodecheckYamlFound()
    {
        // mock contents API
        $contentsApi = $this->createMock(\Github\Api\Repository\Contents::class);
        $contentsApi->expects($this->once())->method('show');

        // mock Repo API
        $repoApi = $this->createMock(\Github\Api\Repo::class);

        // mock show() for default branch main
        $repoApi->method('show')
            ->will($this->throwException(new \Exception('No default branch found.')));

        $repoApi->expects($this->once())->method('show');

        // mock contents()
        $repoApi->method('contents')
            ->willReturn($contentsApi);

        // mock GitHub client
        $client = $this->createMock(\Github\Client::class);

        // client->api('repo') must return Github\Api\Repo because of return types
        $client->method('api')->willReturn($repoApi);


        $request = new Request();

        $this->handler = new CodecheckMetadataHandler($request, $client, $this->curlApiClient);

        $owner = 'codecheckers';
        $repo = 'testing-dev-register';
        $repositoryUrl = 'https://github.com/' . $owner . '/' . $repo . '/';
        $response = $this->handler->importMetadataFromRepository($repositoryUrl);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray['repository']);
        $this->assertEquals('codecheck.yml not found', $actualMetadataReturnArray['error']);
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

    public function testImportMetadataFromZenodo()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willReturn('test: yaml');
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        print_r($actualMetadataReturnArray);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals(['test' => 'yaml'], $actualMetadataReturnArray['metadata']);
    }

    public function testImportMetadataFromOsf()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->willReturnOnConsecutiveCalls(
                json_encode([
                    'data' => [
                        [
                            'attributes' => [
                                'name' => 'README.md',
                                'guid' => '4co4h'
                            ],
                            'attributes' => [
                                'name' => 'codecheck.yml',
                                'guid' => '5zu8b'
                            ]
                        ]
                    ]
                ]),
                'test: yaml'
            );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals(['test' => 'yaml'], $actualMetadataReturnArray['metadata']);
    }

    public function testImportMetadataFromOsfNoDataFromOsfFilestorage()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->willReturnOnConsecutiveCalls(
                json_encode([
                    'data' => null
                ]),
                'test: yaml'
            );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals('Invalid OSF API response', $actualMetadataReturnArray['error']);
    }

    public function testImportMetadataFromOsfCodecheckYamlHasNoGuid()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->willReturnOnConsecutiveCalls(
                json_encode([
                    'data' => [
                        [
                            'attributes' => [
                                'name' => 'codecheck.yml',
                                'guid' => null
                            ]
                        ]
                    ]
                ]),
                'test: yaml'
            );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals('codecheck.yml not found', $actualMetadataReturnArray['error']);
    }

    public function testImportMetadataFromOsfCurlInitException()
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
            ->will($this->throwException(new CurlInitException($errorMessage, $errorCode)));

        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
    }

    public function testImportMetadataFromOsfCurlReadException()
    {
        $curlHandle = curl_init();
        $errorMessage = curl_error($curlHandle);
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/{$osfNodeId}/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlReadException($curlHandle)));

        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        // A cURL error number is not an HTTP status, so the response carries
        // a 500 rather than the code the exception happened to have (#130).
        $this->assertEquals(500, $response->getHttpResponseCode());
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

    public function testReadYamlContentCurlInitException()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $errorCode = 500;
        $errorMessage = 'Error initializing the cURL API';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlInitException($errorMessage, $errorCode)));
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray['success']);
        $this->assertEquals($repository, $actualMetadataReturnArray['repository']);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray['error']);
    }

    public function testReadYamlContentCurlReadException()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $curlHandle = curl_init();
        $errorMessage = curl_error($curlHandle);
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
            ->will($this->throwException(new CurlReadException($curlHandle)));
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        // A cURL error number is not an HTTP status, so the response carries
        // a 500 rather than the code the exception happened to have (#130).
        $this->assertEquals(500, $response->getHttpResponseCode());
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

        $yaml = $this->handler->buildYaml($publication, $this->buildYamlMetadata('1.0'));
        $this->assertStringContainsString('https://codecheck.org.uk/spec/config/1.0/', $yaml);

        $yaml = $this->handler->buildYaml($publication, $this->buildYamlMetadata('latest'));
        $this->assertStringContainsString('https://codecheck.org.uk/spec/config/latest/', $yaml);

        // An empty version falls back rather than emitting a broken URL.
        $yaml = $this->handler->buildYaml($publication, $this->buildYamlMetadata(''));
        $this->assertStringContainsString('https://codecheck.org.uk/spec/config/latest/', $yaml);
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
            'spec_version' => 'latest',
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
            'spec_version' => 'latest',
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
            'spec_version' => 'latest',
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
