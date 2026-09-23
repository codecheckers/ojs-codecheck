<?php

namespace APP\plugins\generic\codecheck;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiController;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleAvailability;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\migration\install\CodecheckSchemaMigration;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidAuthHandler;
use APP\plugins\generic\codecheck\classes\Orcid\OrcidDepositService;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\classes\Submission\AvailabilityStatementField;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckAuthorMetadata;
use APP\plugins\generic\codecheck\classes\Submission\Schema;
use APP\plugins\generic\codecheck\classes\Submission\SubmissionWizardHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckPublicationValidator;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckRegisterDepositService;
use APP\plugins\generic\codecheck\controllers\page\CodecheckPageHandler;
use APP\template\TemplateManager;
use PKP\components\forms\FieldOptions;
use PKP\core\JSONMessage;
use PKP\core\Request;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CodecheckPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        // Outside the enabled check on purpose. Creating a journal is a
        // site-scoped request, so `getEnabled()` there reads the *site* row,
        // which a per-journal install does not have — registering this inside
        // the block below means it never attaches at all (#177). PKP registers
        // its own equivalent, `installContextSpecificSettings`, outside any
        // enabled check for the same reason.
        if ($success) {
            Hook::add('Context::add', $this->writeDefaultSettingsForNewContext(...));
        }

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
        CodecheckLogger::debug('Validating Publication!');
        $errors = &$args[0];
        // The hook hands over the submission being published. Passing it on
        // matters: publishing goes through the REST API, where there is no page
        // handler to ask for the authorized submission.
        $submission = $args[2] ?? null;
        $codecheckPublicationValidator = new CodecheckPublicationValidator($this, $submission);

        $validationErrors = $codecheckPublicationValidator->validatePublication();

        if (is_array($validationErrors)) {
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
     * @param array $args [0] => Publication $newPublication, [1] => Publication $publication, [2] => Submission $submission
     */
    public function depositToRegister(string $hookName, array $args): bool
    {
        [$newPublication, $publication, $submission] = $args;

        if (!$submission->getData('codecheckOptIn')) {
            return false;
        }

        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            CodecheckLogger::warning('No context while publishing submission #' . $submission->getId() . '; skipping the register deposit.');
            return false;
        }

        if (!$this->isRegisterDepositEnabled($context->getId())) {
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
            array_map(fn ($status) => __($status), Constants::CODECHECK_STATUSES)
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
        // The submission comes from the hook, not from a lookup: publishing goes
        // through the REST API, where reaching for it another way is the trap
        // documented in CLAUDE.md. Same shape as depositToRegister() above.
        [$newPublication, $publication, $submission] = $args;

        if (!$submission) {
            return false;
        }
        if (!$submission->getData('codecheckOptIn')) {
            return false;
        }

        // The context can be null on that same REST path, and PKP swallows what
        // a hook throws as "failed to handle the hook" — so publishing would
        // break with nothing to read (#175).
        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            CodecheckLogger::warning('No context while publishing submission #' . $submission->getId() . '; skipping the ORCID deposit.');
            return false;
        }

        if (!$this->getSetting($context->getId(), Constants::ORCID_ENABLED)) {
            return false;
        }

        try {
            $depositService = new OrcidDepositService($this);
            $results = $depositService->depositForSubmission($submission->getId());
            foreach ($results as $result) {
                // A deposit that failed before it reached anyone's record carries
                // no ORCID iD — reading one unconditionally warned on every
                // publish for a journal that enabled ORCID without credentials.
                $who = $result['orcidId'] ?? 'an unidentified codechecker';

                if (($result['status'] ?? null) === 'success') {
                    CodecheckLogger::info('ORCID deposited for ' . $who . ' put-code=' . ($result['putCode'] ?? '?'));
                } else {
                    CodecheckLogger::error('ORCID deposit failed for ' . $who . ': ' . ($result['error'] ?? 'unknown'));
                }
            }
        } catch (\Throwable $e) {
            CodecheckLogger::error('ORCID deposit exception on publish: ' . $e->getMessage());
        }

        return false;
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
        $router->registerPluginApiControllers([new CodecheckApiController($this)]);

        return Hook::CONTINUE;
    }

    /**
     * The config file specification versions this journal offers in the metadata
     * form. Unset or empty means the default: the current stable specification.
     *
     * Lives on the plugin rather than the API controller because the setting it
     * reads belongs to the journal, not to a request.
     */
    public function getEnabledConfigVersions(?int $contextId): array
    {
        // Deliberately not in `CODECHECK_SETTING_DEFAULTS`: a recorded default
        // is written into a row, and the writers never reconcile, so a journal
        // enabled today would keep being offered 1.0 after 1.1 became the
        // stable specification. This default is expected to change, so it is
        // resolved here, at the one place that reads the setting.
        //
        // Narrowing to nothing is the same answer as nothing stored: a journal
        // offering no version at all would leave the metadata form with nothing
        // to record a check against.
        $enabled = $contextId === null ? [] : self::narrowConfigVersions(
            (array) $this->getSetting($contextId, Constants::CODECHECK_ENABLED_CONFIG_VERSIONS)
        );

        return $enabled ?: Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS;
    }

    /**
     * The versions of $versions the plugin still knows, in the plugin's own
     * order. One rule, applied at both boundaries: the settings form narrows
     * what it stores, and `getEnabledConfigVersions()` narrows what it reads,
     * so a version dropped from the plugin cannot reappear from a row written
     * before it was dropped.
     */
    public static function narrowConfigVersions(array $versions): array
    {
        return array_values(array_intersect(Constants::CODECHECK_CONFIG_VERSIONS, $versions));
    }

    /**
     * Routes the PKP controller serves instead of the hand-rolled handler.
     *
     * Stage 0 of the migration in `plan-50-p2-api-controller.md`: one endpoint,
     * to check the migration's assumptions against a running instance.
     */



    /**
     * Declare the handler function to process the actual page PATH.
     */
    public function setCodecheckPageHandler($hookName, $args)
    {
        $page = &$args[0];
        $op = &$args[1];
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
        if (!$context) {
            return false;
        }

        $contextId = $context->getId();

        // Every dashboard view, not just /editorial: `mySubmissions` and
        // `reviewAssignments` render the same Pinia store and the same bundle,
        // so a config injected for one op only leaves the JS fallback deciding
        // on the other two — which is a second statement of the default, and
        // the opposite one for a journal that switched the column off.
        if ($request->getRequestedPage() == 'dashboard') {
            $dashboardConfig = json_encode([
                'showDashboardColumn' => (bool) $this->getSettingWithDefault($contextId, Constants::CODECHECK_SHOW_DASHBOARD_COLUMN),
                'codecheckMode' => $this->getSetting($contextId, Constants::CODECHECK_MODE) ?? 'opt-in',
            ]);

            $templateMgr->addJavaScript(
                'codecheck-dashboard-config',
                'window.codecheckDashboardConfig = ' . $dashboardConfig . ';',
                [
                    'inline' => true,
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
                        'dataAvailabilityStatement' => $publication ? $publication->getData('dataAvailabilityStatement') : null,
                    ]
                ]);
            }
        }

        // ----------------------------------------------------------------
        // Reviewer page — inject submission data + ORCID config for Vue
        // ----------------------------------------------------------------
        if ($request->getRequestedPage() == 'reviewer' && $request->getRequestedOp() == 'submission') {
            $requestArgs = $request->getRequestedArgs();
            $submissionId = (int) ($requestArgs[0] ?? 0);

            if ($submissionId) {
                $context = $request->getContext();
                $contextId = $context->getId();
                $submission = Repo::submission()->get($submissionId);

                if ($submission && $submission->getData('codecheckOptIn')) {
                    $orcidAuthUrl = $request->getBaseUrl() . '/index.php/' . $context->getPath() . '/codecheck/orcid/startAuth';

                    $reviewerData = json_encode([
                        'submissionId' => $submission->getId(),
                        'codecheckOptIn' => true,
                        'orcid' => [
                            'enabled' => (bool) $this->getSetting($contextId, Constants::ORCID_ENABLED),
                            'authUrl' => $orcidAuthUrl,
                            'apiType' => $this->getSetting($contextId, Constants::ORCID_API_TYPE) ?? Constants::ORCID_API_TYPE_SANDBOX,
                            'apiBaseUrl' => $request->getBaseUrl() . '/index.php/' . $context->getPath(),
                        ],
                    ]);

                    $templateMgr->addJavaScript(
                        'codecheck-reviewer-data',
                        'window.codecheckReviewerData = ' . $reviewerData . ';',
                        [
                            'inline' => true,
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
            $codecheckMandatory = false;
            $codecheckDescription = __('plugins.generic.codecheck.optIn.description', [
                'codecheckLink' => "<a href='{$this->getUrlPageRoute('codecheck')}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . '</a>'
            ]);

            if ($codecheckMode == 'opt-out') {
                $checkboxValue = true;
            } elseif ($codecheckMode == 'mandatory') {
                $checkboxValue = true;
                $codecheckMandatory = true;
                $codecheckDescription = __('plugins.generic.codecheck.mandatory.description', [
                    'codecheckLink' => "<a href='{$this->getUrlPageRoute('codecheck')}/info' target='_blank'>" . __('plugins.generic.codecheck.displayName') . '</a>'
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
                'value' => $checkboxValue,
                'groupId' => 'default'
            ]));
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
        // The hook is `Submission::validate`, raised as
        // [&$errors, $submission, $props, ...] — so a refusal is added here and
        // OJS abandons the save. See the repository check below.
        $errors = &$params[0];
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

        // An address that cannot be a repository link is refused, not dropped:
        // the author posts what they typed and is told no, the way PKP's own
        // fields work. Dropping it here would be indistinguishable from the
        // author removing the entry, and `CodecheckAuthorMetadata::merge()`
        // would then delete the address already on file (issue #170).
        //
        // Nothing is written in that case — this hook runs during validation,
        // so a save that OJS is about to abandon must not have happened.
        $unusable = $authorMetadata->introducedUnusableRepositories();
        if ($unusable !== []) {
            $errors['repositories'] = [
                __('plugins.generic.codecheck.repositories.invalidUrl', [
                    'repository' => implode(', ', $unusable),
                ]),
            ];

            return false;
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
        // The parent takes only $enabled and derives the context itself, so
        // passing $contextId there would be silently discarded — the two writes
        // would then be able to land in different journals.
        $result = parent::setEnabled($enabled);

        if ($enabled) {
            // Single entry point — install migration calls upgrade migrations internally.
            $this->getInstallMigration()->up();
            $this->writeDefaultSettings($this->getCurrentContextId());
        }

        return $result;
    }

    /**
     * Give a journal a stored row for every setting in
     * `Constants::CODECHECK_SETTING_DEFAULTS`, so "unset" is not a third state
     * anything has to have an opinion about (#177).
     *
     * Only writes what is not there, so a journal that switched something off
     * keeps it off through a disable and re-enable.
     *
     * Called from three places, because a journal can acquire this plugin in
     * three ways: enabling it (`setEnabled()`), being created while it is
     * already enabled (`Context::add`), and existing before this version
     * (the install migration). `getSettingWithDefault()` still resolves the
     * default for anything these miss — the row is for visibility, the reader
     * is the guarantee.
     */
    public function writeDefaultSettings(?int $contextId): void
    {
        // Enabling the plugin from the site-wide list has no journal to write
        // for; each journal is written when it enables it, or on creation.
        if ($contextId === null) {
            return;
        }

        // Read every setting before writing any of them: `updateSetting()`
        // forgets the cache entry holding this plugin's whole settings array,
        // so an interleaved loop re-queries on each iteration.
        $missing = array_filter(
            Constants::CODECHECK_SETTING_DEFAULTS,
            fn ($default, $name) => $this->getSetting($contextId, $name) === null,
            ARRAY_FILTER_USE_BOTH
        );

        foreach ($missing as $name => $default) {
            $this->updateSetting($contextId, $name, $default);
        }
    }

    /**
     * Writes the defaults for a journal created while the plugin was already
     * enabled, which never calls `setEnabled()` (#177).
     */
    public function writeDefaultSettingsForNewContext(string $hookName, array $args): bool
    {
        $context = $args[0] ?? null;

        if ($context) {
            $this->writeDefaultSettings((int) $context->getId());
        }

        return false;
    }

    /**
     * A setting resolved against its recorded default.
     *
     * The one reader, so that no two callers can disagree about what a missing
     * row means — the settings form and the register deposit disagreed, which
     * is #177. A null context resolves to the default too: "no journal to ask"
     * and "nothing stored" are the same answer, and answering anything else
     * would encode the opposite of the declared default.
     *
     * **Only `null` counts as unset here.** A switched-off boolean is stored,
     * and read back, as `false` — a legitimate value that `empty()` cannot tell
     * apart from a missing row, so an "empty means unset" rule here would
     * switch every default-on setting back on. A setting that must also fall
     * back on an empty value says so where the emptiness means something:
     * `getEnabledConfigVersions()` does, after narrowing the list, and
     * `CODECHECK_AVAILABILITY_STATEMENT_HEADING` where it is read, its default
     * being a localised string rather than a value a constant can hold.
     */
    public function getSettingWithDefault(?int $contextId, string $name): mixed
    {
        $stored = $contextId === null ? null : $this->getSetting($contextId, $name);

        return $stored ?? (Constants::CODECHECK_SETTING_DEFAULTS[$name] ?? null);
    }

    /** Whether a published article is deposited to the CODECHECK Register. */
    public function isRegisterDepositEnabled(?int $contextId): bool
    {
        return (bool) $this->getSettingWithDefault($contextId, Constants::CODECHECK_REGISTER_DEPOSIT_ENABLED);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}
