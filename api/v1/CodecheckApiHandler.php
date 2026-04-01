<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\plugins\generic\codecheck\api\v1\CodecheckRoleManager;
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
use APP\plugins\generic\codecheck\classes\Exceptions\RoleExceptions\RoleNotFoundException;
use APP\plugins\generic\codecheck\classes\Roles\CodecheckRole;
use APP\plugins\generic\codecheck\classes\Roles\ReadAccessRole;
use APP\plugins\generic\codecheck\classes\Roles\WriteAccessRole;
use APP\plugins\generic\codecheck\classes\Roles\StandardAccessRole;
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
     * @param array<CodecheckRole> $roles The CODECHECK roles for `read`, `write` and `standard` access to the API routes
     * @return void
     */
    public function __construct(Request $request, array $roles)
    {
        $this->response = new JsonResponse();
        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request);
        $this->roles = $roles;

        $this->endpoints = [
            'GET' => [
                [
                    'route' => 'venue',
                    'handler' => [$this, 'getVenueData'],
                    'role' => StandardAccessRole::class,
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'getMetadata'],
                    'role' => ReadAccessRole::class,
                ],
                [
                    'route' => 'download',
                    'handler' => [$this, 'downloadFile'],
                    'roles' => ReadAccessRole::class,
                ],
                [
                    'route' => 'yaml',
                    'handler' => [$this, 'generateYaml'],
                    'role' => ReadAccessRole::class,
                ],
            ],
            'POST' => [
                [
                    'route' => 'identifier',
                    'handler' => [$this, 'reserveIdentifier'],
                    'role' => StandardAccessRole::class,
                ],
                [
                    'route' => 'metadata',
                    'handler' => [$this, 'saveMetadata'],
                    'role' => WriteAccessRole::class,
                ],
                [
                    'route' => 'upload',
                    'handler' => [$this, 'uploadFile'],
                    'role' => WriteAccessRole::class,
                ],
            ],
        ];

        $this->request = $request;

        // Get the API Route that was called from the request
        $this->route = $this->getRouteFromRequest();

        $this->authorize();

        // Serve the Request
        $this->serveRequest();
    }

    private function getEndpoint(): ApiEndpoint
    {
        // get the request Method like POST or GET
        $requestMethod = $this->request->getRequestMethod();

        error_log("Method: " . $requestMethod);

        return new ApiEndpoint($this->endpoints, $this->route, $requestMethod);
    }

    private function getPkpRoles(string $codecheckRole): array
    {
        foreach ($this->roles as $role) {
            if($role::class === $codecheckRole) {
                return $role->getRoles();
            }
        }

        throw new RoleNotFoundException("The CODECHECK Role was not found in the array of roles.");
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
        $apiEndpoint = $this->getEndpoint();
        $codecheckRole = $apiEndpoint->getRole();
        
        try {
            $pkpRoles = $this->getPkpRoles($codecheckRole);

            if(!($user && $user->hasRole($pkpRoles, $contextId))) {
                $this->response->response([
                    'success'   => false,
                    'error'     => "User has no assigned Role or doesn't have the right roles assigned to access this resource"
                ], 400);
                return;
            }
        } catch (RoleNotFoundException $roleNotFoundException) {
            $this->response->response([
                'success'   => false,
                'error'     => $roleNotFoundException->getMessage()
            ], $roleNotFoundException->getCode());
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
        $requestMethod = $this->request->getRequestMethod();

        error_log("Method: " . $requestMethod);

        $apiEndpoint = $this->getEndpoint();

        call_user_func($apiEndpoint->getHandler());
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
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], 400);
            return;
        }

        try {
            $codecheckVenueNames = new CodecheckVenueNames();
        } catch (ApiFetchException $e) {
            $this->response->response([
                'success'   => false,
                'error'     => $e->getMessage(),
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
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            } catch (NoMatchingIssuesFoundException $me) {
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
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
     * This function gets all the Codecheck Metadata
     * 
     * @return void
     */
    public function getMetadata(): void
    {
        // get submissionId
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        error_log("[CODECHECK API] getMetadata called for submission: $submissionId");
        
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            $this->response->response([
                'success' => false,
                'error' => 'Submission not found'
            ], 404);
            return;
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
                'authors' => $this->codecheckMetadataHandler->getAuthors($publication),
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

        error_log("[CODECHECK API] Response: " . json_encode($response));
        
        $this->response->response($response, 200);
    }

    /**
     * This function saves all the CODECHECK Metadata to the Database
     * 
     * @return void
     */
    public function saveMetadata(): void
    {
        // get submissionId
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        error_log("[CODECHECK API] saveMetadata called for submission: $submissionId");
        
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            $this->response->response([
                'success' => false,
                'error' => 'Submission not found'
            ], 404);
            return;
        }

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        
        error_log("[CODECHECK API] Received data: " . $jsonData);

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
            error_log("[CODECHECK API] Updated existing record");
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
            error_log("[CODECHECK Metadata] Created new record");
        }

        $this->response->response([
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully',
            'submissionID' => $submissionId,
            'certificate' => $metadataData['certificate'],
        ], 200);
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
                'subimssionID' => $submissionId,
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
     * This function generates the Yaml file based on the CODECHECK Metadata
     * 
     * @return void
     */
    public function generateYaml(): void
    {
        $submissionId = $this->codecheckMetadataHandler->getSubmissionId();

        error_log("[CODECHECK Metadata] generateYaml called for submission: $submissionId");
        
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            $this->response->response([
                'success' => false,
                'error' => 'Submission not found',
                'subimssionID' => $submissionId,
            ], 404);
            return;
        }

        $publication = $submission->getCurrentPublication();
        
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        if (!$metadata) {
            $this->response->response([
                'success' => false,
                'error' => 'No CODECHECK metadata found',
                'subimssionID' => $submissionId,
            ], 404);
            return;
        }

        $yaml = $this->codecheckMetadataHandler->buildYaml($publication, $metadata);

        $this->response->response([
            'success' => false,
            'yaml' => $yaml,
            'filename' => 'codecheck.yml'
        ], 200);
    }
}