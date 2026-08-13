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
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;

class CodecheckMetadataHandler
{
    private mixed $submissionId;
    private Client $client;
    private CurlApiClient $curlApiClient;

    /**
     * `CodecheckMetadataHandler`
     * @param \APP\core\Request $request The API Request
     */
    public function __construct(Request $request, Client $client = new Client(), CurlApiClient $curlApiClient = new CurlApiClient())
    {
        $this->client = $client;
        $this->submissionId = $request->getUserVar('submissionId');
        $this->curlApiClient = $curlApiClient;
      
        // Load Composer dependencies if not already loaded
        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once($autoloadPath);
            }
        }
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
                'repository' => json_decode($metadata->repository ?? '{"repositories":null,"repoWithCodecheckYaml":null}', true),
                'codecheckers' => json_decode($metadata->codecheckers ?? '[]', true),
                'source' => $metadata->source,
                'certificate' => $metadata->certificate,
                'issue' => json_decode($metadata->issue ?? '[]', true),
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
            'repository' => json_encode($data['repository'] ?? ['repositories' => null, 'repoWithCodecheckYaml' => null]),
            'source' => $nullIfEmpty($data['source'] ?? null),
            'codecheckers' => json_encode($data['codecheckers'] ?? []),
            'certificate' => $nullIfEmpty($data['certificate'] ?? null),
            'issue' => json_encode($data['issue'] ?? ['url' => null, 'number' => null, 'labelsSelected' => []]),
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
        $repository = json_decode($metadata->repository ?? '{"repositories":null,"repoWithCodecheckYaml":null}', false);

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

        CodecheckLogger::debug("Repo" . print_r($repository, true));
        // Repository — filter out entries marked as private
        if ($repository && isset($repository->repositories) && is_array($repository->repositories)) {
            $publicUrls = array_values(array_map(
                fn($r) => isset($r->url) ? $r->url : '',
                array_filter($repository->repositories, fn($r) => empty($r->hidden))
            ));
            $publicUrls = array_filter($publicUrls);
            $filteredCount = count($repository->repositories) - count($publicUrls);
            if ($filteredCount > 0) {
                CodecheckLogger::debug("Filtered out {$filteredCount} of " . count($repository->repositories) . " repositories because they are marked as private.");
            }
            if (!empty($publicUrls)) {
                $data['repository'] = count($publicUrls) === 1
                    ? array_values($publicUrls)[0]
                    : implode(', ', $publicUrls);
            }
        }

        // Check time
        if ($metadata->check_time) {
            $data['check_time'] = date('Y-m-d H:i:s', strtotime($metadata->check_time));
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
        $yaml = "---\n" . Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Post-process to match original format
        $yaml = $this->normalizeYamlOutput($yaml);

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
     * Normalize YAML output to match original format
     */
    private function normalizeYamlOutput(string $yaml): string
    {
        // Remove quotes around URLs and simple strings
        $yaml = preg_replace("/'(https?:\/\/[^']+)'/", '$1', $yaml);
        $yaml = preg_replace("/'([^':\n]+)'/", '$1', $yaml);
        
        // Normalize list item formatting
        $yaml = preg_replace('/^(\s+)-\n\s+(\w+):/m', '$1- $2:', $yaml);
        
        return $yaml;
    }

    public function importMetadataFromRepository(string $repository): JsonResponse
    {
        // Resolve DOI links (e.g. https://doi.org/10.5281/zenodo.1234567) to their
        // final destination, in case Zenodo or OSF repositories are provided as a DOI.
        $repository = $this->curlApiClient->resolveDoi($repository);

        // Check if the repository is a Zenodo Repository
        if (preg_match('#^https://zenodo\.org/records/\d{8}/?$#', $repository)) {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            return $this->importMetadataFromZenodo($repository);
        }
        // Check if the Repository is a GitHub Repository
        elseif (preg_match('#^https://github\.com/codecheckers/#', $repository))
        {
            return $this->importMetadataFromGitHub($repository);
        }
        // Check if the Repository is an OSF Repository
        elseif (preg_match('#^https://osf\.io/([A-Za-z0-9]{5})/?$#', $repository, $matches))
        {
            $osf_node_id = $matches[1];
            return $this->importMetadataFromOSF($osf_node_id);
        }
        // Check if the Repository is a GitLab Repository
        elseif (preg_match('#^https://gitlab\.com/cdchck/community-codechecks/([^/]+)/?$#', $repository))
        {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            return $this->importMetadataFromGitLab($repository);
        } else {
            return new JsonResponse([
                'success' => false,
                'repository' => $repository,
                'error' => "The repository (" . $repository . ") URL isn't of the required format.",
            ], 400);
        }
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitHub Repository
     * @param string $repository The GitHub Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromGitHub(string $repository): JsonResponse
    {
        $githubUrlParts = CodecheckGithubRegisterApiClient::parseGithubUrl($repository);
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
                'error' => "There is no '$filename' file in this repository.",
                'repository' => $repository,
            ], 404);
        }

        // A path that is not a directory listing comes back as null or a single
        // file rather than a list, which is not something to iterate over.
        if (!is_iterable($contents)) {
            return new JsonResponse([
                'success' => false,
                'repository' => $repository,
                'error' => "$filename not found",
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
            'error' => "$filename not found",
        ], 404);
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK Zenodo Repository
     * @param string $repository The Zenodo Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromZenodo(string $repository): JsonResponse
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
    private function importMetadataFromOSF(string $osf_node_id): JsonResponse
    {
        $filename = 'codecheck.yml';
        $repository = "https://osf.io/$osf_node_id/";
        $apiUrl = "https://api.osf.io/v2/nodes/" . $osf_node_id . "/files/osfstorage/";

        // Get YAML Contents
        try {
            $api_response = $this->curlApiClient->fetch($apiUrl);
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
                    'error' => "$filename not found",
                    'repository' => $repository
                ], 404);
            }
        }
        // Check if cURL went wrong
        catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'repository' => $repository
            ], $e->getCode());
        }
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitLab Repository
     * @param string $repository The GitLab Repository
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromGitLab(string $repository): JsonResponse
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
            $yamlContent = $this->curlApiClient->fetch($pathToYamlContent);

            $metadata = Yaml::parse($yamlContent);

            return new JsonResponse([
                'success' => true,
                'repository' => $repository,
                'metadata' => $metadata,
            ], 200);
        }
        // Check if something went wrong
        catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'repository' => $repository,
            ], $e->getCode());
        }
    }
}