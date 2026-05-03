<?php

namespace APP\plugins\generic\codecheck\api\v1;

use PKP\security\Role;
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
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckVenue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;

use APP\facades\Repo;
use \Github\Client;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use Illuminate\Support\Facades\DB;

class CodecheckApiHandler
{
    private JsonResponse $response;
    private array $roles;
    private array $endpoints;
    private string $route;
    private Request $request;
    private CodecheckMetadataHandler $codecheckMetadataHandler;

    /**
     * Initialize the Codecheck APIHandler class
     * 
     * @param Request $request API Request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->response = new JsonResponse([
            'success' => false,
            'error' => 'No API Response was created.',
        ], 500);

        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request, new Client(), new CurlApiClient());

        $this->roles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_AUTHOR
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
            ],
        ];

        $this->request = $request;

        $this->authorize();

        // Get the API Route that was called from the request
        $this->route = $this->getRouteFromRequest();
        // Serve the Request
        $this->serveRequest();
    }

    /**
     * Authorize the API connection
     * 
     * @return void
     */
    public function authorize()
    {
        // Check if the CSRF Token is present and valid
        $csrfInHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if(!($csrfInHeader && $csrfInHeader === $this->request->getSession()->token())) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => 'No or wrong CSRF Token'
            ], 400);
            return;
        }

        // Check if the user that accesses this resource has at least one valid Role and if user exists
        $user = $this->request->getUser() ?? null;
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
     * 
     * @return ?string If Request is correct, this returns the route and else it returns `null`
     */
    private function getRouteFromRequest(): ?string
    {
        if (preg_match('#api/v1/codecheck/(.*)#', $this->request->getRequestPath(), $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

    /**
     * Serves the API request -> calls the function based on the called endpoint in the route
     * 
     * @return void
     */
    private function serveRequest(): void
    {
        // get the request Method like POST or GET
        $method = $this->request->getRequestMethod();

        error_log("Method: " . $method);

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

        // Serve the getVenueData API route
        JsonResponse::staticResponse([
            'success' => true,
            'venueTypes' => $codecheckVenueTypes->get()->toArray(),
            'venueNames' => $codecheckVenueNames->get()->toArray(),
        ], 200);
    }

    /**
     * This reserves a new Identifier
     * 
     * @return void
     */
    public function reserveIdentifier(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $reserveIdentifierMode = $postParams['reserveIdentifierMode'];

        if(!is_string($reserveIdentifierMode)) {
            $this->response->response([
                'success'   => false,
                'error'     => "No Reserve Identifier Mode was specified.",
            ], 400);
            return;
        }

        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            'codecheckers',
            'testing-dev-register', // Name of the GitHub Repository for the Register
            $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
            $this->request->getContext(), // The Journal Object of the Submission
        );

        // CODECHECK Register with list of all identifiers in range
        try {
            $certificateIdentifierList = CertificateIdentifierList::fromApi($codecheckGithubRegisterApiClient);
        } catch (ApiFetchException $ae) {
            $this->response->response([
                'success'   => false,
                'error'     => $ae->getMessage(),
            ], 400);
            return;
        } catch (NoMatchingIssuesFoundException $me) {
            $this->response->response([
                'success'   => false,
                'error'     => $me->getMessage(),
            ], 400);
            return;
        }

        if($reserveIdentifierMode == 'linkExistingIdentifier') {
            $identifierStr = $postParams["identifier"];
            $this->linkExistingIdentifier($identifierStr, $certificateIdentifierList);
            return;
        }

        $venueType = $postParams["venueType"];
        $venueName = $postParams["venueName"];
        $submissionData = $postParams["submission"];
        $authorString = $submissionData["authorString"];
        $articleTitle = $submissionData["title"];

        // check if they are of type string (If not return success false over the API)
        if(is_string($venueType) && is_string($venueName) && is_array($submissionData) && is_string($authorString) && is_string($articleTitle)) {
            // sort Certificate Identifier list descending
            $certificateIdentifierList->sortDesc();

            // create the new unique Identifier
            $newIdentifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

            // create the CODECHECK Venue with the selected type and name
            $codecheckVenue = new CodecheckVenue($venueType, $venueName);

            switch ($reserveIdentifierMode) {
                case 'api':
                    $issue = $this->reserveIdentifierWithApi(
                        $codecheckGithubRegisterApiClient,
                        $newIdentifier,
                        $codecheckVenue,
                        $articleTitle,
                        $authorString
                    );
                    $issueGithubUrl = $issue['html_url'];
                    $issueNumber = $issue['number'];
                    break;
                
                case 'newIssueUrl':
                    $issueGithubUrl = $this->reserveIdentifierWithNewIssueUrl(
                        $newIdentifier,
                        $codecheckVenue,
                        $articleTitle,
                        $authorString
                    );
                    break;

                default:
                    $this->response->response([
                        'success'   => false,
                        'error'     => "An unexpected mode for the reservation of the Certificate Identifier was given: " . $reserveIdentifierMode,
                    ], 400);
                    break;
            }

            // check if an error happened and return if that is the case
            if($issueGithubUrl == null) { return; }

            // return a success result
            $this->response->response([
                'success' => true,
                'identifier' => $newIdentifier->toStr(),
                'issueUrl' => $issueGithubUrl,
                'issueNumber' => $issueNumber ?? null,
            ], 200);
            return;
        } else {
            $this->response->response([
                'success'   => false,
                'error'     => "The CODECHECK Venue Type and/ or Venue Names aren't of Type string as expected.",
            ], 400);
            return;
        }
    }
     
    /**
     * This reserves a new Identifier with the GitHub API
     * 
     * @return ?array
     */
    private function reserveIdentifierWithApi(
        CodecheckGithubRegisterApiClient $codecheckGithubRegisterApiClient,
        CertificateIdentifier $identifier,
        CodecheckVenue $venue,
        string $articleTitle,
        string $authorString

    ): ?array
    {
        // Add the new issue to the CODECHECK GtiHub Register
        try {
            $issue = $codecheckGithubRegisterApiClient->addIssue(
                $identifier,
                $venue,
                $articleTitle,
                $authorString
            );
        } catch (ApiCreateException $e) {
            // return an error result
            $this->response->response([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], 400);
            return null;
        }

        return $issue;
    }

    /**
     * This reserves a new Identifier with the GitHub New Issue Url
     * 
     * @return string
     */
    private function reserveIdentifierWithNewIssueUrl(
        CertificateIdentifier $identifier,
        CodecheckVenue $venue,
        string $articleTitle,
        string $authorString
    ): string
    {
        $journalName = $this->request->getContext()?->getLocalizedName() ?? 'Unknwon Journal';

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            'codecheckers',
            'testing-dev-register',
            $identifier,
            $venue,
            $articleTitle,
            $journalName,
            $authorString,
            $this->codecheckMetadataHandler->getSubmissionId()
        );

        return $codecheckIssue->getNewIssueUrl();
    }

    private function linkExistingIdentifier(
        string $identifierStr,
        CertificateIdentifierList $certificateIdentifierList
    ) {
        $title =  "a | " . $identifierStr;
        $rawIdentifier = CertificateIdentifierList::getRawIdentifier($title);
        if($rawIdentifier == null) {
            $this->response->response([
                'success'   => false,
                'identifier' => $identifierStr,
                'error'     => "The identifier: " . $identifierStr . " isn't matching the required format (YYYY-NNN or YYYY-NNN/YYYY-NNN).",
            ], 400);
            return;
        }
        $identifier = CertificateIdentifier::fromStr($rawIdentifier);
        $issue = $certificateIdentifierList->getIssueInformationByIdentifier($identifier);
        error_log(print_r($issue, true));
        if(is_string($issue['issueUrl']) && is_int($issue['issueNumber'])) {
            $this->response->response([
                'success' => true,
                'identifier' => $identifier->toStr(),
                'issueUrl' => $issue['issueUrl'],
                'issueNumber' => $issue['issueNumber'],
            ], 200);
            return;
        }

        $this->response->response([
            'success'   => false,
            'identifier' => $identifierStr,
            'error'     => "The certificate with the Identifier: ". $identifierStr . " doesn't exist in the GitHub Register.",
        ], 404);
        return;
    }

    /**
     * This function loads the Codecheck Metadata from an existing `codecheck.yml` in an existing Code Repository
     * 
     * @return void
     */
    public function loadMetadataFromRepository(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $repository = $postParams["repository"];

        // Check if the repository is a Zenodo Repository
        if (preg_match('#^https://zenodo\.org/records/\d{8}/?$#', $repository)) {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromZenodo($repository);
            $yamlResponse->constructResponse();

        } elseif (preg_match('#^https://github\.com/codecheckers/#', $repository))
        // Check if the Repository is a GitHub Repository
        {
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromGitHub($repository);
            $yamlResponse->constructResponse();
        } elseif (preg_match('#^https://osf\.io/([A-Za-z0-9]{5})/?$#', $repository, $matches))
        // Check if the Repository is an OSF Repository
        {
            $osf_node_id = $matches[1];
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromOSF($osf_node_id);
            $yamlResponse->constructResponse();
        } elseif (preg_match('#^https://gitlab\.com/cdchck/community-codechecks/([^/]+)/?$#', $repository))
        // Check if the Repository is a GitLab Repository
        {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            $yamlResponse = $this->codecheckMetadataHandler->importMetadataFromGitLab($repository);
            $yamlResponse->constructResponse();
        } else {
            JsonResponse::staticResponse([
                'success' => false,
                'repository' => $repository,
                'error' => "The repository (" . $repository . ") isn't of the required format.",
            ], 400);
        }
    }

    /**
     * This function gets all the Codecheck Metadata
     * 
     * @return void
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
     * Save the CODECHECK Metadata for a submission
     * 
     * @return void
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
     * 
     * @return void
     */
    public function uploadFile(): void
    {
        // get submissionId
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        error_log("[CODECHECK] Upload file for submission: $submissionId");
        
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'Submission not found',
                'submissionID' => $submissionId,
            ], 400);
            return;
        }

        if (!isset($_FILES['file'])) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'No file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['file'];

        error_log("[CODECHECK API] File: " . $file['name']);
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'Upload error: ' . $file['error']
            ], 400);
            return;
        }

        // Create directory for codecheck files
        $context = $this->request->getContext();
        error_log("[CODECHECK API] Request Context ID: " . $context->getId());
        $basePath = \PKP\core\Core::getBaseDir();
        $uploadDir = $basePath . '/files/journals/' . $context->getId() . '/codecheck/' . $submissionId;
        
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                JsonResponse::staticResponse([
                    'success' => false,
                    'error' => 'Failed to create directory'
                ], 500);
                return;
            }
        }

        // Generate safe filename
        $originalName = basename($file['name']);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $filename = time() . '_' . $filename; // Add timestamp to avoid conflicts
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'Failed to save file'
            ], 500);
            return;
        }

        error_log("[CODECHECK] File saved: $filepath");

        // Return relative path for storage
        $relativePath = 'files/journals/' . $context->getId() . '/codecheck/' . $submissionId . '/' . $filename;

        JsonResponse::staticResponse([
            'success' => true,
            'filePath' => $relativePath,
            'filename' => $originalName,
            'size' => $file['size']
        ], 200);
    }

    /**
     * Download a file from the CODECHECK manifest
     * 
     * @return void
     */
    public function downloadFile(): void
    {
        $filePath = $this->request->getUserVar('file');
        
        if (!$filePath) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'No file specified'
            ], 400);
            return;
        }

        $basePath = \PKP\core\Core::getBaseDir();
        $fullPath = $basePath . '/' . $filePath;
        
        error_log("[CODECHECK] Download request: $fullPath");
        
        // Security: ensure file is in codecheck directory
        if (strpos($filePath, 'codecheck') === false || !file_exists($fullPath)) {
            JsonResponse::staticResponse([
                'success' => false,
                'error' => 'File not found'
            ], 404);
            return;
        }

        // Get original filename (remove timestamp prefix)
        $filename = basename($fullPath);
        $filename = preg_replace('/^\d+_/', '', $filename); // Remove timestamp
        
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($fullPath);
        exit;
    }

    /**
     * Generate the CODECHECK YAML file for a submission
     * 
     * @return void
     */
    public function generateYaml(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();
        $result = $this->codecheckMetadataHandler->generateYaml($this->request, $submissionId);

        if(isset($result['error'])) {
            $result = array_merge($result, ['submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse($result, 200);
    }
}