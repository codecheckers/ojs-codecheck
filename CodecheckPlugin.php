<?php
namespace APP\plugins\generic\codecheck;

use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\migration\CodecheckSchemaMigration;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\components\forms\FieldOptions;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use APP\plugins\generic\codecheck\controllers\CodecheckAPIHandler;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use PKP\security\Role;

class CodecheckPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        error_log('[CodecheckPlugin] register() called, path=' . $path);

        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $this->addAssets();

            $articleDetails = new ArticleDetails($this);
            Hook::add('Templates::Article::Details', $articleDetails->addCodecheckInfo(...));

            Hook::add('Schema::get::submission', $this->addToSubmissionSchema(...));
            Hook::add('Form::config::before', $this->addToSubmissionForm(...));
            Hook::add('Form::config::before', $this->addCodecheckFileOptions(...));
            Hook::add('Submission::edit', $this->saveSubmissionData(...));
            // Add hook for the Template Manager
            Hook::add('TemplateManager::display', $this->callbackTemplateManagerDisplay(...));
        }

        // Add hook for Ajax API calls
        Hook::add('APIHandler::endpoints::users', function(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool {
        
            // This allow to add a route on fly without defining a api controller
            // Through this allow quick add/modify routes, it's better to use
            // controller based appraoch which is more structured and understandable
            $apiHandler->addRoute(
                'GET',
                'codecheck',
                function (IlluminateRequest $request): JsonResponse {
                    return response()->json([
                        'message' => 'A new codecheck route added successfully on fly',
                    ], Response::HTTP_OK);
                },
                'test.onfly',
                [
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_SUB_EDITOR,
                ],
                // Optional param to define a set to Authorization Policies for route
                // new class implements HasAuthorizationPolicy
                // {
                //     public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
                //     {
                //         return [
                //             new \PKP\security\authorization\ContextAccessPolicy($request, $roleAssignments)
                //         ];
                //     }
                // }
            );
            
            return Hook::CONTINUE;
        });

        return $success;
    }

    public function setupAPIHandler($hookName, $params)
    {
        $route = $params[0];
        $apiRoute = $params[1];

        error_log("[CodecheckPlugin] LoadHandler called for: $route, $apiRoute");

        if ($route === 'codecheck_api') {
            $handler = new CodecheckAPIHandler($apiRoute, file_get_contents('php://input'));
            $handler->serveApiRoute();
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
            'codecheck-frontend-styles',
            $cssUrl,
            ['contexts' => ['frontend']]
        );
    }

    public function callbackTemplateManagerDisplay($hookName, $args): bool
    {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        
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
                    ]
                ]);
            }
        }
        
        return false;
    }

    /**
     * Add CODECHECK file components to submission file forms
     */
    public function addCodecheckFileOptions(string $hookName, \PKP\components\forms\FormComponent $form): bool
    {
        if ($form->id === 'submissionFile') {
            $request = \APP\core\Application::get()->getRequest();
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            
            if ($submission && $submission->getData('codecheckOptIn')) {
                // Check if CODECHECK options are already in the form
                foreach ($form->fields as $field) {
                    if ($field instanceof \PKP\components\forms\FieldOptions && $field->name === 'genreId') {
                        // Check if CODECHECK options already exist
                        $hasCodecheckOptions = false;
                        foreach ($field->options as $option) {
                            if (str_contains($option['label'], 'CODECHECK') || str_contains($option['label'], 'codecheck')) {
                                $hasCodecheckOptions = true;
                                break;
                            }
                        }
                        
                        if ($hasCodecheckOptions) {
                            return false;
                        }
                        
                        $this->createCodecheckGenres();
                        
                        $context = $request->getContext();
                        $genreDao = \PKP\db\DAORegistry::getDAO('GenreDAO');
                        $genres = $genreDao->getByContextId($context->getId());
                        
                        $codecheckGenres = [];
                        while ($genre = $genres->next()) {
                            $name = $genre->getLocalizedName();
                            if (is_array($name)) {
                                $name = $name['en'] ?? $name[array_key_first($name)] ?? '';
                            }
                            
                            if (str_contains($name, 'CODECHECK') || str_contains($name, 'codecheck')) {
                                $codecheckGenres[] = [
                                    'value' => $genre->getId(),
                                    'label' => $name
                                ];
                            }
                        }
                        
                        if (!empty($codecheckGenres)) {
                            $currentOptions = $field->options ?? [];
                            $field->options = array_merge($currentOptions, $codecheckGenres);
                        }
                        break;
                    }
                }
            }
        }
        return false;
    }

    public function createCodecheckGenres(): void
    {
        static $genresCreated = false;
        
        if ($genresCreated) {
            return;
        }
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return;
        }
        
        $genreDao = \PKP\db\DAORegistry::getDAO('GenreDAO');
        
        $checkSql = "SELECT COUNT(*) as count FROM genre_settings WHERE setting_value = 'codecheck.yml'";
        $result = $genreDao->retrieve($checkSql);
        $row = $result->current();

        if ($row && $row->count > 0) {
            $genresCreated = true;
            return;
        }
        
        try {
            $ymlGenre = $genreDao->newDataObject();
            $ymlGenre->setContextId($context->getId());
            $ymlGenre->setName('codecheck.yml', 'en');
            $ymlGenre->setCategory(1);
            $ymlGenre->setSupplementary(true);
            $ymlGenre->setRequired(false);
            $ymlGenre->setSequence(98);
            $genreDao->insertObject($ymlGenre);
            
            $genresCreated = true;
        } catch (Exception $e) {
            error_log("CODECHECK: Error creating genres: " . $e->getMessage());
        }
    }
    
    public function addToSubmissionSchema(string $hookName, array $args): bool
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
        
        $schema->properties->codeRepository = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];
        
        $schema->properties->dataRepository = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];
        
        $schema->properties->manifestFiles = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];
        
        $schema->properties->dataAvailabilityStatement = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable']
        ];
        
        return false;
    }

    public function addToSubmissionForm(string $hookName, \PKP\components\forms\FormComponent $form): bool
    {
        if ($form->id === 'submitStart' || $form->id === 'submissionStart' || str_contains($form->id, 'start')) {
            $form->addField(new FieldOptions('codecheckOptIn', [
                'label' => 'CODECHECK',
                'type' => 'checkbox',
                'options' => [
                    ['value' => 1, 'label' => 'Yes, I want my paper to be codechecked. See: <a href="https://codecheck.org.uk/" target="_blank">CODECHECK</a>']
                ],
                'value' => false,
                'groupId' => 'default'
            ]));
            
            return false;
        }
        
        if ($form->id === 'titleAbstract') {
            $request = Application::get()->getRequest();
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            
            if ($submission && $submission->getData('codecheckOptIn')) {
                $form->addField(new \PKP\components\forms\FieldTextarea('retrieveReserveCertificateIdentifier', [
                    'label' => 'Certificate Identifier',
                    'description' => 'CODECHECK Certificate ID, Venue Type and Venue Name',
                    'groupId' => 'default',
                    'isRequired' => true,
                    'rows' => 3,
                    'value' => $submission->getData('retrieveReserveCertificateIdentifier'),
                ]));

                $form->addField(new \PKP\components\forms\FieldTextarea('codeRepository', [
                    'label' => 'Code Repository URLs',
                    'description' => 'Link(s) to your code repository (GitHub, GitLab, etc.)',
                    'groupId' => 'default',
                    'rows' => 3,
                    'value' => $submission->getData('codeRepository'),
                ]));

                $form->addField(new \PKP\components\forms\FieldTextarea('dataRepository', [
                    'label' => 'Data Repository URLs', 
                    'description' => 'Link(s) to your data repository (Zenodo, OSF, etc.) - optional',
                    'groupId' => 'default',
                    'rows' => 3,
                    'value' => $submission->getData('dataRepository'),
                ]));
                
                $form->addField(new \PKP\components\forms\FieldTextarea('manifestFiles', [
                    'label' => 'Expected Output Files',
                    'description' => 'List the main figures, tables, and results your code should produce.',
                    'groupId' => 'default',
                    'rows' => 6,
                    'isRequired' => true,
                    'value' => $submission->getData('manifestFiles'),
                ]));  

                $form->addField(new \PKP\components\forms\FieldRichTextarea('dataAvailabilityStatement', [
                    'label' => 'Data and Software Availability',
                    'description' => 'Copy from your manuscript\'s data availability section',
                    'groupId' => 'default', 
                    'rows' => 4,
                    'value' => $submission->getData('dataAvailabilityStatement'),
                ]));
            }
        }
        
        return false;
    }

    public function saveSubmissionData(string $hookName, array $params): bool
    {
        $newSubmission = $params[0];
        $params_array = $params[2];
        
        $fields = [
            'codecheckOptIn',
            'retrieveReserveCertificateIdentifier',
            'codeRepository',
            'dataRepository',
            'manifestFiles',
            'dataAvailabilityStatement',
        ];
        
        foreach ($fields as $field) {
            if (isset($params_array[$field])) {
                $newSubmission->setData($field, $params_array[$field]);
            }
        }
        
        return false;
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.codecheck.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.codecheck.description');
    }

    public function getActions($request, $actionArgs): array
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    public function setEnabled($enabled, $contextId = null)
    {
        $result = parent::setEnabled($enabled, $contextId);
        
        if ($enabled) {
            try {
                $migration = new CodecheckSchemaMigration();
                $migration->up();
            } catch (Exception $e) {
                error_log("CODECHECK: " . $e->getMessage());
            }
        }
        
        return $result;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}