<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\core\Request;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueTypes;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenueNames;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckYamlValidator;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidApiClient;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidTokenDAO;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\facades\Repo;
use \Github\Client;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use Illuminate\Support\Facades\DB;

class CodecheckApiHandler
{
    private JsonResponse $response;
    private CodecheckRoleManager $roles;
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
     * @param CodecheckRoleManager $roles The CODECHECK roles for `read`, `write` and `standard` access to the API routes
     * @return void
     */
    public function __construct(CodecheckPlugin $plugin, Request $request, CodecheckRoleManager $roles)
    {
        $this->plugin = $plugin;

        $this->response = new JsonResponse();

        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request, new \Github\Client(), new CurlApiClient());

        $this->roles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR
        ];

        $this->endpoints = [
            'GET' => [
                [
                    'route' => 'labels',
                    'handler' => [$this, 'getCodecheckIssueLabels'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'getMetadata'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'download',
                    'handler' => [$this, 'downloadFile'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'yaml',
                    'handler' => [$this, 'generateYaml'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'register',
                    'handler' => [$this, 'getGithubRegisterRepositoryUrl'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'status',
                    'handler' => [$this, 'getCurrentStatus'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'status/history',
                    'handler' => [$this, 'getStatusHistory'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'orcid-status',
                    'handler' => [$this, 'getOrcidStatus'],
                    'roles' => $this->roles,
                ],
                [
                    'route' => 'orcid-test',
                    'handler' => [$this, 'testOrcidSetup'],
                    'roles' => $this->roles,
                ],
            ],
            'POST' => [
                [
                    'route' => 'identifier',
                    'handler' => [$this, 'reserveIdentifier'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'issue',
                    'handler' => [$this, 'updateGithubIssue'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'saveMetadata'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'upload',
                    'handler' => [$this, 'uploadFile'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'repository',
                    'handler' => [$this, 'loadMetadataFromRepository'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'yaml/validate',
                    'handler' => [$this, 'validateYamlStructure'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'status/update',
                    'handler' => [$this, 'updateStatus'],
                    'roles' => $roles->editMetadata(),
                ],
                [
                    'route' => 'users/roles/validation',
                    'handler' => [$this, 'validateUserAccessRightsToStatus'],
                    'roles' => $roles->readMetadata(),
                ],
                [
                    'route' => 'orcid-deposit',
                    'handler' => [$this, 'depositToOrcid'],
                    'roles' => $this->roles,
                ],
            ],
        ];

        $this->request = $request;

        $this->route = $this->getRouteFromRequest();
        // Serve the Request
        $this->serveRequest();
    }

    private function getEndpoint(): ApiEndpoint
    {
        // get the request Method like POST or GET
        $requestMethod = $this->request->getRequestMethod();

        CodecheckLogger::debug("API Method: " . $requestMethod);

        return new ApiEndpoint($this->endpoints, $this->route, $requestMethod);
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

        if(!($user && $user->hasRole($this->roles, $contextId))) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => "User has no assigned Role or doesn't have the right roles assigned to access this resource"
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
        // get the request Method like POST or GET
        $method = $this->request->getRequestMethod();

        CodecheckLogger::debug('Method: ' . $method);

        foreach ($this->endpoints[$method] as $endpoint) {
            if($this->route == $endpoint['route']) {
                call_user_func($endpoint['handler']);
                return;
            }
        }
    }

    /**
     * Gets Venue Types and Venue Names
     * 
     * @return void
     */
    private function getVenueData(): void
    {   
        try {
            $codecheckVenueTypes = new CodecheckVenueTypes();
        } catch (\Throwable $e) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => "Error while fetching the Venue Types: " . $e->getMessage(),
            ], 400);
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (\Throwable $e) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => "Error while fetching the Venue Names: " . $e->getMessage(),
            ], 400);
            return;
        }

        // get the github custom labels specified in the plugin settings form
        $context = $this->request->getContext();
        $githubCustomLabels = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_CUSTOM_LABELS);

        // Serve the getVenueData API route
        JsonResponse::staticResponse([
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
            'customLabels' => $githubCustomLabels,
        ], 200);
    }

    private function getAuthorStringBasedOnAuthorAnonymity(): string|null
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $submissionData = $postParams["submission"];
        $authorString = $submissionData["authorString"];

        $context = $this->request->getContext();
        $isAuthorStringEnabled = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_AUTHOR_ANONYMITY);

        // if Authors should be Anonymous/ if no Author string was given, set it to null
        if(!$isAuthorStringEnabled || !is_string($authorString)) {
            $authorString = null;
        }

        return $authorString;
    }

    /**
     * This function gets when the Codecheck Issue Labels where last updated
     * 
     * @return string The Date when the issues where last updated
     */
    private function getIssueLabelsLastUpdated(): string
    {
        if (!Schema::hasTable('codecheck_issue_labels')) {
            // The issue labels table doesn't exist
            CodecheckLogger::error("CODECHECK API: The Issue Label table doesn't exist");
            throw new Exception("The table 'codecheck_issue_labels' doesn't exist.", 500);
        }

        $labelsLastUpdated = DB::table('codecheck_issue_labels')
            ->select(['labels_last_updated'])
            ->first();

        CodecheckLogger::debug("Labels: " . print_r(DB::table('codecheck_issue_labels')->select(['*'])->get()->toArray(), true));

        // If Labels weren't updated yet, set last updated to earliest date possible, so they will definitely get updated
        $labelsLastUpdated = $labelsLastUpdated->labels_last_updated ?? date('Y-m-d H:i:s', 0);

        CodecheckLogger::debug("CODECHECK API: Codecheck Issues Last Updated: " . json_encode($labelsLastUpdated));
        
        return $labelsLastUpdated;
    }

    /**
     * Validates general POST parameters for reserveIdentifier & updateGithubIssue, returning an error message string
     * on the first failed guard, or null if all parameters are valid.
     */
    private function validateIdentifierPostParameters(array $postParams): ?string
    {
        if(!is_array($postParams['issue'])) {
            return "The parameter 'issue' must be an array!";
        }
        if(!is_array($postParams['issue']['labelsSelected'])) {
            return "The parameter 'issue.labelsSelected' must be an array!";
        }
        if (!is_array($postParams['submission'])) {
            return "Parameter 'submission' must be an array.";
        }
        if (!is_string($postParams['submission']['title'] ?? null)) {
            return "Parameter 'submission.title' must be a string.";
        }
        if (!is_string($postParams['submission']['authorString'])) {
            return "Parameter 'submission.authorString' must be a string.";
        }
        if (!is_array($postParams['repositories'])) {
            return "Parameter 'repositories' must be an array.";
        }
        if (!is_array($postParams['codecheckers'])) {
            return "Parameter 'codecheckers' must be an array.";
        }

        return null;
    }

    /**
     * Validates POST parameters for reserveIdentifier, returning an error message string
     * on the first failed guard, or null if all parameters are valid.
     */
    private function validateReserveIdentifierParameters(array $postParams): ?string
    {
        $error = $this->validateIdentifierPostParameters($postParams);
        if(!is_null($error)) {
            return $error;
        }
        if (!is_string($postParams['reserveIdentifierMode'])) {
            return "No Reserve Identifier Mode was specified.";
        }
        if ($postParams['reserveIdentifierMode'] === 'linkExistingIdentifier' && !is_string($postParams['identifier'] ?? null)) {
            return "Parameter 'identifier' must be a string when using mode 'linkExistingIdentifier'.";
        }

        return null;
    }

    private function validateUpdateGithubIssueParameters(array $postParams): ?string
    {
        $error = $this->validateIdentifierPostParameters($postParams);
        if(!is_null($error)) {
            return $error;
        }
        if(!is_int($postParams['issue']['number'])) {
            return "The parameter 'issue.number' must be an integer!";
        }
        if(!is_string($postParams['issue']['url'])) {
            return "The parameter 'issue.url' must be a string!";
        }

        return null;
    }

    /**
     * Reserve a new certificate identifier
     */
    public function reserveIdentifier(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $venueType = $postParams["venueType"];
        $venueName = $postParams["venueName"];
        $customLabels = $postParams["customLabels"];
        $authorString = $postParams["authorString"];

        // get the github Register Repository specified in the plugin settings form
        $context = $this->request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);
        $isAuthorStringEnabled = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_AUTHOR_ANONYMITY);

        error_log("[Codecheck Api Handler] GitHub Register Repository specified in the Settings form: " . $githubRegisterRepository);

        // if Authors should be Anonymous/ if no Author string was given, set it to null
        if(!$isAuthorStringEnabled || !is_string($authorString)) {
            $authorString = null;
        }

        // check if they are of type string (If not return success false over the API)
        if(is_string($venueType) && is_string($venueName) && is_array($customLabels)) {
            // CODECHECK GitHub Issue Register API parser
            $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
                $githubPersonalAccessToken, // The GitHub PAT (classic) needed to access the Register Repository
                $githubRegisterOrganization, // The organization owning the GitHub Register Repository
                $githubRegisterRepository, // Name of the GitHub Repository for the Register
                $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
                $context, // The Journal Object of the Submission
            );

            CodecheckLogger::debug(print_r($this->request->getContext(), true));

            // CODECHECK Register with list of all identifiers in range
            try {
                $certificateIdentifierList = CertificateIdentifierList::fromApi($codecheckGithubRegisterApiClient);
            } catch (ApiFetchException $ae) {
                JsonResponse::staticResponse([
                    'success'   => false,
                    'error'     => $ae->getMessage(),
                ], 400);
                return;
            } catch (NoMatchingIssuesFoundException $me) {
                JsonResponse::staticResponse([
                    'success'   => false,
                    'error'     => $me->getMessage(),
                ], 400);
                return;
            }

            // print Certificate Identifier list
            $certificateIdentifierList->sortDesc();

            // create the new unique Identifier
            $new_identifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

            // create the CODECHECK Venue with the selected type and name
            $codecheckVenue = new CodecheckVenue($venueType, $venueName);

            // Add the new issue to the CODECHECK GtiHub Register
            try {
                $issueGithubUrl = $codecheckGithubRegisterApiClient->addIssue(
                    $new_identifier,
                    $codecheckVenue->getVenueType(),
                    $codecheckVenue->getVenueName(),
                    $customLabels,
                    $authorString,
                    $codecheckers,
                    $repositories
                );
            } catch (ApiCreateException $e) {
                // return an error result
                JsonResponse::staticResponse([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            }

            // return a success result
            JsonResponse::staticResponse([
                'success' => true,
                'identifier' => $new_identifier->toStr(),
                'issueUrl' => $issueGithubUrl,
            ], 200);
            return;
        } else {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => "The CODECHECK Venue Type and/ or Venue Names aren't of Type string as expected.",
            ], 400);
        }
        $identifier = CertificateIdentifier::fromStr($rawIdentifier);
        $issue = $certificateIdentifierList->getIssueInformationByIdentifier($identifier);
        if(!is_array($issue) || !is_string($issue['issueUrl']) || !is_int($issue['issueNumber'])) {
            JsonResponse::staticResponse([
                'success'   => false,
                'identifier' => $identifierStr,
                'error'     => "The certificate with the Identifier: ". $identifierStr . " doesn't exist in the GitHub Register.",
            ], 404);
            return;
        }

        JsonResponse::staticResponse([
            'success' => true,
            'identifier' => $identifier->toStr(),
            'issueUrl' => $issue['issueUrl'],
            'issueNumber' => $issue['issueNumber'],
        ], 200);
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
        } elseif (preg_match('#^https://gitlab\.com/cdchck/community-codecheckers/([^/]+)/?$#', $repository)) {
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
        $result = $this->codecheckMetadataHandler->getMetadata($this->request, $submissionId);

        if(isset($result['error'])) {
            $result = array_merge($result, ['submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse($result, 200);
    }

    /**
     * Save CODECHECK metadata for a submission
     */
    public function saveMetadata(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();
        $result = $this->codecheckMetadataHandler->saveMetadata($this->request, $submissionId);

        if(isset($result['error'])) {
            $result = array_merge($result, ['submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse($result, 200);
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

        if(isset($result['error'])) {
            $result = array_merge($result, ['submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse($result, 200);
    }

    /**
     * This function validates the structure of a Yaml file
     * 
     * @return void
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

        $journalConfigError = null;
        try {
            $depositService = new OrcidDepositService($this->plugin);
            $depositService->getValidatedJournalInfo($this->request->getContext()->getId());
        } catch (\InvalidArgumentException $e) {
            $journalConfigError = $e->getMessage();
        }

        $this->response->response([
            'success'            => true,
            'submissionId'       => $submissionId,
            'codecheckers'       => $codecheckers,
            'journalConfigError' => $journalConfigError,
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

    /**
     * GET api/v1/codecheck/orcid-test
     *
     * Tests the ORCID setup without writing any data:
     * 1. Validates required journal metadata
     * 2. Makes an authenticated token request to verify credentials
     */
    public function testOrcidSetup(): void
    {
        $context   = $this->request->getContext();
        $contextId = $context->getId();

        try {
            $depositService = new OrcidDepositService($this->plugin);
            $depositService->getValidatedJournalInfo($contextId);
        } catch (\InvalidArgumentException $e) {
            $this->response->response([
                'success' => false,
                'step'    => 'metadata',
                'error'   => $e->getMessage(),
            ], 400);
            return;
        }

        $clientId     = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);
        $apiType      = $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE)
                        ?? Constants::ORCID_API_TYPE_SANDBOX;

        if (!$clientId || !$clientSecret) {
            $this->response->response([
                'success' => false,
                'step'    => 'credentials',
                'error'   => __('plugins.generic.codecheck.orcid.test.error.noCredentials'),
            ], 400);
            return;
        }

        try {
            $client = new OrcidApiClient($clientId, $clientSecret, $apiType);
            $client->getClientCredentialsToken();
        } catch (\Throwable $e) {
            $this->response->response([
                'success' => false,
                'step'    => 'credentials',
                'error'   => __('plugins.generic.codecheck.orcid.test.error.credentialsFailed') . ' ' . $e->getMessage(),
            ], 400);
            return;
        }

        $this->response->response([
            'success' => true,
            'message' => __('plugins.generic.codecheck.orcid.test.success'),
        ], 200);
    }

    public function getCurrentStatus(): void
    {
        $submissionId = (int) $this->codecheckMetadataHandler->getSubmissionId();

        $statusRecord = CodecheckStatusHandler::getCurrentStatusData($submissionId);

        if($statusRecord == null) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => "There doesn't exist any Status in the OJS Databse for this submission Id yet.",
                'statusRecord' => null,
                'allStatuses' => Constants::CODECHECK_STATUSES,
            ], 500);
        }

        JsonResponse::staticResponse([
            'success' => true,
            'statusRecord' => $statusRecord,
            'allStatuses' => Constants::CODECHECK_STATUSES,
        ], 200);
    }

    public function getStatusHistory(): void
    {
        $submissionId = (int) $this->codecheckMetadataHandler->getSubmissionId();

        $statusHistory = CodecheckStatusHandler::getStatusDataHistory($submissionId);

        if($statusHistory == null) {
            JsonResponse::staticResponse([
                'success' => false,
                'statusHistory' => $statusHistory,
            ], 400);
        }

        JsonResponse::staticResponse([
            'success' => true,
            'statusHistory' => $statusHistory,
        ], 200);
    }

    public function updateStatus(): void
    {
        $submissionId = (int) $this->codecheckMetadataHandler->getSubmissionId();

        $postParams = json_decode(file_get_contents('php://input'), true);
        $status = $postParams["status"];
        $userId = $postParams["userId"];

        if(!is_string($status) || !is_int($userId)) {
            JsonResponse::staticResponse([
                'success' => false,
                'statusRecord' => [
                    'status' => $status,
                    'userId' => $userId
                ],
                'allStatuses' => Constants::CODECHECK_STATUSES,
                'error' => 'Bad Request: Please provide a Status form of string and a User ID in the form of int.'
            ], 400);
        }

        if($userId == -1) {
            $submissionMetadata = $this->codecheckMetadataHandler->getMetadata($this->request, $submissionId);
            if(array_key_exists("error",$submissionMetadata)) {
                JsonResponse::staticResponse([
                    'success' => false,
                    'error' => $submissionMetadata["error"],
                    'allStatuses' => Constants::CODECHECK_STATUSES,
                ], 400);
            }
            $statusUpdate = CodecheckStatusHandler::automaticStatusUpdate($submissionMetadata);

            if($statusUpdate == null) {
                JsonResponse::staticResponse([
                    'success' => false,
                    'statusRecord' => $statusUpdate,
                    'allStatuses' => Constants::CODECHECK_STATUSES,
                    'error' => "Status doesn't need to be automatically updated."
                ], 200);
            } else {
                JsonResponse::staticResponse([
                    'success' => true,
                    'statusRecord' => $statusUpdate,
                    'allStatuses' => Constants::CODECHECK_STATUSES,
                ], 200);
            }
        }

        $statusUpdate = CodecheckStatusHandler::updateStatus($submissionId, $status, $userId);

        if($statusUpdate == false) {
            JsonResponse::staticResponse([
                'success' => true,
                'statusRecord' => [
                    'status' => $status,
                    'userId' => $userId
                ],
                'allStatuses' => Constants::CODECHECK_STATUSES,
                'error' => "Inserting into the CODECHECK Status Database went wrong."
            ], 500);
        }

        JsonResponse::staticResponse([
            'success' => true,
            'statusRecord' => $statusUpdate,
            'allStatuses' => Constants::CODECHECK_STATUSES,
        ], 200);
    }

    public function validateUserAccessRightsToStatus(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $user = $postParams["user"];

        if(!is_array($user["roles"])) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'Bad Request: Please provide the current User in your request.'
            ], 400);
        }

        $userRoles = $user["roles"];
        $allowedToAccess = false;

        foreach ($userRoles as $userRole) {
            if($userRole == 16) {
                $allowedToAccess = true;
                break;
            }
        }

        JsonResponse::staticResponse([
            'success' => true,
            'userAllowedToAccess' => $allowedToAccess,
        ], 200);
    }
}