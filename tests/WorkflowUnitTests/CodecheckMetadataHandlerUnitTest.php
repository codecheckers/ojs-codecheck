<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use PKP\tests\PKPTestCase;

use \APP\core\Request;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use CurlHandle;

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
                    'content' => base64_encode("test: yaml")
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
        $this->assertTrue($actualMetadataReturnArray["success"]);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray["repository"]);
        $this->assertEquals(["test" => "yaml"], $actualMetadataReturnArray["metadata"]);
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
                    'content' => base64_encode("test: yaml")
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
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray["repository"]);
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
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repositoryUrl, $actualMetadataReturnArray["repository"]);
        $this->assertEquals('codecheck.yml not found', $actualMetadataReturnArray["error"]);
    }

    public function testImportMetadataFromZenodo()
    {
        $repository = 'https://zenodo.org/records/14900193';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willReturn("test: yaml");
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        print_r($actualMetadataReturnArray);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals(["test" => "yaml"], $actualMetadataReturnArray["metadata"]);
    }

    public function testImportMetadataFromOsf()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/$osfNodeId/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            json_encode([
                                "data" => [
                                    [
                                        "attributes" => [
                                            "name" => "README.md",
                                            "guid" => "4co4h"
                                        ],
                                        "attributes" => [
                                            "name" => "codecheck.yml",
                                            "guid" => "5zu8b"
                                        ]
                                    ]
                                ]
                            ]),
                            "test: yaml"
                        );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals(["test" => "yaml"], $actualMetadataReturnArray["metadata"]);
    }

    public function testImportMetadataFromOsfNoDataFromOsfFilestorage()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/$osfNodeId/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            json_encode([
                                "data" => NULL
                            ]),
                            "test: yaml"
                        );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(500, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals('Invalid OSF API response', $actualMetadataReturnArray["error"]);
    }

    public function testImportMetadataFromOsfCodecheckYamlHasNoGuid()
    {
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/$osfNodeId/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            json_encode([
                                "data" => [
                                    [
                                        "attributes" => [
                                            "name" => "codecheck.yml",
                                            "guid" => NULL
                                        ]
                                    ]
                                ]
                            ]),
                            "test: yaml"
                        );
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(404, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals('codecheck.yml not found', $actualMetadataReturnArray["error"]);
    }

    public function testImportMetadataFromOsfCurlInitException()
    {
        $errorCode = 500;
        $errorMessage = "Error initializing the cURL API";
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/$osfNodeId/";
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
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray["error"]);
    }

    public function testImportMetadataFromOsfCurlReadException()
    {
        $curlHandle = curl_init();
        $errorCode = curl_errno($curlHandle);
        $errorMessage = curl_error($curlHandle);
        $osfNodeId = 'ymc3t';
        $repository = "https://osf.io/$osfNodeId/";
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')
                        ->will($this->throwException(new CurlReadException($curlHandle)));

        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($osfNodeId);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray["error"]);
    }

    public function testImportMetadataFromGitlab()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $client = $this->createMock(\Github\Client::class);
        $request = new Request();
        $curlApiClient = $this->createMock(CurlApiClient::class);
        $curlApiClient->method('resolveDoi')->willReturn($repository);
        $curlApiClient->method('fetch')->willReturn("test: yaml");
        $this->handler = new CodecheckMetadataHandler($request, $client, $curlApiClient);
        $response = $this->handler->importMetadataFromRepository($repository);
        $actualMetadataReturnArray = json_decode($response->getPayload(), true);
        $this->assertEquals(200, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertTrue($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals(["test" => "yaml"], $actualMetadataReturnArray["metadata"]);
    }

    public function testReadYamlContentCurlInitException()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $errorCode = 500;
        $errorMessage = "Error initializing the cURL API";
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
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray["error"]);
    }

    public function testReadYamlContentCurlReadException()
    {
        $repository = 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT';
        $curlHandle = curl_init();
        $errorCode = curl_errno($curlHandle);
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
        $this->assertEquals($errorCode, $response->getHttpResponseCode());
        $this->assertCount(3, $actualMetadataReturnArray);
        $this->assertFalse($actualMetadataReturnArray["success"]);
        $this->assertEquals($repository, $actualMetadataReturnArray["repository"]);
        $this->assertEquals($errorMessage, $actualMetadataReturnArray["error"]);
    }

    public function testBuildYamlExcludesPrivateRepositories()
    {
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        $metadata = (object) [
            'version'            => 'latest',
            'publication_type'   => 'doi',
            'manifest'           => '[]',
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/public/repo', 'isPrivate' => false],
                    ['url' => 'https://github.com/private/repo', 'isPrivate' => true],
                ],
                'repoWithCodecheckYaml' => null,
            ]),
            'codecheckers'       => '[]',
            'source'             => null,
            'summary'            => null,
            'check_time'         => null,
            'certificate'        => null,
            'report'             => null,
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
            'version'            => 'latest',
            'publication_type'   => 'doi',
            'manifest'           => '[]',
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/private/repo-one', 'isPrivate' => true],
                    ['url' => 'https://github.com/private/repo-two', 'isPrivate' => true],
                ],
                'repoWithCodecheckYaml' => null,
            ]),
            'codecheckers'       => '[]',
            'source'             => null,
            'summary'            => null,
            'check_time'         => null,
            'certificate'        => null,
            'report'             => null,
            'additional_content' => null,
        ];

        $yaml = $this->handler->buildYaml($publication, $metadata);

        $this->assertStringNotContainsString('repository', $yaml);
    }

    public function testBuildYamlIncludesAllPublicRepositories()
    {
        $publication = $this->createMock(\APP\publication\Publication::class);
        $publication->method('getLocalizedTitle')->willReturn('Test Paper');
        $publication->method('getData')->with('authors')->willReturn([]);
        $publication->method('getStoredPubId')->willReturn(null);

        $metadata = (object) [
            'version'            => 'latest',
            'publication_type'   => 'doi',
            'manifest'           => '[]',
            'repository' => json_encode([
                'repositories' => [
                    ['url' => 'https://github.com/public/repo-one', 'isPrivate' => false],
                    ['url' => 'https://github.com/public/repo-two', 'isPrivate' => false],
                ],
                'repoWithCodecheckYaml' => null,
            ]),
            'codecheckers'       => '[]',
            'source'             => null,
            'summary'            => null,
            'check_time'         => null,
            'certificate'        => null,
            'report'             => null,
            'additional_content' => null,
        ];

        $yaml = $this->handler->buildYaml($publication, $metadata);

        $this->assertStringContainsString('github.com/public/repo-one', $yaml);
        $this->assertStringContainsString('github.com/public/repo-two', $yaml);
    }
}