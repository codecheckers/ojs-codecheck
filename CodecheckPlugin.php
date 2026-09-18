<?php
namespace APP\plugins\generic\codecheck;

use PKP\security\Role;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleAvailability;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\classes\migration\install\CodecheckSchemaMigration;
use APP\plugins\generic\codecheck\classes\Submission\AvailabilityStatementField;
use APP\plugins\generic\codecheck\classes\Submission\Schema;
use APP\plugins\generic\codecheck\classes\Submission\SubmissionWizardHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidAuthHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\Schema as DBSchema;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\components\forms\FieldOptions;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiController;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiHandler;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use PKP\core\JSONMessage;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\controllers\page\CodecheckPageHandler;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleManager;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckPublicationValidator;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckRegisterDepositService;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckAuthorMetadata;
use PKP\core\Request;
use \Github\Client;

class CodecheckPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $this->addAssets();

            $articleDetails = new ArticleDetails($this);
            $articleAvailability = new ArticleAvailability($this);
            $issueTOC = new \APP\plugins\generic\codecheck\classes\FrontEnd\IssueTOC($this);
            Hook::add('Templates::Issue::Issue::Article', $issueTOC->addCodecheckBadge(...));
            Hook::add('Templates::Article::Details', $articleDetails->addCodecheckInfo(...));
            // Fires inside .main_entry after the abstract, unlike the sidebar hook above
            Hook::add('Templates::Article::Main', $articleAvailability->addAvailabilityStatement(...));

            // Opt-in checkbox on submission start
            Hook::add('Schema::get::submission', $this->addOptInToSchema(...));
            Hook::add('Form::config::before', $this->addOptInCheckbox(...));
            Hook::add('Submission::edit', $this->saveOptIn(...));

            Hook::add('Submission::validate', $this->saveWizardFieldsFromRequest(...));

            // Let editors correct the availability statement after submission (Issue #167)
            $availabilityStatementField = new AvailabilityStatementField();
            Hook::add('Form::config::before', $availabilityStatementField->addToMetadataForm(...));
            // Add hook for Ajax API calls
            Hook::add('Dispatcher::dispatch', [$this, 'setupAPIHandler']);
            // Issue #50 stage 0: one route is served by a PKP controller instead.
            Hook::add('APIHandler::endpoints::plugin', [$this, 'registerApiControllers']);
            // Add hook for the custom CODECHECK Pages
            Hook::add('LoadHandler', $this->setCodecheckPageHandler(...));
            // Add hook for the Template Manager
            Hook::add('TemplateManager::display', $this->callbackTemplateManagerDisplay(...));

            // Wizard fields schema
            $codecheckSchema = new Schema();
            Hook::add('Schema::get::publication', function ($hookName, $args) use ($codecheckSchema) {
                return $codecheckSchema->addToSchemaPublication($hookName, $args);
            });

            // Wizard template handlers
            $codecheckWizard = new SubmissionWizardHandler($this);
            Hook::add('TemplateManager::display', function ($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardSteps($hookName, $params);
            });
            Hook::add('Template::SubmissionWizard::Section', function ($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardTemplate($hookName, $params);
            });
            Hook::add('Template::SubmissionWizard::Section::Review', function ($hookName, $params) use ($codecheckWizard) {
                return $codecheckWizard->addToSubmissionWizardReviewTemplate($hookName, $params);
            });

            // ORCID: automatically deposit when an article is published
            Hook::add('Publication::publish', $this->onPublicationPublish(...));
            
            // Test if we can hook into the publication to block it if codecheck failed
            Hook::add('Publication::validatePublish', $this->validatePublicationHook(...));

            // Deposit a register.csv row when a CODECHECK-opted-in article is published (Issue #10)
            Hook::add('Publication::publish', $this->depositToRegister(...));

            // Add Localizations to Codecheck Status Preview
            Hook::add('TemplateManager::display', $this->addCodecheckStatusLocalizations(...));
        }

        return $success;
    }

    /**
     * The publication will be invalid, whenever we ship at least one error in the `$errors` Array. And it will be valid, whenever the array is empty.
     * The return value of this function doesn't have to do anything with the publication validation. Instead it has to do with how OJS handles this hook. If `true` where to be returned, the Hook: `'Publication::validatePublish'` would stop to be called (meaning that for other plugins & OJS itsself this Hook wouldn't be fired anymore, if we have invalid metadata). This of course means, that e.g. if a submission is in the review stage, but somehow the editor already wants to publish it, we never get to see this error during the publication validation (because our invalid CODECHECK data would stop the hook).
     * To prevent this, it is best practise to always return `false` on a Hook, so other Plugins and OJS itsself get to call the Hook as well after our plugin did.
     * 
     * @param string $hookName The name of the Hook (`'Publication::validatePublish'`)
     * @param array $args The arguments of the Hook including the validation `$errors` Array at `&$args[0]`
     * 
     * @return bool Returns `false` to enable OJS itsself and other Plugins to continue with their implementation for this Hook
    */
    public function validatePublicationHook(string $hookName, array $args): bool
    {
        CodecheckLogger::debug("Validating Publication!");
        $errors = &$args[0];
        // The hook hands over the submission being published. Passing it on
        // matters: publishing goes through the REST API, where there is no page
        // handler to ask for the authorized submission.
        $submission = $args[2] ?? null;
        $codecheckPublicationValidator = new CodecheckPublicationValidator($this, $submission);

        $validationErrors = $codecheckPublicationValidator->validatePublication();

        if(is_array($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        /* Returns `false` to enable OJS itsself and other Plugins to continue with their implementation for this Hook */
        return false;
    }

    /**
     * Deposits a register.csv row to the CODECHECK Register when a
     * CODECHECK-opted-in submission is published.
     *
     * Fires on `Publication::publish`, i.e. after the publication has
     * actually been saved as published (not during pre-publish validation),
     * matching the hook signature:
     *   Hook::call('Publication::publish', [&$newPublication, $publication, $submission]);
     *
     * Uses whichever GitHub register organization/repository is already
     * configured in the plugin settings (see CODECHECK_GITHUB_REGISTER_ORGANIZATION /
     * CODECHECK_GITHUB_REGISTER_REPOSITORY) — currently defaulting to
     * codecheckers/testing-dev-register for development.
     *
     * @param string $hookName
     * @param array $args [0] => Publication $newPublication, [1] => Publication $publication, [2] => Submission $submission
     * @return bool
     */
    public function depositToRegister(string $hookName, array $args): bool
    {
        [$newPublication, $publication, $submission] = $args;

        if (!$submission->getData('codecheckOptIn')) {
            return false;
        }

        $context = Application::get()->getRequest()->getContext();
        if (!$this->getSetting($context->getId(), Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED)) {
            CodecheckLogger::debug('Register deposit is disabled for this journal; skipping for submission #' . $submission->getId());
            return false;
        }

        CodecheckLogger::debug('Depositing register.csv row for submission #' . $submission->getId());

        $depositService = new CodecheckRegisterDepositService($this);
        $result = $depositService->depositForSubmission($submission->getId());

        if (!$result['success']) {
            CodecheckLogger::error('Register deposit failed for submission #' . $submission->getId() . ': ' . ($result['error'] ?? 'unknown error'));
        }

        // Never block publication on a register-deposit failure — this is a
        // best-effort side effect, not a publish requirement. Failures are
        // logged; a manual/CI fallback can pick up the deposit later.
        return false;
    }

    public function addCodecheckStatusLocalizations($hookName, $args)
    {
        $templateMgr = $args[0];

        $localeKeys = array_combine(
            Constants::CODECHECK_STATUSES,
            array_map(fn($status) => __($status), Constants::CODECHECK_STATUSES)
        );

        $localeKeys[Constants::CODECHECK_STATUS_PENDING] = __(Constants::CODECHECK_STATUS_PENDING);

        $templateMgr->addJavaScript(
            'codecheck-locale-status',
            'pkp.localeKeys = pkp.localeKeys || {};' .
            'Object.assign(pkp.localeKeys, ' . json_encode($localeKeys) . ');',
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
    /**
     * Routes the PKP controller serves instead of the hand-rolled handler.
     *
     * Stage 0 of the migration in `plan-50-p2-api-controller.md`: one endpoint,
     * to check the migration's assumptions against a running instance.
     */
    private const CONTROLLER_ROUTES = ['status'];

    /**
     * Is this request one the controller now answers?
     */
    private static function servedByController(string $requestPath): bool
    {
        if (!preg_match('~/api/v\d+/codecheck/([^?#]+)~', $requestPath, $matches)) {
            return false;
        }

        return in_array(trim($matches[1], '/'), self::CONTROLLER_ROUTES, true);
    }

    /**
     * Hand the CODECHECK controller to OJS's API router.
     *
     * Note the signature. `APIHandler::endpoints::plugin` is raised with
     * `Hook::run('...', [$this])`, and `Hook::run()` **spreads** its arguments
     * (`call_user_func_array($callback, [$hookName, ...$args])`), so the router
     * arrives as its own parameter. Every other hook this plugin uses is raised
     * through `Hook::call()`, which wraps the arguments once more before handing
     * them to `run()` — which is why those callbacks take `array $args`.
     *
     * Getting this wrong is invisible: the TypeError is thrown inside the hook
     * and PKP swallows it, so the callback simply appears never to have run and
     * the request falls through to OJS's own 404.
     */
    public function registerApiControllers(string $hookName, \PKP\core\APIRouter $router): bool
    {
        $router->registerPluginApiControllers([new CodecheckApiController()]);

        return Hook::CONTINUE;
    }

    /**
     * Which journal roles may read, write and administer CODECHECK data.
     *
     * Built here rather than inline in `setupAPIHandler()` so that a test can
     * assert against the sets the plugin actually installs. They were duplicated
     * in the test instead, and when `ROLE_ID_REVIEWER` moved from reading to
     * writing the test went on describing the old arrangement (Issue #173).
     *
     * **A reviewer may read, not write.** Writing CODECHECK data is for editors,
     * and — once the handler can tell — for the reviewer assigned to that one
     * submission and flagged as its codechecker. The role sets cannot express
     * that, because they answer "does this user hold this role anywhere in the
     * journal"; see #174.
     */
    public static function buildRoleManager(): CodecheckRoleManager
    {
        $adminRoles = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
        $editRoles = new CodecheckRoleArray([$adminRoles, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]);
        $readRoles = new CodecheckRoleArray([$editRoles, Role::ROLE_ID_READER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER]);

        return new CodecheckRoleManager(
            readMetadata: $readRoles,
            editMetadata: $editRoles,
            admin: $adminRoles,
        );
    }

    public function setupAPIHandler(string $hookName, array $args): void
    {
        $request = $args[0];
        $router  = $request->getRouter();

        if (!($router instanceof \PKP\core\APIRouter)) return;

        if (str_contains($request->getRequestPath(), 'api/v1/codecheck')) {
            // Scaffolding for the stage 0 spike (Issue #50). The routes listed
            // here are served by CodecheckApiController; this hook fires before
            // routing and the handler below exits, so without the skip the
            // controller would never be reached. Goes away with the handler.
            if (self::servedByController($request->getRequestPath())) {
                return;
            }

            CodecheckLogger::debug('Instantiating the CODECHECK APIHandler');

            $apiHandler = new CodecheckApiHandler($this, $request, self::buildRoleManager());
            CodecheckLogger::debug('API request: ' . $request->getRequestPath());
        }

        if (!isset($apiHandler)) {
            return;
        }

        // Not registered with the router: setHandler() takes a PKPHandler and
        // CodecheckApiHandler is not one. The call used to stand here and was
        // simply never reached, because constructing the handler authorized,
        // served and exited. Serving from execute() made it reachable, and it
        // raised a TypeError inside the hook — which PKP swallows, leaving OJS
        // to answer every plugin API call with its own 404.
        $apiHandler->execute();
        exit;
    }

    /**
     * Declare the handler function to process the actual page PATH.
     */
    public function setCodecheckPageHandler($hookName, $args)
    {
        $page    = &$args[0];
        $op      = &$args[1];
        $handler = &$args[3];

        // ORCID OAuth routes. The request is only needed to read the sub-operation
        // off this one route, so it is fetched here rather than for every page.
        if ($page === 'codecheck' && $op === 'orcid') {
            $subOp = Application::get()->getRequest()->getRequestedArgs()[0] ?? '';
            if (in_array($subOp, ['startAuth', 'callback'], true)) {
                $handler = new OrcidAuthHandler($this);
                $args[1] = $subOp;
                return true;
            }
        }

        if ($page === 'codecheck' && $op === 'info') {
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

        // No context means we're on a site-wide admin page — nothing to inject
        if (!$context) return false;

        $contextId = $context->getId();

        // Editorial dashboard — inject dashboard config for the Vue JS layer.
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

        // Workflow page — inject submission data for the CODECHECK tab.
        if ($request->getRequestedOp() == 'workflow') {
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            if ($submission) {
                // The author's repositories and manifest entries are no longer publication
                // fields — they go straight into codecheck_metadata — so only the two
                // submission-level flags and the publication's statement are left here.
                $publication = $submission->getCurrentPublication();

                $templateMgr->setState([
                    'codecheckSubmission' => [
                        'id' => $submission->getId(),
                        'codecheckOptIn' => $submission->getData('codecheckOptIn'),
                        'retrieveReserveCertificateIdentifier' => $submission->getData('retrieveReserveCertificateIdentifier'),
                        'dataAvailabilityStatement'            => $publication ? $publication->getData('dataAvailabilityStatement') : null,
                    ]
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
            'type'       => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];

        $schema->properties->retrieveReserveCertificateIdentifier = (object) [
            'type'       => 'string',
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
            $codecheckMandatory = false;
            $codecheckDescription = __('plugins.generic.codecheck.optIn.description', [
                'codecheckLink' => "<a href='{$this->getUrlPageRoute("codecheck")}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . "</a>"
            ]);

            if ($codecheckMode == 'opt-out') {
                $checkboxValue = true;
            } elseif ($codecheckMode == 'mandatory') {
                $checkboxValue = true;
                $codecheckMandatory = true;
                $codecheckDescription = __('plugins.generic.codecheck.mandatory.description', [
                    'codecheckLink' => "<a href='{$this->getUrlPageRoute("codecheck")}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . "</a>"
                ]);
            }

            $form->addField(new FieldOptions('codecheckOptIn', [
                'label' => __('plugins.generic.codecheck.displayName'),
                'isRequired' => $codecheckMandatory,
                'type' => 'checkbox',
                'options' => [
                    [
                        'value' => 1, 
                        'label' => $codecheckDescription,
                        'disabled' => $codecheckMandatory,
                    ]
                ],
                'value'   => $checkboxValue,
                'groupId' => 'default'
            ]));
        }

        return false;
    }

    public function saveOptIn(string $hookName, array $params): bool
    {
        $submission   = $params[0];
        $params_array = $params[2];

        if (isset($params_array['codecheckOptIn'])) {
            $submission->setData('codecheckOptIn', $params_array['codecheckOptIn']);
        }

        return false;
    }

    /**
     * Persist what the author entered in the submission wizard.
     *
     * Repositories and expected output files go straight into
     * `codecheck_metadata`, the same record the codechecker edits later, so
     * there is one list rather than two that have to be reconciled. Entries
     * are marked `providedByAuthor` so the workflow form can show where they
     * came from and refuse to delete them.
     *
     * The availability statement stays on the publication: it has no
     * counterpart in codecheck.yml and no codechecker view.
     */
    public function saveWizardFieldsFromRequest(string $hookName, array $params): bool
    {
        $submission = $params[1];

        if (!$submission) {
            return false;
        }

        $request = Application::get()->getRequest();

        $dataAvailabilityStatement = $request->getUserVar('dataAvailabilityStatement');
        if ($dataAvailabilityStatement) {
            $publication = $submission->getCurrentPublication();
            if ($publication) {
                Repo::publication()->edit($publication, [
                    'dataAvailabilityStatement' => $dataAvailabilityStatement,
                ]);
            }
        }

        $repositories = $request->getUserVar('repositories');
        $manifestFiles = $request->getUserVar('manifestFiles');

        if ($repositories === null && $manifestFiles === null) {
            return false;
        }

        $authorMetadata = new CodecheckAuthorMetadata($submission->getId());

        if ($repositories !== null) {
            $authorMetadata->setRepositories(self::splitLines($repositories));
        }

        if ($manifestFiles !== null) {
            $authorMetadata->setManifest(self::splitLines($manifestFiles));
        }

        $authorMetadata->save();

        return false;
    }

    /**
     * One entry per non-empty line, trimmed.
     */
    private static function splitLines(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $value)),
            fn ($line) => $line !== ''
        ));
    }

    /**
     * Provide a name for this plugin.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.codecheck.displayName');
    }

    /**
     * Provide a description for this plugin.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.codecheck.description');
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     * Registers the install migration with OJS's plugin installer mechanism.
     */
    public function getInstallMigration(): CodecheckSchemaMigration
    {
        return new CodecheckSchemaMigration();
    }

    /**
     * Add a settings action to the plugin's entry in the plugins list.
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /**
     * Load a form when the `settings` button is clicked and save it when the user saves it.
     */
    public function manage($args, $request): JSONMessage
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    public function setEnabled($enabled, $contextId = null)
    {
        $result = parent::setEnabled($enabled, $contextId);

        if ($enabled) {
            // Single entry point — install migration calls upgrade migrations internally.
            $this->getInstallMigration()->up();
        }

        return $result;
    }

    /**
     * Drop all CODECHECK tables and recreate them from scratch.
     *
     * This is a deliberate, admin-triggered destructive action exposed via
     * the plugin settings UI ("Clear / Reset DB"). It is intentionally kept
     * separate from the migration system, which never drops tables.
     */
    public function resetSchema(): void
    {
        // Drop in reverse dependency order — codecheck_status references codecheck_metadata.
        DBSchema::dropIfExists('codecheck_status');
        DBSchema::dropIfExists('codecheck_orcid_tokens');
        DBSchema::dropIfExists('codecheck_issue_labels');
        DBSchema::dropIfExists('codecheck_metadata');

        // Recreate everything fresh via the install migration.
        $this->getInstallMigration()->up();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}