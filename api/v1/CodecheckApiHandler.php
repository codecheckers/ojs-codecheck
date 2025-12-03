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
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;

use APP\facades\Repo;
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
        $this->response = new JsonResponse();

        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request);

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
                    'route' => 'loadMetadataFromRepository',
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
            $this->response->response([
                'success'   => false,
                'error'     => 'No or wrong CSRF Token'
            ], 400);
            return;
        }

        // Check if the user that accesses this resource has at least one valid Role and if user exists
        $user = $this->request->getUser() ?? null;
        $contextId = $this->request->getContext()->getId();

        if(!($user && $user->hasRole($this->roles, $contextId))) {
            $this->response->response([
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
            $this->response->response([
                'success'   => false,
                'error'     => "Error while fetching the Venue Types: " . $e->getMessage(),
            ], 400);
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (\Throwable $e) {
            $this->response->response([
                'success'   => false,
                'error'     => "Error while fetching the Venue Names: " . $e->getMessage(),
            ], 400);
            return;
        }

        // Serve the getVenueData API route
        $this->response->response([
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
        $venueType = $postParams["venueType"];
        $venueName = $postParams["venueName"];
        $authorString = $postParams["authorString"];

        // check if they are of type string (If not return success false over the API)
        if(is_string($venueType) && is_string($venueName) && is_string($authorString)) {
            // CODECHECK GitHub Issue Register API parser
            $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
                'testing-dev-register', // Name of the GitHub Repository for the Register
                $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
                $this->request->getContext(), // The Journal Object of the Submission
            );

            error_log(print_r($this->request->getContext(), true));

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
                    $authorString,
                );
            } catch (ApiCreateException $e) {
                // return an error result
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            }

            // return a success result
            $this->response->response([
                'success' => true,
                'identifier' => $new_identifier->toStr(),
                'issueUrl' => $issueGithubUrl,
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
     * This function loads the Codecheck Metadata from an existing `codecheck.yml` in an existing Code Repository
     * 
     * @return void
     */
    public function loadMetadataFromRepository(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $repository = $postParams["repository"];

        if (preg_match('#^https://zenodo\.org/records/\d{8}/?$#', $repository)) {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            $metadata = $this->codecheckMetadataHandler->importMetadataFromZenodo($repository);

            $response_code = 200;
            if(!$metadata['success']) {
                $response_code = 400;
            }

            $this->response->response($metadata, $response_code);

        } elseif (preg_match('#^https://github\.com/codecheckers/#', $repository)) {
            $metadata = $this->codecheckMetadataHandler->importMetadataFromGitHub($repository);

            $response_code = 200;
            if(!$metadata['success']) {
                $response_code = 400;
            }

            $this->response->response($metadata, $response_code);
        } else {
            $this->response->response([
                'success' => false,
                'repository' => $repository,
                'error' => "The repository (" . $repository . ") isn't of the required format: https://zenodo.org/records/{8 digit identifier}",
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
            $this->response->response($result, 404);
        }

        $this->response->response($result, 200);
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
            $this->response->response($result, 404);
        }

        $this->response->response($result, 200);
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
            $this->response->response([
                'success' => false,
                'error' => 'Submission not found',
                'submissionID' => $submissionId,
            ], 400);
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->response->response([
                'success' => false,
                'error' => 'No file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['file'];

        error_log("[CODECHECK API] File: " . $file['name']);
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->response->response([
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
                $this->response->response([
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
            $this->response->response([
                'success' => false,
                'error' => 'Failed to save file'
            ], 500);
            return;
        }

        error_log("[CODECHECK] File saved: $filepath");

        // Return relative path for storage
        $relativePath = 'files/journals/' . $context->getId() . '/codecheck/' . $submissionId . '/' . $filename;

        $this->response->response([
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
            $this->response->response([
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
            $this->response->response([
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
            $this->response->response($result, 404);
        }

        $this->response->response($result, 200);
    }
}