<?php

namespace APP\plugins\generic\codecheck\api\v1;

use PKP\security\Role;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\core\Request;
use \Github\Client;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueNames;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenue;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckYamlValidator;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidTokenDAO;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;

class CodecheckApiHandler
{
    private JsonResponse $response;
    private array $roles;
    private array $endpoints;
    private string $route;
    private CodecheckPlugin $plugin;
    private Request $request;
    private CodecheckMetadataHandler $codecheckMetadataHandler;

    /**
     * Initialize the Codecheck APIHandler class
     *
     * @param CodecheckPlugin $plugin
     * @param Request $request API Request
     * @return void
     */
    public function __construct(CodecheckPlugin $plugin, Request $request)
    {
        $this->plugin = $plugin;

        $this->response = new JsonResponse();

        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request, new \Github\Client(), new CurlApiClient());

        $this->roles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR,
            Role::ROLE_ID_REVIEWER,
        ];

        $this->endpoints = [
            'GET' => [
                [
                    'route' => 'venue',
                    'handler' => [$this, 'getVenueData'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'getMetadata'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'download',
                    'handler' => [$this, 'downloadFile'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'yaml',
                    'handler' => [$this, 'generateYaml'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'orcid-status',
                    'handler' => [$this, 'getOrcidStatus'],
                    'roles' => $this->roles,
                ],
            ],
            'POST' => [
                [
                    'route' => 'identifier',
                    'handler' => [$this, 'reserveIdentifier'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'saveMetadata'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'upload',
                    'handler' => [$this, 'uploadFile'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'repository',
                    'handler' => [$this, 'loadMetadataFromRepository'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'yaml/validate',
                    'handler' => [$this, 'validateYamlStructure'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'orcid-deposit',
                    'handler' => [$this, 'depositToOrcid'],
                    'roles' => $this->roles,
                ],
            ],
        ];

        $this->request = $request;

        $this->authorize();

        $this->route = $this->getRouteFromRequest();
        $this->serveRequest();
    }

    /**
     * Authorize the API connection
     */
    public function authorize()
    {
        $csrfInHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!($csrfInHeader && $csrfInHeader === $this->request->getSession()->token())) {
            $this->response->response([
                'success' => false,
                'error'   => 'No or wrong CSRF Token',
            ], 400);
            return;
        }

        $user      = $this->request->getUser() ?? null;
        $contextId = $this->request->getContext()->getId();

        if (!($user && $user->hasRole($this->roles, $contextId))) {
            $this->response->response([
                'success' => false,
                'error'   => "User has no assigned Role or doesn't have the right roles assigned to access this resource",
            ], 400);
            return;
        }
    }

    /**
     * Gets the route from the entire API Request
     */
    private function getRouteFromRequest(): ?string
    {
        if (preg_match('#api/v1/codecheck/(.*)#', $this->request->getRequestPath(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Serves the API request
     */
    private function serveRequest(): void
    {
        $method = $this->request->getRequestMethod();

        foreach ($this->endpoints[$method] as $endpoint) {
            if ($this->route == $endpoint['route']) {
                call_user_func($endpoint['handler']);
                return;
            }
        }
    }

    /**
     * Gets Venue Types, Venue Names and custom labels
     */
    private function getVenueData(): void
    {
        try {
            $codecheckVenueTypes = new CodecheckVenueTypes();
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
            return;
        }

        $context            = $this->request->getContext();
        $githubCustomLabels = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_CUSTOM_LABELS);

        $this->response->response([
            'success'      => true,
            'venueTypes'   => $codecheckVenueTypes->get()->toArray(),
            'venueNames'   => $codecheckVenueNames->get()->toArray(),
            'customLabels' => $githubCustomLabels,
        ], 200);
    }

    /**
     * Reserve a new certificate identifier
     */
    public function reserveIdentifier(): void
    {
        $postParams   = json_decode(file_get_contents('php://input'), true);
        $venueType    = $postParams["venueType"];
        $venueName    = $postParams["venueName"];
        $customLabels = $postParams["customLabels"];
        $authorString = $postParams["authorString"];

        $context                     = $this->request->getContext();
        $githubPersonalAccessToken   = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization  = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository    = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);
        $isAuthorStringEnabled       = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_AUTHOR_ANONYMITY);

        if (!$isAuthorStringEnabled || !is_string($authorString)) {
            $authorString = null;
        }

        if (is_string($venueType) && is_string($venueName) && is_array($customLabels)) {
            $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
                $githubPersonalAccessToken,
                $githubRegisterOrganization,
                $githubRegisterRepository,
                $this->codecheckMetadataHandler->getSubmissionId(),
                $context,
            );

            try {
                $certificateIdentifierList = CertificateIdentifierList::fromApi($codecheckGithubRegisterApiClient);
            } catch (ApiFetchException $ae) {
                $this->response->response(['success' => false, 'error' => $ae->getMessage()], 400);
                return;
            } catch (NoMatchingIssuesFoundException $me) {
                $this->response->response(['success' => false, 'error' => $me->getMessage()], 400);
                return;
            }

            $certificateIdentifierList->sortDesc();
            $new_identifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);
            $codecheckVenue = new CodecheckVenue($venueType, $venueName);

            try {
                $issueGithubUrl = $codecheckGithubRegisterApiClient->addIssue(
                    $new_identifier,
                    $codecheckVenue->getVenueType(),
                    $codecheckVenue->getVenueName(),
                    $customLabels,
                    $authorString,
                );
            } catch (ApiCreateException $e) {
                $this->response->response(['success' => false, 'error' => $e->getMessage()], 400);
                return;
            }

            $this->response->response([
                'success'    => true,
                'identifier' => $new_identifier->toStr(),
                'issueUrl'   => $issueGithubUrl,
            ], 200);
        } else {
            $this->response->response([
                'success' => false,
                'error'   => "The CODECHECK Venue Type and/ or Venue Names aren't of Type string as expected.",
            ], 400);
        }
    }

    /**
     * Load metadata from a remote repository (Zenodo, GitHub, OSF, GitLab)
     */
    public function loadMetadataFromRepository(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $repository = $postParams["repository"];

        if (preg_match('#^https://zenodo\.org/records/\d{8}/?$#', $repository)) {
            $repository   = rtrim($repository, '/');
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromZenodo($repository);
            $yamlResponse->constructResponse();
        } elseif (preg_match('#^https://github\.com/codecheckers/#', $repository)) {
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromGitHub($repository);
            $yamlResponse->constructResponse();
        } elseif (preg_match('#^https://osf\.io/([A-Za-z0-9]{5})/?$#', $repository, $matches)) {
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromOSF($matches[1]);
            $yamlResponse->constructResponse();
        } elseif (preg_match('#^https://gitlab\.com/cdchck/community-codechecks/([^/]+)/?$#', $repository)) {
            $repository   = rtrim($repository, '/');
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromGitLab($repository);
            $yamlResponse->constructResponse();
        } else {
            $this->response->response([
                'success'    => false,
                'repository' => $repository,
                'error'      => "The repository (" . $repository . ") isn't of the required format.",
            ], 400);
        }
    }

    /**
     * Get CODECHECK metadata for a submission
     */
    public function getMetadata(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            $this->response->response(['success' => false, 'error' => 'Submission not found'], 404);
            return;
        }

        $publication = $submission->getCurrentPublication();
        $metadata    = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->first();

        $this->response->response([
            'submissionId' => $submissionId,
            'submission'   => [
                'id'                        => $submission->getId(),
                'title'                     => $publication ? $publication->getLocalizedTitle() : '',
                'authors'                   => $this->codecheckMetadataHandler->getAuthors($publication),
                'doi'                       => $publication ? $publication->getStoredPubId('doi') : null,
                'codeRepository'            => $submission->getData('codeRepository'),
                'dataRepository'            => $submission->getData('dataRepository'),
                'manifestFiles'             => $submission->getData('manifestFiles'),
                'dataAvailabilityStatement' => $submission->getData('dataAvailabilityStatement'),
            ],
            'codecheck' => $metadata ? [
                'version'           => $metadata->version ?? 'latest',
                'publicationType'   => $metadata->publication_type ?? 'doi',
                'manifest'          => json_decode($metadata->manifest ?? '[]', true),
                'repository'        => $metadata->repository,
                'codecheckers'      => json_decode($metadata->codecheckers ?? '[]', true),
                'source'            => $metadata->source,
                'certificate'       => $metadata->certificate,
                'check_time'        => $metadata->check_time,
                'summary'           => $metadata->summary,
                'report'            => $metadata->report,
                'additionalContent' => $metadata->additional_content,
            ] : null,
        ], 200);
    }

    /**
     * Save CODECHECK metadata for a submission
     */
    public function saveMetadata(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            $this->response->response(['success' => false, 'error' => 'Submission not found'], 404);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $nullIfEmpty = function ($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };

        $metadataData = [
            'submission_id'      => $submissionId,
            'version'            => $data['version'] ?? 'latest',
            'publication_type'   => $data['publication_type'] ?? 'doi',
            'manifest'           => json_encode($data['manifest'] ?? []),
            'repository'         => $nullIfEmpty($data['repository'] ?? null),
            'source'             => $nullIfEmpty($data['source'] ?? null),
            'codecheckers'       => json_encode($data['codecheckers'] ?? []),
            'certificate'        => $nullIfEmpty($data['certificate'] ?? null),
            'check_time'         => $nullIfEmpty($data['check_time'] ?? null),
            'summary'            => $nullIfEmpty($data['summary'] ?? null),
            'report'             => $nullIfEmpty($data['report'] ?? null),
            'additional_content' => $nullIfEmpty($data['additional_content'] ?? null),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $exists = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->exists();

        if ($exists) {
            DB::table('codecheck_metadata')->where('submission_id', $submissionId)->update($metadataData);
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
        }

        $this->response->response([
            'success'      => true,
            'message'      => 'CODECHECK metadata saved successfully',
            'submissionID' => $submissionId,
            'certificate'  => $metadataData['certificate'],
        ], 200);
    }

    /**
     * Upload a file for the CODECHECK manifest
     */
    public function uploadFile(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();
        $submission   = Repo::submission()->get($submissionId);

        if (!$submission) {
            $this->response->response(['success' => false, 'error' => 'Submission not found', 'submissionID' => $submissionId], 400);
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->response->response(['success' => false, 'error' => 'No file uploaded'], 400);
            return;
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->response->response(['success' => false, 'error' => 'Upload error: ' . $file['error']], 400);
            return;
        }

        $context   = $this->request->getContext();
        $basePath  = \PKP\core\Core::getBaseDir();
        $uploadDir = $basePath . '/files/journals/' . $context->getId() . '/codecheck/' . $submissionId;

        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $this->response->response(['success' => false, 'error' => 'Failed to create directory'], 500);
            return;
        }

        $originalName = basename($file['name']);
        $filename     = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $filepath     = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->response->response(['success' => false, 'error' => 'Failed to save file'], 500);
            return;
        }

        $relativePath = 'files/journals/' . $context->getId() . '/codecheck/' . $submissionId . '/' . $filename;

        $this->response->response([
            'success'  => true,
            'filePath' => $relativePath,
            'filename' => $originalName,
            'size'     => $file['size'],
        ], 200);
    }

    /**
     * Download a file from the CODECHECK manifest
     */
    public function downloadFile(): void
    {
        $filePath = $this->request->getUserVar('file');

        if (!$filePath) {
            $this->response->response(['success' => false, 'error' => 'No file specified'], 400);
            return;
        }

        $basePath = \PKP\core\Core::getBaseDir();
        $fullPath = $basePath . '/' . $filePath;

        if (strpos($filePath, 'codecheck') === false || !file_exists($fullPath)) {
            $this->response->response(['success' => false, 'error' => 'File not found'], 404);
            return;
        }

        $filename = preg_replace('/^\d+_/', '', basename($fullPath));

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($fullPath);
        exit;
    }

    /**
     * Generate the CODECHECK YAML file for a submission
     */
    public function generateYaml(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();
        $submission   = Repo::submission()->get($submissionId);

        if (!$submission) {
            $this->response->response(['success' => false, 'error' => 'Submission not found', 'submissionID' => $submissionId], 404);
            return;
        }

        $publication = $submission->getCurrentPublication();
        $metadata    = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->first();

        if (!$metadata) {
            $this->response->response(['success' => false, 'error' => 'No CODECHECK metadata found', 'submissionID' => $submissionId], 404);
            return;
        }

        $yaml = $this->codecheckMetadataHandler->buildYaml($publication, $metadata);

        $this->response->response([
            'success'  => true,
            'yaml'     => $yaml,
            'filename' => 'codecheck.yml',
        ], 200);
    }

    /**
     * Validate YAML structure
     */
    public function validateYamlStructure(): void
    {
        $postParams  = json_decode(file_get_contents('php://input'), true);
        $yamlContent = $postParams["yaml"];

        $yamlValidator = new CodecheckYamlValidator($yamlContent);

        try {
            $yamlValidator->validateYaml();
        } catch (\Throwable $e) {
            CodecheckLogger::error('YAML parse exception: ' . $e->getMessage());
            $this->response->response(['success' => false, 'error' => $e->getMessage()], $e->getCode());
            return;
        }

        $this->response->response(['success' => true], 200);
    }

    /**
     * GET api/v1/codecheck/orcid-status?submissionId=XX
     */
    public function getOrcidStatus(): void
    {
        $submissionId = (int) $this->request->getUserVar('submissionId');

        if (!$submissionId) {
            $this->response->response(['success' => false, 'error' => 'Missing submissionId'], 400);
            return;
        }

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            $this->response->response(['success' => false, 'error' => 'Submission not found'], 404);
            return;
        }

        $metadata = DB::table('codecheck_metadata')->where('submission_id', $submissionId)->first();

        $codecheckerNames = [];
        if ($metadata && $metadata->codecheckers) {
            $decoded = json_decode($metadata->codecheckers, true);
            if (is_array($decoded)) {
                $codecheckerNames = $decoded;
            }
        }

        $tokenDAO  = new OrcidTokenDAO();
        $tokenRows = $tokenDAO->getAllBySubmission($submissionId);

        $tokensByOrcid = [];
        foreach ($tokenRows as $row) {
            if ($row->orcid_id) {
                $tokensByOrcid[$row->orcid_id] = $row;
            }
        }

        $codecheckers = [];

        if (!empty($codecheckerNames)) {
            foreach ($codecheckerNames as $cc) {
                $name     = is_array($cc) ? ($cc['name'] ?? '') : (string) $cc;
                $orcidId  = is_array($cc) ? ($cc['orcid'] ?? $cc['ORCID'] ?? null) : null;
                $tokenRow = $orcidId ? ($tokensByOrcid[$orcidId] ?? null) : null;

                $codecheckers[] = [
                    'name'          => $name,
                    'orcidId'       => $tokenRow->orcid_id ?? null,
                    'depositStatus' => $tokenRow->deposit_status ?? null,
                    'putCode'       => $tokenRow->put_code ?? null,
                    'depositedAt'   => $tokenRow->deposited_at ?? null,
                    'errorMessage'  => $tokenRow->error_message ?? null,
                ];
            }
        } else {
            foreach ($tokenRows as $row) {
                $codecheckers[] = [
                    'name'          => $row->orcid_id ?? 'Unknown',
                    'orcidId'       => $row->orcid_id,
                    'depositStatus' => $row->deposit_status,
                    'putCode'       => $row->put_code,
                    'depositedAt'   => $row->deposited_at,
                    'errorMessage'  => $row->error_message,
                ];
            }
        }

        $this->response->response([
            'success'      => true,
            'submissionId' => $submissionId,
            'codecheckers' => $codecheckers,
        ], 200);
    }

    /**
     * POST api/v1/codecheck/orcid-deposit
     */
    public function depositToOrcid(): void
    {
        $postParams   = json_decode(file_get_contents('php://input'), true);
        $submissionId = (int) ($postParams['submissionId'] ?? 0);

        if (!$submissionId) {
            $this->response->response(['success' => false, 'error' => 'Missing submissionId'], 400);
            return;
        }

        $context = $this->request->getContext();

        if (!$this->plugin->getSetting($context->getId(), Constants::ORCID_ENABLED)) {
            $this->response->response(['success' => false, 'error' => 'ORCID deposition is not enabled for this journal.'], 400);
            return;
        }

        try {
            $depositService = new OrcidDepositService($this->plugin);
            $results        = $depositService->depositForSubmission($submissionId);

            $this->response->response(['success' => true, 'results' => $results], 200);
        } catch (\Throwable $e) {
            CodecheckLogger::error('ORCID depositToOrcid API error: ' . $e->getMessage());
            $this->response->response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}