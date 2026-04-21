<?php
namespace APP\plugins\generic\codecheck;

use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\classes\migration\CodecheckSchemaMigration;
use APP\plugins\generic\codecheck\classes\Submission\Schema;
use APP\plugins\generic\codecheck\classes\Submission\SubmissionWizardHandler;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\components\forms\FieldOptions;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiHandler;
use PKP\core\JSONMessage;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\controllers\page\CodecheckPageHandler;

class CodecheckPlugin extends GenericPlugin
{
    private CodecheckSchemaMigration $migration;

    public function register($category, $path, $mainContextId = null): bool
    {
        error_log('[CodecheckPlugin] register() called, path=' . $path);

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
            
        }

        return $success;
    }
    
    /**
     * Setup the `CodecheckApiHandler`
     * 
     * @param string $hookname The name of the hook
     * @param array $args The arguments passed by the hook
     * 
     * @return void
     */
    public function setupAPIHandler(string $hookName, array $args): void
    {
        $request = $args[0];
        $router = $request->getRouter();

        if (!($router instanceof \PKP\core\APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/codecheck')) {
            error_log("[CODECHECK Plugin] Instanciating the CODECHECK APIHandler");
            $apiHandler = new CodecheckApiHandler($this, $request);
            error_log("[CODECHECK Plugin] API request: " . $request->getRequestPath() . "\n");
        }

        if (!isset($apiHandler)) {
            return;
        }

        $router->setHandler($apiHandler);
        exit;
    }

    /**
     * Declare the handler function to process the actual page PATH
     *
     * @param string $hookName The name of the invoked hook
     * @param array $args Hook parameters
     *
     * @return bool Hook handling status
     */
    public function setCodecheckPageHandler($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $page = &$args[0];
        $op = &$args[1];
        $handler = &$args[3];

        
        // Construct a path to look for
        $path = $page;
        if ($op !== 'index') {
            $path .= "/{$op}";
        }
        if ($ops = $request->getRequestedArgs()) {
            $path .= '/' . implode('/', $ops);
        }

        // Check if this is a request for a static page or preview.
        if ($page = 'codecheck' && $op == 'info') {
            // Trick the handler into dealing with it normally
            $page = 'pages';
            $op = 'view';

            // It is -- attach the static pages handler.
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
        
        if ($request->getRequestedOp() == 'workflow') {
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            
            if ($submission) {
                $publication = $submission->getCurrentPublication();
                $templateMgr->setState([
                    'codecheckSubmission' => [
                        'id' => $submission->getId(),
                        'codecheckOptIn' => $submission->getData('codecheckOptIn'),
                        'retrieveReserveCertificateIdentifier' => $submission->getData('retrieveReserveCertificateIdentifier'),
                        'codeRepository' => $submission->getData('codeRepository'),
                        'dataRepository' => $submission->getData('dataRepository'),
                        'manifestFiles' => $submission->getData('manifestFiles'),
                        'dataAvailabilityStatement' => $submission->getData('dataAvailabilityStatement'),
                    ]
                ]);
            }
        }
        
        return false;
    }

    public function getUrlPageRoute(string $page): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            ROUTE_PAGE,
            null,
            $page
        );
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
            error_log("[CODECHECK Settings] Mode: " . $codecheckMode);
            $checkboxValue = false;
            $checkboxDisabled = false;

            if($codecheckMode == 'opt-out') {
                $checkboxValue = true;
            } elseif ($codecheckMode == 'required') {
                $checkboxValue = true;
                $checkboxDisabled = true;
            }

            $form->addField(new FieldOptions('codecheckOptIn', [
                'label' => __('plugins.generic.codecheck.displayName'),
                'type' => 'checkbox',
                'options' => [
                    [
                        'value' => 1, 
                        'label' => __('plugins.generic.codecheck.optIn.description', [
                            'codecheckLink' => "<a href='{$this->getUrlPageRoute("codecheck")}/info' target='_blank'>CODECHECK</a>"
                        ]),
                        'disabled' => $checkboxDisabled,
                    ]
                ],
                'value' => $checkboxValue,
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
        
        if (!$submission) {
            return false;
        }
        
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
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.codecheck.displayName');
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.codecheck.description');
    }

    /**
     * Add a settings action to the plugin's entry in the CODECHECK plugins list.
     *
     * @param Request $request
     * @param array $actionArgs
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /**
     * Load a form when the `settings` button is clicked and
     * save the form when the user saves it.
     *
     * @param array $args
     * @param Request $request
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
            $this->migration = new CodecheckSchemaMigration();
            $this->migration->up();
        }
        
        return $result;
    }

    public function resetSchema(): void
    {
        $this->migration = new CodecheckSchemaMigration();
        $this->migration->down();
        $this->migration->up();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}