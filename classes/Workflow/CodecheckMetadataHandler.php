<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use \APP\core\Request;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use Github\Client;
use Symfony\Component\Yaml\Yaml;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;

class CodecheckMetadataHandler
{
    private mixed $submissionId;
    private Client $client;
    private CurlApiClient $curlApiClient;

    /**
     * `CodecheckMetadataHandler`
     * @param \APP\core\Request $request The API Request
     */
<<<<<<< HEAD
    public function __construct(\APP\core\Request $request)
=======
    public function __construct(Request $request, Client $client, CurlApiClient $curlApiClient)
>>>>>>> 3b70be7 (Finished reworking curl calls, now possible to mock them #36, #75)
    {
        $this->client = new Client();
        $this->submissionId = $request->getUserVar('submissionId');
        $this->curlApiClient = $curlApiClient;
    }

    /**
     * Get the submission ID
     * @return mixed Returns the Submission ID for the Request that was passed in the constructor
     */
    public function getSubmissionId(): mixed
    {
        return $this->submissionId;
    }

    public function getMetadata($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();
        
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        $response = [
            'submissionId' => $submissionId,
            'submission' => [
                'id' => $submission->getId(),
                'title' => $publication ? $publication->getLocalizedTitle() : '',
                'authors' => $this->getAuthors($publication),
                'doi' => $publication ? $publication->getStoredPubId('doi') : null,
                'codeRepository' => $submission->getData('codeRepository'),
                'dataRepository' => $submission->getData('dataRepository'),
                'manifestFiles' => $submission->getData('manifestFiles'),
                'dataAvailabilityStatement' => $submission->getData('dataAvailabilityStatement'),
            ],
            'codecheck' => $metadata ? [
                'version' => $metadata->version ?? 'latest',
                'publicationType' => $metadata->publication_type ?? 'doi',
                'manifest' => json_decode($metadata->manifest ?? '[]', true),
                'repository' => $metadata->repository,
                'codecheckers' => json_decode($metadata->codecheckers ?? '[]', true),
                'source' => $metadata->source,
                'certificate' => $metadata->certificate,
                'check_time' => $metadata->check_time,
                'summary' => $metadata->summary,
                'report' => $metadata->report,
                'additionalContent' => $metadata->additional_content,
            ] : null
        ];
        
        return $response;
    }

    public function saveMetadata($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['success' => false, 'error' => 'Submission not found'];
        }

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        
        $nullIfEmpty = function($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };
        
        $metadataData = [
            'submission_id' => $submissionId,
            'version' => $data['version'] ?? 'latest',
            'publication_type' => $data['publication_type'] ?? 'doi',
            'manifest' => json_encode($data['manifest'] ?? []),
            'repository' => $nullIfEmpty($data['repository'] ?? null),
            'source' => $nullIfEmpty($data['source'] ?? null),
            'codecheckers' => json_encode($data['codecheckers'] ?? []),
            'certificate' => $nullIfEmpty($data['certificate'] ?? null),
            'check_time' => $nullIfEmpty($data['check_time'] ?? null),
            'summary' => $nullIfEmpty($data['summary'] ?? null),    
            'report' => $nullIfEmpty($data['report'] ?? null),
            'additional_content' => $nullIfEmpty($data['additional_content'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $exists = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->exists();

        if ($exists) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($metadataData);
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
        }

        return [
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully'
        ];
    }

    public function generateYaml($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();
        
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        if (!$metadata) {
            return ['error' => 'No CODECHECK metadata found'];
        }

        $yaml = $this->buildYaml($publication, $metadata);

        return [
            'yaml' => $yaml,
            'filename' => 'codecheck.yml'
        ];
    }

    public function buildYaml($publication, $metadata): string
    {
        $manifest = json_decode($metadata->manifest ?? '[]', true);
        $codecheckers = json_decode($metadata->codecheckers ?? '[]', true);

        // Build YAML data structure
        $data = [
            'version' => 'https://codecheck.org.uk/spec/config/1.0/'
        ];

        // Add source if present
        if ($metadata->source) {
            $data['source'] = $metadata->source;
        }

        // Paper section
        $authors = [];
        foreach ($this->getAuthors($publication) as $author) {
            $authorData = ['name' => $author['name']];
            if (!empty($author['orcid'])) {
                $authorData['ORCID'] = $author['orcid'];
            }
            $authors[] = $authorData;
        }

        $paperData = [
            'title' => $publication->getLocalizedTitle(),
            'authors' => $authors
        ];

        $doi = $publication->getStoredPubId('doi');
        if ($doi) {
            $paperData['reference'] = 'https://doi.org/' . $doi;
        }

        $data['paper'] = $paperData;

        // Manifest section
        $manifestData = [];
        foreach ($manifest as $file) {
            $fileData = ['file' => $file['file'] ?? ''];
            if (!empty($file['comment'])) {
                $fileData['comment'] = $file['comment'];
            }
            $manifestData[] = $fileData;
        }
        $data['manifest'] = $manifestData;

        // Codechecker section
        $codecheckerData = [];
        foreach ($codecheckers as $checker) {
            $checkerData = ['name' => $checker['name'] ?? ''];
            if (!empty($checker['orcid'])) {
                $checkerData['ORCID'] = $checker['orcid'];
            }
            $codecheckerData[] = $checkerData;
        }
        $data['codechecker'] = $codecheckerData;

        // Summary
        if ($metadata->summary) {
            $data['summary'] = $metadata->summary;
        }

        // Repository
        if ($metadata->repository) {
            $data['repository'] = $metadata->repository;
        }

        // Check time
        if ($metadata->check_time) {
            $data['check_time'] = $metadata->check_time;
        }

        // Certificate
        if ($metadata->certificate) {
            $data['certificate'] = $metadata->certificate;
        }

        // Report
        if ($metadata->report) {
            $data['report'] = $metadata->report;
        }

        // Generate YAML
        $yaml = "---\n" . Yaml::dump($data, 4, 2);

        // Add custom additional content at the end if present
        if ($metadata->additional_content) {
            $yaml .= "\n" . trim($metadata->additional_content) . "\n";
        }

        return $yaml;
    }

    /**
     * Get the Authors for a specific publication
     * @param mixed $publication The publication data
     * @return array The Authors with Name and ORCID (if isset) in an Array
     */
    public function getAuthors($publication): array
    {
        if (!$publication) {
            return [];
        }
        
        $authors = [];
        foreach ($publication->getData('authors') as $author) {
            $locale = $author->getDefaultLocale();
            $givenName = $author->getGivenName($locale) ?? '';
            $familyName = $author->getFamilyName($locale) ?? '';
            $fullName = trim($givenName . ' ' . $familyName);

            $authors[] = [
                'name' => $fullName,    
                'orcid' => $author->getOrcid()
            ];
        }
        return $authors;
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitHub Repository
     * @param string $repository The GitHub Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    public function importMetadataFromGitHub(string $repository): JsonResponse
    {
        $githubUrlParts = CodecheckRegisterGithubIssuesApiParser::parseGithubUrl($repository);
        $filename = 'codecheck.yml';

        // AUTO-DETECT DEFAULT BRANCH if path is root
        if ($githubUrlParts['path'] === '') {
            try {
                $repoData = $this->client->api('repo')->show($githubUrlParts['owner'], $githubUrlParts['repo']);
                $githubUrlParts['ref'] = $repoData['default_branch'];
            } catch (\Exception $e) {
                // fallback stays 'main'
            }
        }

        // Retrieve folder contents
        try {
            $contents = $this->client->api('repo')->contents()->show(
                $githubUrlParts['owner'],
                $githubUrlParts['repo'],
                $githubUrlParts['path'],
                $githubUrlParts['ref']
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'repository' => $repository,
            ], 404);
        }

        // Find codecheck.yml
        foreach ($contents as $item) {
            if ($item['type'] === 'file' && $item['name'] === $filename) {

                // Fetch the raw content of the codecheck.yml file
                $file = $this->client->api('repo')->contents()->show(
                    $githubUrlParts['owner'],
                    $githubUrlParts['repo'],
                    $item['path'],
                    $githubUrlParts['ref']
                );

                $metadata = Yaml::parse(base64_decode($file['content']));

                return new JsonResponse([
                    'success' => true,
                    'repository' => $repository,
                    'metadata' => $metadata,
                ], 200);
            }
        }

        return new JsonResponse([
            'success' => false,
            'repository' => $repository,
            'error' => 'codecheck.yml not found',
        ], 404);
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK Zenodo Repository
     * @param string $repository The Zenodo Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    public function importMetadataFromZenodo(string $repository): JsonResponse
    {
        $filename = 'codecheck.yml';
        $pathToCodecheckYaml = $repository . '/files/' . $filename . '?download=1';

        return $this->readYamlContent($pathToCodecheckYaml, $repository);
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK OSF Repository
     * @param string $osf_node_id The node_id of the OSF Repository for the OSF API
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    public function importMetadataFromOSF(string $osf_node_id): JsonResponse
    {
        $filename = 'codecheck.yml';
        $repository = "https://osf.io/$osf_node_id/";
        $apiUrl = "https://api.osf.io/v2/nodes/" . $osf_node_id . "/files/osfstorage/";

        // Get YAML Contents
        try {
            $api_response = $this->curlApiClient->get($apiUrl);
            $data = json_decode($api_response, true);

            if (!$data || !isset($data['data'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid OSF API response',
                    'repository' => $repository
                ], 500);
            }

            // Search for the codecheck.yml and get the guid of the codecheck.yml
            $guid = null;

            foreach ($data['data'] as $item) {
                $attributes = $item['attributes'];

                if (isset($attributes['name']) && $attributes['name'] === $filename) {
                    $guid = $attributes['guid'];   // This is the OSF file GUID
                    break;
                }
            }

            if ($guid) {
                $pathToCodecheckYaml = 'https://osf.io/download/' . $guid . '/';
                $repository = 'https://osf.io/' . $osf_node_id . '/';
                return $this->readYamlContent($pathToCodecheckYaml, $repository);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'codecheck.yml not found',
                    'repository' => $repository
                ], 404);
            }
        }
        // Check if cURL init went wrong
        catch (CurlInitException $curlInitException) {
            return new JsonResponse([
                'success' => false,
                'error' => $curlInitException->getMessage(),
                'repository' => $repository
            ], $curlInitException->getCode());
        }
        // Check if curl got a response or some form of HTTP error
        catch (CurlReadException $curlReadException) {
            return new JsonResponse([
                'success' => false,
                'error' => $curlReadException->getMessage(),
                'repository' => $repository
            ], $curlReadException->getCode());
        }
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitLab Repository
     * @param string $repository The GitLab Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    public function importMetadataFromGitLab(string $repository): JsonResponse
    {
        $filename = 'codecheck.yml';
        $pathToCodecheckYaml = $repository . '/-/raw/main/' . $filename . '?ref_type=heads&inline=false';

        return $this->readYamlContent($pathToCodecheckYaml, $repository);
    }

    /**
     * Read the yaml data and return an API response array with the content of the yaml file
     * @param string $pathToYamlContent The exact path to the download of the yaml file
     * @param string $repository The exact path to the code repository
     * @return JsonResponse The API Response with the repository and the yaml content array
     */
    private function readYamlContent(string $pathToYamlContent, string $repository): JsonResponse
    {
        // Get YAML Contents
        try {
            $yamlContent = $this->curlApiClient->get($pathToYamlContent);

            $metadata = Yaml::parse($yamlContent);

            return new JsonResponse([
                'success' => true,
                'repository' => $repository,
                'metadata' => $metadata,
            ], 200);
        }
        // Check if cURL init went wrong
        catch (CurlInitException $curlInitException) {
            return new JsonResponse([
                'success' => false,
                'error' => $curlInitException->getMessage(),
            ], $curlInitException->getCode());
        }
        // Check if curl got a response or some form of HTTP error
        catch (CurlReadException $curlReadException) {
            return new JsonResponse([
                'success' => false,
                'error' => $curlReadException->getMessage(),
            ], $curlReadException->getCode());
        }
    }
}