<?php

namespace APP\plugins\generic\codecheck\api\v1;

use PKP\security\Role;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\core\Request;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiCreateException;
use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;

use APP\facades\Repo;
use \Github\Client;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                    'route' => 'labels',
                    'handler' => [$this, 'getCodecheckIssueLabels'],
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
                    'route' => 'issue',
                    'handler' => [$this, 'updateGithubIssue'],
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
     * Gets the Issue Labels of the CODECHECK API
     * 
     * @return void
     */
    private function getCodecheckIssueLabels(): void
    {
        $dbLabelsOutdated = false;

        $issueLabelsLastUpdated = strtotime($this->getIssueLabelsLastUpdated());
        $now = strtotime(date('Y-m-d H:i:s'));
        $timeDifferenceInHours = round(($now - $issueLabelsLastUpdated) / 3600);

        if($timeDifferenceInHours > 6) {
            $dbLabelsOutdated = true;
        }

        $codecheckIssueLabels = CodecheckIssueLabels::fromDB();

        if($dbLabelsOutdated) {
            try {
                $codecheckIssueLabels = CodecheckIssueLabels::fromApi("https://codecheck.org.uk/register/venues/index.json");
            } catch (\Throwable $e) {
                $this->response->response([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], 400);
                return;
            }
        }

        // Serve the getCodecheckIssueLabels API route
        $this->response->response([
            'success' => true,
            'labels' => $codecheckIssueLabels->get()->toArray(),
        ], 200);
    }

    /**
     * This function gets when the Codecheck Issue Labels where last updated
     * 
     * @return string The Date when the issues where last updated
     */
    private function getIssueLabelsLastUpdated(): string
    {
        if (!Schema::hasTable('codecheck_issue_labels')) {
            error_log("[CODECHECK API] The Issue Label table doesn't exist");
        }

        $labelsLastUpdated = DB::table('codecheck_issue_labels')
            ->select(['labels_last_updated'])
            ->first();

        error_log("Labels: " . print_r(DB::table('codecheck_issue_labels')->select(['*'])->get()->toArray(), true));

        // If Labels weren't updated yet, set last updated to earliest date possible, so they will definitely get updated
        $labelsLastUpdated = $labelsLastUpdated->labels_last_updated ?? date('Y-m-d H:i:s', 0);

        error_log("[CODECHECK API] Codecheck Issues Last Updated: " . json_encode($labelsLastUpdated));
        
        return $labelsLastUpdated;
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
            if($reserveIdentifierMode == 'linkExistingIdentifier') {
                $identifierStr = $postParams["identifier"];
                $certificateIdentifierList = CertificateIdentifierList::fromApiWithIdentifier(
                    $codecheckGithubRegisterApiClient,
                    CertificateIdentifier::fromStr($identifierStr)
                );
            }
            $certificateIdentifierList = CertificateIdentifierList::fromApi(
                $codecheckGithubRegisterApiClient,
                true
            );
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

        $issueLabelArray = $postParams["labels"];
        $submissionData = $postParams["submission"];
        $authorString = $submissionData["authorString"];
        $articleTitle = $submissionData["title"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];

        // check if they are of type string (If not return success false over the API)
        if(is_array($issueLabelArray) && is_array($submissionData) && is_string($authorString) && is_string($articleTitle) && is_array($repositories) && is_array($codecheckers)) {
            // sort Certificate Identifier list descending
            $certificateIdentifierList->sortDesc();

            // create the new unique Identifier
            $newIdentifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);

            // create the CODECHECK Issue Labels with the selected issue labels
            $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);

            switch ($reserveIdentifierMode) {
                case 'api':
                    $issue = $this->reserveIdentifierWithApi(
                        $codecheckGithubRegisterApiClient,
                        $newIdentifier,
                        $codecheckIssueLabels,
                        $articleTitle,
                        $authorString,
                        $codecheckers,
                        $repositories
                    );
                    $issueGithubUrl = $issue['html_url'];
                    $issueNumber = $issue['number'];
                    break;
                
                case 'newIssueUrl':
                    $issueGithubUrl = $this->reserveIdentifierWithNewIssueUrl(
                        $newIdentifier,
                        $codecheckIssueLabels,
                        $articleTitle,
                        $authorString,
                        $codecheckers,
                        $repositories
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
                'error'     => "Some Parameters sent with POST to the API aren't of the expected datatype.",
            ], 400);
            return;
        }
    }

    public function updateGithubIssue(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $issue = $postParams['issue'];
        if(!is_array($issue) || !is_int($issue['number']) || !is_string($issue['url'])) {
            # TODO: JSON Error Response
            return;
        }
        
        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            'codecheckers',
            'testing-dev-register', // Name of the GitHub Repository for the Register
            $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
            $this->request->getContext(), // The Journal Object of the Submission
        );

        $issueLabelArray = $postParams["issue"]["labelsSelected"];
        $submissionData = $postParams["submission"];
        $authorString = $submissionData["authorString"];
        $articleTitle = $submissionData["title"];
        $identifierStr = $postParams["identifier"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];

        if(is_string($identifierStr) && is_array($issueLabelArray) && is_array($submissionData) && is_string($authorString) && is_string($articleTitle) && is_array($repositories) && is_array($codecheckers)) {
            $identifier = CertificateIdentifier::fromStr($identifierStr);
            $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);
            $updatedIssue = $codecheckGithubRegisterApiClient->updateIssue(
                $issue['number'],
                $identifier,
                $codecheckIssueLabels,
                $articleTitle,
                $authorString,
                $codecheckers,
                $repositories
            );

            # TODO: Check if the update function worked and return JSON Error if not

            // return a success result
            $this->response->response([
                'success' => true,
                'identifier' => $identifier->toStr(),
                'issueUrl' => $updatedIssue['html_url'],
                'issueNumber' => $updatedIssue['number'] ?? null,
            ], 200);
            return;
        } else {
            $this->response->response([
                'success'   => false,
                'error'     => "Some Parameters sent with POST to the API aren't of the expected datatype.",
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
        CodecheckIssueLabels $issueLabels,
        string $articleTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): ?array
    {
        // Add the new issue to the CODECHECK GtiHub Register
        try {
            $issue = $codecheckGithubRegisterApiClient->addIssue(
                $identifier,
                $issueLabels,
                $articleTitle,
                $authorString,
                $codecheckers,
                $repositories
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
        CodecheckIssueLabels $issueLabels,
        string $articleTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): string
    {
        $journalName = $this->request->getContext()?->getLocalizedName() ?? 'Unknwon Journal';

        $codecheckIssue = new CodecheckGithubRegisterIssue(
            'codecheckers',
            'testing-dev-register',
            $identifier,
            $issueLabels,
            $articleTitle,
            $journalName,
            $authorString,
            $this->codecheckMetadataHandler->getSubmissionId(),
            $codecheckers,
            $repositories
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

<<<<<<< HEAD
        JsonResponse::staticResponse($result, 200);
=======
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
                'issue' => json_decode($metadata->issue ?? '[]', true),
                'check_time' => $metadata->check_time,
                'summary' => $metadata->summary,
                'report' => $metadata->report,
                'additionalContent' => $metadata->additional_content,
            ] : null
        ];

        error_log("[CODECHECK API] getMetadata Response: " . json_encode($response));
        
        $this->response->response($response, 200);
>>>>>>> 9aee600 (Labels are now cached / saved within the OJS database #55)
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

<<<<<<< HEAD
        JsonResponse::staticResponse($result, 200);
=======
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
>>>>>>> 9aee600 (Labels are now cached / saved within the OJS database #55)
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