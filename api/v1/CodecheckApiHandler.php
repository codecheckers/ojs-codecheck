<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\core\Request;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckYamlValidator;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\facades\Repo;
use \Github\Client;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleManager;
use APP\plugins\generic\codecheck\classes\Exceptions\RoleExceptions\RoleNotFoundException;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckIssueLabels;
use Exception;
use Illuminate\Support\Facades\Schema;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
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
     * @param Request $request API Request
     * @param CodecheckRoleManager $roles The CODECHECK roles for `read`, `write` and `standard` access to the API routes
     * @return void
     */
    public function __construct(CodecheckPlugin $plugin, Request $request, CodecheckRoleManager $roles)
    {
        $this->plugin = $plugin;

        $this->response = new JsonResponse([
            'success' => false,
            'error' => 'No API Response was created.',
        ], 500);

        $this->codecheckMetadataHandler = new CodecheckMetadataHandler($request, new Client(), new CurlApiClient());

        $this->roles = $roles;

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

        CodecheckLogger::debug("API Request: " . $requestMethod . " - " . $this->request->getRequestPath());

        return new ApiEndpoint($this->endpoints, $this->route, $requestMethod);
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
        $apiEndpoint = $this->getEndpoint();
        $codecheckRole = $apiEndpoint->getRoles();
        
        try {
            $pkpRoles = $codecheckRole->getRoles();

            if(!($user && $user->hasRole($pkpRoles, $contextId))) {
                JsonResponse::staticResponse([
                    'success'   => false,
                    'error'     => "User has no assigned Role or doesn't have the right roles assigned to access this resource"
                ], 400);
                return;
            }
        } catch (RoleNotFoundException $roleNotFoundException) {
            JsonResponse::staticResponse([
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

        CodecheckLogger::debug('Method: ' . $requestMethod);

        $apiEndpoint = $this->getEndpoint();

        call_user_func($apiEndpoint->getHandler());
    }

    /**
     * Gets the Issue Labels of the CODECHECK API
     * 
     * @return void
     */
    private function getCodecheckIssueLabels(): void
    {
        $dbLabelsOutdated = false;

        try {
            $issueLabelsLastUpdated = strtotime($this->getIssueLabelsLastUpdated());
        } catch (\Throwable $e) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], $e->getCode());
            return;
        }
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
                JsonResponse::staticResponse([
                    'success'   => false,
                    'error'     => $e->getMessage(),
                ], $e->getCode());
                return;
            }
        }

        // add the github custom labels specified in the plugin settings form to the Label Array returned back to the user
        $context = $this->request->getContext();
        $githubCustomLabels = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_CUSTOM_LABELS);
        $codecheckIssueLabels->addLabelArray($githubCustomLabels);

        $codecheckStatuses = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED);
        error_log(print_r($codecheckStatuses, true));

        // Serve the getCodecheckIssueLabels API route
        JsonResponse::staticResponse([
            'success' => true,
            'labels' => $codecheckIssueLabels->get()->toArray(),
        ], 200);
    }

    public function getGithubRegisterRepositoryUrl(): void
    {
        $context = $this->request->getContext();
        $githubRegisterRepositoryOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepositoryRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        JsonResponse::staticResponse([
            'success' => true,
            'url' => "github.com/$githubRegisterRepositoryOrganization/$githubRegisterRepositoryRepository",
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
     * This reserves a new Identifier
     * 
     * @return void
     */
    public function reserveIdentifier(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        
        $parameterValidationError = $this->validateReserveIdentifierParameters($postParams);

        if ($parameterValidationError !== null) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => $parameterValidationError,
            ], 400);
            return;
        }

        $issueLabelArray = $postParams["issue"]["labelsSelected"];
        $submissionData = $postParams["submission"];
        $articleTitle = $submissionData["title"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];
        $reserveIdentifierMode = $postParams['reserveIdentifierMode'];

        $context = $this->request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        $authorString = $this->getAuthorStringBasedOnAuthorAnonymity();

        if (!in_array($reserveIdentifierMode, ['api', 'newIssueUrl', 'linkExistingIdentifier'])) {
            JsonResponse::staticResponse([
                'success' => false,
                'error'   => "An unexpected mode for the reservation of the Certificate Identifier was given: " . $reserveIdentifierMode,
            ], 400);
            return;
        }

        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository, // Name of the GitHub Repository for the Register
            $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
            $context, // The Journal Object of the Submission
        );

        // CODECHECK Register with list of all identifiers in range
        try {
            if($reserveIdentifierMode == 'linkExistingIdentifier') {
                $identifierStr = $postParams["identifier"];
                $certificateIdentifierList = CertificateIdentifierList::fromApiWithIdentifier(
                    $codecheckGithubRegisterApiClient,
                    CertificateIdentifier::fromStr($identifierStr)
                );
                $this->linkExistingIdentifier($identifierStr, $certificateIdentifierList);
                return;
            }

            $certificateIdentifierList = CertificateIdentifierList::fromApi(
                $codecheckGithubRegisterApiClient,
                true
            );
            // sort Certificate Identifier list descending
            $certificateIdentifierList->sortDesc();
            // create the new unique Identifier
            $newIdentifier = CertificateIdentifier::newUniqueIdentifier($certificateIdentifierList);
            // create the CODECHECK Issue Labels with the selected issue labels
            $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);

            if($reserveIdentifierMode == 'api') {
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
            } else if($reserveIdentifierMode == 'newIssueUrl') {
                $issueGithubUrl = $this->reserveIdentifierWithNewIssueUrl(
                    $githubRegisterOrganization,
                    $githubRegisterRepository,
                    $newIdentifier,
                    $codecheckIssueLabels,
                    $articleTitle,
                    $authorString,
                    $codecheckers,
                    $repositories
                );
                $issueNumber = null;
            }
        } catch (\Throwable $e) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => $e->getMessage(),
            ], $e->getCode());
            return;
        }

        JsonResponse::staticResponse([
            'success' => true,
            'identifier' => $newIdentifier->toStr(),
            'issueUrl' => $issueGithubUrl,
            'issueNumber' => $issueNumber,
        ], 200);
        return;
    }

    public function updateGithubIssue(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);

        $parameterValidationError = $this->validateUpdateGithubIssueParameters($postParams);

        if ($parameterValidationError !== null) {
            JsonResponse::staticResponse([
                'success'   => false,
                'error'     => $parameterValidationError,
            ], 400);
            return;
        }

        $issue = $postParams['issue'];
        $issueLabelArray = $postParams["issue"]["labelsSelected"];
        $submissionData = $postParams["submission"];
        $articleTitle = $submissionData["title"];
        $identifierStr = $postParams["identifier"];
        $repositories = $postParams["repositories"];
        $codecheckers = $postParams["codecheckers"];

        $context = $this->request->getContext();
        $githubPersonalAccessToken = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $githubRegisterOrganization = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $githubRegisterRepository = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);
        $updateInformation = $this->plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);

        $authorString = $this->getAuthorStringBasedOnAuthorAnonymity();

        // CODECHECK GitHub Issue Register API parser
        $codecheckGithubRegisterApiClient = new CodecheckGithubRegisterApiClient(
            $githubPersonalAccessToken,
            $githubRegisterOrganization,
            $githubRegisterRepository, // Name of the GitHub Repository for the Register
            $this->codecheckMetadataHandler->getSubmissionId(), // Submission ID
            $context, // The Journal Object of the Submission
        );

        $identifier = CertificateIdentifier::fromStr($identifierStr);
        $codecheckIssueLabels = new CodecheckIssueLabels($issueLabelArray);
        try {
            $updatedIssue = $codecheckGithubRegisterApiClient->updateIssue(
                $updateInformation,
                $issue['number'],
                $identifier,
                $codecheckIssueLabels,
                $articleTitle,
                $authorString,
                $codecheckers,
                $repositories
            );

            JsonResponse::staticResponse([
                'success' => true,
                'identifier' => $identifier->toStr(),
                'issueUrl' => $updatedIssue['html_url'],
                'issueNumber' => $updatedIssue['number'],
            ], 200);
        } catch (\Throwable $e) {
            JsonResponse::staticResponse([
                'success' => false,
                'identifier' => $identifier->toStr(),
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    /**
     * This reserves a new Identifier with the GitHub API
     * 
     * @return array
     */
    private function reserveIdentifierWithApi(
        CodecheckGithubRegisterApiClient $codecheckGithubRegisterApiClient,
        CertificateIdentifier $identifier,
        CodecheckIssueLabels $issueLabels,
        string $articleTitle,
        string $authorString,
        array $codecheckers,
        array $repositories
    ): array
    {
        // Add the new issue to the CODECHECK GtiHub Register
        $issue = $codecheckGithubRegisterApiClient->addIssue(
            $identifier,
            $issueLabels,
            $articleTitle,
            $authorString,
            $codecheckers,
            $repositories
        );

        return $issue;
    }

    /**
     * This reserves a new Identifier with the GitHub New Issue Url
     * 
     * @return string
     */
    private function reserveIdentifierWithNewIssueUrl(
        string $githubRegisterOrganization,
        string $githubRegisterRepository,
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
            $githubRegisterOrganization,
            $githubRegisterRepository,
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
            JsonResponse::staticResponse([
                'success'   => false,
                'identifier' => $identifierStr,
                'error'     => "The identifier: " . $identifierStr . " isn't matching the required format (YYYY-NNN or YYYY-NNN/YYYY-NNN).",
            ], 400);
            return;
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
            $result = array_merge($result, ['success' => false, 'submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse(array_merge($result, ['success' => true]), 200);
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
            $result = array_merge($result, ['success' => false, 'submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse(array_merge($result, ['success' => true]), 200);
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

        CodecheckLogger::info('Upload file for submission: ' . $submissionId);
        
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

        CodecheckLogger::debug('File: ' . $file['name']);
        
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
        CodecheckLogger::debug('Request Context ID: ' . $context->getId());
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

        CodecheckLogger::info('File saved: ' . $filepath);

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
        
        CodecheckLogger::info('Download request: ' . $fullPath);
        
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
            $result = array_merge($result, ['success' => false, 'submissionID' => $submissionId]);
            JsonResponse::staticResponse($result, 404);
        }

        JsonResponse::staticResponse(array_merge($result, ['success' => true]), 200);
    }

    /**
     * This function validates the structure of a Yaml file
     * 
     * @return void
     */
    public function validateYamlStructure(): void
    {
        $postParams = json_decode(file_get_contents('php://input'), true);
        $yamlContent = $postParams["yaml"];

        $yamlValidator = new CodecheckYamlValidator($yamlContent);

        try {
            $yamlValidator->validateYaml();
        } catch (\Throwable $e) {
            CodecheckLogger::error('YAML Parse Exception: ' . $e->getMessage());

            JsonResponse::staticResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], $e->getCode());
        }

        CodecheckLogger::info('The generated YAML content is structurally valid');

        JsonResponse::staticResponse([
            'success' => true,
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