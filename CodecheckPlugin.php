<?php
namespace APP\plugins\generic\codecheck;

use PKP\security\Role;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\classes\migration\CodecheckSchemaMigration;
use APP\plugins\generic\codecheck\classes\Submission\Schema;
use APP\plugins\generic\codecheck\classes\Submission\SubmissionWizardHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidAuthHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\components\forms\FieldOptions;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiHandler;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use PKP\core\JSONMessage;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\controllers\page\CodecheckPageHandler;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleManager;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use PKP\core\Request;
use \Github\Client;

class CodecheckPlugin extends GenericPlugin
{
    private CodecheckSchemaMigration $migration;

    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $this->addAssets();

            $articleDetails = new ArticleDetails($this);
            $issueTOC = new \APP\plugins\generic\codecheck\classes\FrontEnd\IssueTOC($this);
            Hook::add('Templates::Issue::Issue::Article', $issueTOC->addCodecheckBadge(...));
            Hook::add('Templates::Article::Details', $articleDetails->addCodecheckInfo(...));

            // Opt-in checkbox on submission start
            Hook::add('Schema::get::submission', $this->addOptInToSchema(...));
            Hook::add('Form::config::before', $this->addOptInCheckbox(...));
            Hook::add('Submission::edit', $this->saveOptIn(...));

            Hook::add('Submission::validate', $this->saveWizardFieldsFromRequest(...));
            // Add hook for Ajax API calls
            Hook::add('Dispatcher::dispatch', [$this, 'setupAPIHandler']);
            // Add hook for the custom CODECHECK Pages
            Hook::add('LoadHandler', $this->setCodecheckPageHandler(...));
            // Add hook for the Template Manager
            Hook::add('TemplateManager::display', $this->callbackTemplateManagerDisplay(...));
            
            // Wizard fields schema
            $codecheckSchema = new Schema();
            Hook::add('Schema::get::publication', function($hookName, $args) use ($codecheckSchema) {
                return $codecheckSchema->addToSchemaPublication($hookName, $args);
            });

            // Wizard template handlers
            $codecheckWizard = new SubmissionWizardHandler($this);
            Hook::add('TemplateManager::display', function($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardSteps($hookName, $params);
            });
            Hook::add('Template::SubmissionWizard::Section', function($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardTemplate($hookName, $params);
            });
            Hook::add('Template::SubmissionWizard::Section::Review', function($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardReviewTemplate($hookName, $params);
            });

            // ORCID: automatically deposit when an article is published
            Hook::add('Publication::publish', $this->onPublicationPublish(...));
            
            // Test if we can hook into the publication to block it if codecheck failed
            Hook::add('Publication::validatePublish', $this->validateCodecheckStatus(...));

            // Add Localizations to Codecheck Status Preview
            Hook::add('TemplateManager::display', $this->addCodecheckStatusLocalizations(...));
        }

        return $success;
    }

    public function validateCodecheckStatus(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $publication = $args[1]; // sometimes passed by reference depending on version
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $codecheckMetadataHandler = new CodecheckMetadataHandler($request, new Client(), new CurlApiClient());
        $codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($codecheckMetadataHandler->getSubmissionId());

        CodecheckLogger::debug("Validating CODECHECK before publication!");

        $codecheckStatusKeysSelected = $this->getSetting($context->getId(), Constants::CODECHECK_STATUS_KEYS_SELECTED);

        if (empty($codecheckStatus)) {
            $errors[] = __('plugins.generic.codecheck.status.validation.failed.noStatusSet');
            return false;
        }

        if (!in_array($codecheckStatus->status, $codecheckStatusKeysSelected)) {
            $errors[] = __('plugins.generic.codecheck.status.validation.failed', [
                'codecheckStatus' => __($codecheckStatus->status)
            ]);
            return false;
        }

        return true;
    }

    public function addCodecheckStatusLocalizations($hookName, $args) {
        $templateMgr = $args[0];
        $templateMgr->addJavaScript(
            'codecheck-locale-status',
            'pkp.localeKeys = pkp.localeKeys || {};' .
            'Object.assign(pkp.localeKeys, ' . json_encode(
                array_combine(
                    Constants::CODECHECK_STATUSES,
                    array_map(fn($status) => __($status), Constants::CODECHECK_STATUSES)
                )
            ) . ');',
            ['inline' => true, 'contexts' => ['backend']]
        );
        return false;
    }
    
    /**
     * Triggered when an editor publishes an article.
     */
    public function onPublicationPublish(string $hookName, array $args): bool
    {
        $publication = $args[0];
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        if (!$submission) return false;
        if (!$submission->getData('codecheckOptIn')) return false;

        $context = Application::get()->getRequest()->getContext();
        if (!$this->getSetting($context->getId(), Constants::ORCID_ENABLED)) return false;

        try {
            $depositService = new OrcidDepositService($this);
            $results = $depositService->depositForSubmission($submission->getId());
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    CodecheckLogger::info('ORCID deposited for ' . $result['orcidId'] . ' put-code=' . $result['putCode']);
                } else {
                    CodecheckLogger::error('ORCID deposit failed for ' . $result['orcidId'] . ': ' . ($result['error'] ?? 'unknown'));
                }
            }
        } catch (\Throwable $e) {
            CodecheckLogger::error('ORCID deposit exception on publish: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Setup the CodecheckApiHandler.
     * The constructor handles the request and exits — no need to set a router handler.
     */
    public function setupAPIHandler(string $hookName, array $args): void
    {
        $request = $args[0];
        $router  = $request->getRouter();

        if (!($router instanceof \PKP\core\APIRouter)) return;

        if (str_contains($request->getRequestPath(), 'api/v1/codecheck')) {
            CodecheckLogger::debug('Instantiating the CODECHECK APIHandler');

            $adminRoles = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
            $editRoles = new CodecheckRoleArray([$adminRoles, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_MANAGER, Role::ROLE_ID_REVIEWER]);
            $readRoles = new CodecheckRoleArray([$editRoles, Role::ROLE_ID_READER, Role::ROLE_ID_AUTHOR]);

            $roles = new CodecheckRoleManager(
                readMetadata:  $readRoles,
                editMetadata:  $editRoles,
                admin:         $adminRoles,
            );

            CodecheckLogger::debug('API request: ' . $request->getRequestPath());
            new CodecheckApiHandler($this, $request, $roles);
        }
    }

    /**
     * Declare the handler function to process the actual page PATH.
     */
    public function setCodecheckPageHandler($hookName, $args)
    {
        $request = Application::get()->getRequest();

        $page    = &$args[0];
        $op      = &$args[1];
        $handler = &$args[3];

        $path = $page;
        if ($op !== 'index') {
            $path .= "/{$op}";
        }
        if ($ops = $request->getRequestedArgs()) {
            $path .= '/' . implode('/', $ops);
        }

        // ORCID OAuth routes
        if ($page === 'codecheck' && $op === 'orcid') {
            $subOp = $request->getRequestedArgs()[0] ?? '';
            if (in_array($subOp, ['startAuth', 'callback'], true)) {
                $handler = new OrcidAuthHandler($this);
                $args[1] = $subOp;
                return true;
            }
        }

        if ($page = 'codecheck' && $op == 'info') {
            $page = 'pages';
            $op = 'view';
            $handler = new CodecheckPageHandler($this);
            return true;
        }
        return false;
    }

    private function addAssets(): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        
        $templateMgr->addJavaScript(
            'codecheck-vue-app',
            "{$request->getBaseUrl()}/{$this->getPluginPath()}/public/build/build.iife.js",
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST
            ]
        );
        
        $templateMgr->addStyleSheet(
            'codecheck-vue-styles',
            "{$request->getBaseUrl()}/{$this->getPluginPath()}/public/build/build.css",
            ['contexts' => ['backend', 'frontend']]
        );
        
        $cssUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/codecheck.css';
        $templateMgr->addStyleSheet(
            'codecheck-styles',
            $cssUrl,
            ['contexts' => ['backend', 'frontend']]
        );
    }

    public function callbackTemplateManagerDisplay($hookName, $args): bool
    {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        // ----------------------------------------------------------------
        // Editorial dashboard — inject dashboard config for the Vue JS layer.
        // Passes showDashboardColumn (Issue #30) and codecheckMode so the
        // CODECHECK status column and opt-in warning box can be controlled.
        // ----------------------------------------------------------------
        if ($request->getRequestedOp() == 'editorial' && $request->getRequestedPage() == 'dashboard') {
            $showDashboardColumn = $this->getSetting($contextId, Constants::CODECHECK_SHOW_DASHBOARD_COLUMN);

            $dashboardConfig = json_encode([
                'showDashboardColumn' => $showDashboardColumn === null ? true : (bool) $showDashboardColumn,
                'codecheckMode'       => $this->getSetting($contextId, Constants::CODECHECK_MODE) ?? 'opt-in',
            ]);

            $templateMgr->addJavaScript(
                'codecheck-dashboard-config',
                'window.codecheckDashboardConfig = ' . $dashboardConfig . ';',
                [
                    'inline'   => true,
                    'contexts' => ['backend'],
                    'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                ]
            );

            $orcidAuthUrl = $request->getBaseUrl() . '/index.php/' . $context->getPath() . '/codecheck/orcid/startAuth';

            $orcidConfig = json_encode([
                'enabled' => (bool) $this->getSetting($contextId, Constants::ORCID_ENABLED),
                'authUrl' => $orcidAuthUrl,
                'apiType' => $this->getSetting($contextId, Constants::ORCID_API_TYPE)
                            ?? Constants::ORCID_API_TYPE_SANDBOX,
                'apiBaseUrl' => $request->getBaseUrl() . '/index.php/' . $context->getPath(),
            ]);

            $templateMgr->addJavaScript(
                'codecheck-orcid-config',
                'window.codecheckOrcidConfig = ' . $orcidConfig . ';',
                [
                    'inline' => true,
                    'contexts' => ['backend'],
                    'priority' => TemplateManager::STYLE_SEQUENCE_LAST
                ]
            );
        }

        // ----------------------------------------------------------------
        // Workflow page — inject submission data for the CODECHECK tab
        // ----------------------------------------------------------------
        if ($request->getRequestedOp() == 'workflow') {
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            if ($submission) {
                $templateMgr->setState([
                    'codecheckSubmission' => [
                        'id' => $submission->getId(),
                        'codecheckOptIn' => $submission->getData('codecheckOptIn'),
                        'retrieveReserveCertificateIdentifier' => $submission->getData('retrieveReserveCertificateIdentifier'),
                        'codeRepository' => $submission->getData('codeRepository'),
                        'dataRepository' => $submission->getData('dataRepository'),
                        'manifestFiles' => $submission->getData('manifestFiles'),
                        'dataAvailabilityStatement' => $submission->getData('dataAvailabilityStatement'),
                    ],
                ]);
            }
        }

        // ----------------------------------------------------------------
        // Reviewer page — inject submission data + ORCID config for Vue
        // ----------------------------------------------------------------
        if ($request->getRequestedPage() == 'reviewer' && $request->getRequestedOp() == 'submission') {
            $requestArgs  = $request->getRequestedArgs();
            $submissionId = (int) ($requestArgs[0] ?? 0);

            if ($submissionId) {
                $context    = $request->getContext();
                $contextId  = $context->getId();
                $submission = Repo::submission()->get($submissionId);

                if ($submission && $submission->getData('codecheckOptIn')) {
                    $orcidAuthUrl = $request->getBaseUrl() . '/index.php/' . $context->getPath() . '/codecheck/orcid/startAuth';

                    $reviewerData = json_encode([
                        'submissionId'   => $submission->getId(),
                        'codecheckOptIn' => true,
                        'orcid'          => [
                            'enabled'    => (bool) $this->getSetting($contextId, Constants::ORCID_ENABLED),
                            'authUrl'    => $orcidAuthUrl,
                            'apiType'    => $this->getSetting($contextId, Constants::ORCID_API_TYPE) ?? Constants::ORCID_API_TYPE_SANDBOX,
                            'apiBaseUrl' => $request->getBaseUrl() . '/index.php/' . $context->getPath(),
                        ],
                    ]);

                    $templateMgr->addJavaScript(
                        'codecheck-reviewer-data',
                        'window.codecheckReviewerData = ' . $reviewerData . ';',
                        [
                            'inline'   => true,
                            'contexts' => ['backend'],
                            'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                        ]
                    );
                }
            }
        }

        return false;
    }

    public function getUrlPageRoute(string $page): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()->url($request, ROUTE_PAGE, null, $page);
    }

    public function addOptInToSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        
        $schema->properties->codecheckOptIn = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];

        $schema->properties->retrieveReserveCertificateIdentifier = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];
        
        return false;
    }

    public function addOptInCheckbox(string $hookName, \PKP\components\forms\FormComponent $form): bool
    {
        if ($form->id === 'submitStart' || $form->id === 'submissionStart' || str_contains($form->id, 'start')) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $codecheckMode = $this->getSetting($context->getId(), Constants::CODECHECK_MODE);
            CodecheckLogger::debug('Mode: ' . $codecheckMode);
            $checkboxValue = false;
            $checkboxDisabled = false;
            $codecheckDescription = __('plugins.generic.codecheck.optIn.description', [
                'codecheckLink' => "<a href='{$this->getUrlPageRoute("codecheck")}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . "</a>"
            ]);

            if ($codecheckMode == 'opt-out') {
                $checkboxValue = true;
            } elseif ($codecheckMode == 'mandatory') {
                $checkboxValue    = true;
                $checkboxDisabled = true;
                $codecheckDescription = __('plugins.generic.codecheck.mandatory.description', [
                    'codecheckLink' => "<a href='{$this->getUrlPageRoute("codecheck")}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . "</a>"
                ]);
            }

            $form->addField(new FieldOptions('codecheckOptIn', [
                'label' => __('plugins.generic.codecheck.displayName'),
                'type' => 'checkbox',
                'options' => [
                    [
                        'value'    => 1,
                        'label'    => $codecheckDescription,
                        'disabled' => $checkboxDisabled,
                    ]
                ],
                'value'   => $checkboxValue,
                'groupId' => 'default'
            ]));
            
            return false;
        }
        
        return false;
    }

    public function saveOptIn(string $hookName, array $params): bool
    {
        $submission = $params[0];
        $params_array = $params[2];
        
        if (isset($params_array['codecheckOptIn'])) {
            $submission->setData('codecheckOptIn', $params_array['codecheckOptIn']);
        }
        
        return false;
    }

    public function saveWizardFieldsFromRequest(string $hookName, array $params): bool
    {
        $submission = $params[1];
        if (!$submission) return false;
        
        $request = Application::get()->getRequest();
        
        $codeRepository = $request->getUserVar('codeRepository');
        $dataRepository = $request->getUserVar('dataRepository');
        $manifestFiles = $request->getUserVar('manifestFiles');
        $dataAvailabilityStatement = $request->getUserVar('dataAvailabilityStatement');
        
        if ($codeRepository || $dataRepository || $manifestFiles || $dataAvailabilityStatement) {
            $publication = $submission->getCurrentPublication();
            if ($publication) {
                $updates = [];
                if ($codeRepository) $updates['codeRepository'] = $codeRepository;
                if ($dataRepository) $updates['dataRepository'] = $dataRepository;
                if ($manifestFiles) $updates['manifestFiles'] = $manifestFiles;
                if ($dataAvailabilityStatement) $updates['dataAvailabilityStatement'] = $dataAvailabilityStatement;
                
                if (!empty($updates)) {
                    Repo::publication()->edit($publication, $updates);
                }
            }
        }
        
        return false;
    }

    /**
     * Provide a name for this plugin
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.codecheck.displayName');
    }

    /**
     * Provide a description for this plugin
     */
    public function getDescription(): string
    {
        return __('plugins.generic.codecheck.description');
    }

    /**
     * Add a settings action to the plugin's entry in the plugins list.
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    public function manage($args, $request): JSONMessage
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    public function setEnabled($enabled, $contextId = null)
    {
        CodecheckLogger::debug("Plugin Enabled!");
        $result = parent::setEnabled($enabled, $contextId);
        
        if ($enabled) {
            $this->migration = new CodecheckSchemaMigration();
            $this->migration->up();
            $this->migration->issueLabelsUp();
            $this->migration->codecheckStatusUp();
        }
        
        return $result;
    }

    public function resetSchema(): void
    {
        $this->migration = new CodecheckSchemaMigration();
        $this->migration->down();
        $this->migration->up();
        $this->migration->issueLabelsDown();
        $this->migration->issueLabelsUp();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}