<?php
/**
 * @file CodecheckPlugin.php
 */

namespace APP\plugins\generic\codecheck;

use APP\core\Request;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\classes\migration\CodecheckSchemaMigration;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\components\forms\FieldOptions;

class CodecheckPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            // Display CODECHECK information on the article details page
            $articleDetails = new ArticleDetails($this);
            Hook::add('Templates::Article::Details', $articleDetails->addCodecheckInfo(...));

            // REPLACED: Use proper OJS 3.5 Custom Fields approach
            // Extend the submission schema to add CODECHECK fields
            Hook::add('Schema::get::submission', $this->addToSubmissionSchema(...));
            Hook::add('Form::config::before', $this->addToSubmissionForm(...));
            // Add CODECHECK file options to upload forms
            Hook::add('Form::config::before', $this->addCodecheckFileOptions(...));
            // Add this hook for review section display
            Hook::add('Template::SubmissionWizard::Section::Review::Details', $this->addCodecheckReviewDisplay(...));
            // Add hook to save custom field data
            Hook::add('Submission::edit', $this->saveSubmissionData(...));
            Hook::add('Submission::edit::before', $this->debugSubmissionData(...));

            // Hook::add('Form::config::after', $this->validateCodecheckFiles(...));

            // Add CSS/JS assets using proper method
            // $this->addAssets();
        }

        return $success;
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
                        error_log("CODECHECK: Options already exist in form, skipping");
                        return false;
                    }
                    
                    // Create genres if needed (only once)
                    $this->createCodecheckGenres();
                    
                    // Get fresh genres from database
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
                        error_log("CODECHECK: Added " . count($codecheckGenres) . " options to form");
                    }
                    break;
                }
            }
        }
    }
    return false;
}

public function debugSubmissionData(string $hookName, array $params): bool
{
    $params_array = $params[2] ?? [];
    
    if (isset($params_array['codeRepository']) || isset($params_array['manifestFiles'])) {
        error_log("CODECHECK SAVE DEBUG: Data being saved:");
        error_log("CODECHECK SAVE DEBUG: " . print_r(array_intersect_key($params_array, array_flip(['codeRepository', 'dataRepository', 'manifestFiles', 'dataAvailabilityStatement'])), true));
    }
    
    return false;
}

/**
 * Create CODECHECK genres manually
 */
public function createCodecheckGenres(): void
{
    static $genresCreated = false; // Prevent multiple calls in same request
    
    if ($genresCreated) {
        return;
    }
    
    $request = \APP\core\Application::get()->getRequest();
    $context = $request->getContext();
    
    if (!$context) {
        return;
    }
    
    $genreDao = \PKP\db\DAORegistry::getDAO('GenreDAO');
    
    // Check if any CODECHECK genre already exists in database
        $checkSql = "SELECT COUNT(*) as count FROM genre_settings WHERE setting_value = 'codecheck.yml'";
        $result = $genreDao->retrieve($checkSql);
        $row = $result->current();

        if ($row && $row->count > 0) {
            error_log("CODECHECK: codecheck.yml genre already exists");
            $genresCreated = true;
            return;
        }
    
    try {
        // Create in the order you want: yml, README, LICENSE
        $ymlGenre = $genreDao->newDataObject();
        $ymlGenre->setContextId($context->getId());
        $ymlGenre->setName('codecheck.yml', 'en');
        $ymlGenre->setCategory(1);
        $ymlGenre->setSupplementary(true);
        $ymlGenre->setRequired(false);
        $ymlGenre->setSequence(98); // First
        $genreDao->insertObject($ymlGenre);
        
        $genresCreated = true;
        error_log("CODECHECK: Created 3 genres in correct order");
        
    } catch (Exception $e) {
        error_log("CODECHECK: Error creating genres: " . $e->getMessage());
    }
}
    /**
     * Extend the submission entity's schema with CODECHECK properties
     */
    public function addToSubmissionSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        
        $schema->properties->codecheckOptIn = (object) [
            'type' => 'boolean',
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
        $schema->properties->manifestFiles = (object) ['type' => 'string', 'apiSummary' => true, 'validation' => ['nullable']];
        $schema->properties->dataAvailabilityStatement = (object) ['type' => 'string', 'apiSummary' => true, 'validation' => ['nullable']];
        return false;
    }

    /**
     * Add CODECHECK fields to submission forms
     */
    public function addToSubmissionForm(string $hookName, \PKP\components\forms\FormComponent $form): bool
    {
    error_log("CODECHECK: Checking form ID: " . $form->id);

        // Target the submission start form (before the wizard steps)
        if ($form->id === 'submitStart' || $form->id === 'submissionStart' || str_contains($form->id, 'start')) {
            error_log("CODECHECK: Adding CODECHECK checkbox to start form");
            
            // Add the main CODECHECK opt-in checkbox to the initial form
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
        
        // Target submission wizard steps for detailed fields
if ($form->id === 'titleAbstract') {
    $request = \APP\core\Application::get()->getRequest();
    $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    
    if ($submission && $submission->getData('codecheckOptIn')) {
        
        $form->addField(new \PKP\components\forms\FieldText('codeRepository', [
            'label' => 'Code Repository URL',
            'description' => 'Link to your code repository (GitHub, GitLab, etc.)',
            'groupId' => 'default',
            'isRequired' => true,
            'value' => $submission->getData('codeRepository'),
        ]));
        
        $form->addField(new \PKP\components\forms\FieldText('dataRepository', [
            'label' => 'Data Repository URL', 
            'description' => 'Link to your data repository (Zenodo, OSF, etc.) - optional',
            'groupId' => 'default',
            'value' => $submission->getData('dataRepository'),
        ]));
        
        $form->addField(new \PKP\components\forms\FieldRichTextarea('manifestFiles', [
            'label' => 'Expected Output Files',
            'description' => 'List the main figures, tables, and results your code should produce. One file per line with optional comments.',
            'groupId' => 'default',
            'rows' => 6,
            'isRequired' => true,
            'value' => $submission->getData('manifestFiles'),
            'placeholder' => 'figures/figure1.png - Main result visualization
tables/summary_stats.csv - Summary statistics  
results/model_output.txt - Model predictions',
        ]));
        
        $form->addField(new \PKP\components\forms\FieldRichTextarea('dataAvailabilityStatement', [
            'label' => 'Data and Software Availability',
            'description' => 'Copy from your manuscript\'s data availability section, or describe how readers can access your code and data',
            'groupId' => 'default', 
            'rows' => 4,
            'value' => $submission->getData('dataAvailabilityStatement'),
        ]));
        
        error_log("CODECHECK: Added all fields to titleAbstract form");
    }
}
        return false;
    }

public function addCodecheckReviewDisplay(string $hookName, array $args): void
{
    $templateMgr = &$args[1];
    $output =& $args[2];
    
    // Try getting submission directly from request instead of template vars
    $request = \APP\core\Application::get()->getRequest();
    try {
        $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            // Fallback to template vars
            $submission = $templateMgr->getTemplateVars('submission');
        }
    } catch (Exception $e) {
        $submission = $templateMgr->getTemplateVars('submission');
    }
    if ($submission && $submission->getData('codecheckOptIn')) {
        // Debug: Log what data we actually have
        error_log("CODECHECK DEBUG: OptIn: " . var_export($submission->getData('codecheckOptIn'), true));
        error_log("CODECHECK DEBUG: Repository: " . var_export($submission->getData('codeRepository'), true));
        error_log("CODECHECK DEBUG: Manifest: " . var_export($submission->getData('manifestFiles'), true));
        error_log("CODECHECK DEBUG: Data Repo: " . var_export($submission->getData('dataRepository'), true));
        error_log("CODECHECK DEBUG: Data Avail: " . var_export($submission->getData('dataAvailabilityStatement'), true));

        $output .= '<div class="section">
            <h3>CODECHECK Information</h3>
            <div class="fields">
                <div class="field">
                    <div class="label">Status</div>
                    <div class="value">This submission will be codechecked</div>
                </div>';
        
        if ($submission->getData('codeRepository')) {
            $output .= '<div class="field">
                    <div class="label">Code Repository</div>
                    <div class="value">' . htmlspecialchars($submission->getData('codeRepository')) . '</div>
                </div>';
        }
        
        if ($submission->getData('dataRepository')) {
            $output .= '<div class="field">
                    <div class="label">Data Repository</div>
                    <div class="value">' . htmlspecialchars($submission->getData('dataRepository')) . '</div>
                </div>';
        }
        
        if ($submission->getData('manifestFiles')) {
            $output .= '<div class="field">
                    <div class="label">Expected Output Files</div>
                    <div class="value">' . htmlspecialchars($submission->getData('manifestFiles')) . '</div>
                </div>';
        }
        
        if ($submission->getData('dataAvailabilityStatement')) {
            $output .= '<div class="field">
                    <div class="label">Data and Software Availability</div>
                    <div class="value">' . htmlspecialchars($submission->getData('dataAvailabilityStatement')) . '</div>
                </div>';
        }
        
        $output .= '</div></div>';
        
        error_log("CODECHECK: Added review display in Details section");
    }
}

 public function saveSubmissionData(string $hookName, array $params): bool
{
    $newSubmission = $params[0];
    $submission = $params[1]; 
    $params_array = $params[2];
    
    // Check if our fields are in the parameters being saved
    $fields = ['codeRepository', 'dataRepository', 'manifestFiles', 'dataAvailabilityStatement'];
    foreach ($fields as $field) {
        if (isset($params_array[$field])) {
            $newSubmission->setData($field, $params_array[$field]);
            error_log("CODECHECK: Saved $field = " . $params_array[$field]);
        }
    }
    
    return false;
}
    /**
     * Validate CODECHECK files are uploaded before submission
     */
    public function validateCodecheckFiles(string $hookName, array $params): bool
    {
        $form = $params[0];
        
        // Only validate on final submission
        if ($form->id === 'submissionWizard' || str_contains(get_class($form), 'SubmissionSubmit')) {
            $request = \APP\core\Application::get()->getRequest();
            $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
            
            if ($submission && $submission->getData('codecheckOptIn')) {
                $this->checkRequiredCodecheckFiles($submission, $form);
            }
        }
        
        return false;
    }

    private function checkRequiredCodecheckFiles($submission, $form): void
    {
        $submissionFiles = \APP\facades\Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany();
        
        $hasReadme = false;
        $hasCodecheckYml = false;
        $hasLicense = false;
        
        foreach ($submissionFiles as $file) {
            $genre = $file->getGenre();
            if ($genre && in_array($genre->getId(), [999, 998, 997])) {
                switch ($genre->getId()) {
                    case 999: $hasReadme = true; break;
                    case 998: $hasCodecheckYml = true; break;
                    case 997: $hasLicense = true; break;
                }
            }
        }
        
        if (!$hasReadme || !$hasCodecheckYml || !$hasLicense) {
            $form->addError('codecheck', 'Please upload all required CODECHECK files: README, codecheck.yml, and LICENSE');
        }
    }
    // Add this method to your CodecheckPlugin class
public function addToReviewForm(string $hookName, \PKP\components\forms\FormComponent $form): bool
{
    if ($form->id === 'review') {
        $request = \APP\core\Application::get()->getRequest();
        $submission = $request->getRouter()->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        
        if ($submission && $submission->getData('codecheckOptIn')) {
            // Add read-only fields to review form
            $form->addField(new \PKP\components\forms\FieldHTML('codeRepositoryReview', [
                'label' => 'Code Repository URL',
                'description' => $submission->getData('codeRepository'),
            ]));
            
            $form->addField(new \PKP\components\forms\FieldHTML('dataRepositoryReview', [
                'label' => 'Data Repository URL',
                'description' => $submission->getData('dataRepository'),
            ]));
            
            $form->addField(new \PKP\components\forms\FieldHTML('manifestFilesReview', [
                'label' => 'Expected Output Files',
                'description' => $submission->getData('manifestFiles'),
            ]));
            
            $form->addField(new \PKP\components\forms\FieldHTML('dataAvailabilityStatementReview', [
                'label' => 'Data and Software Availability',
                'description' => $submission->getData('dataAvailabilityStatement'),
            ]));
        }
    }
    return false;
}
    /**
     * Add CSS/JS assets using documented method
     */
    // private function addAssets(): void
    // {
    //     $request = \APP\core\Application::get()->getRequest();
    //     $templateMgr = \APP\template\TemplateManager::getManager($request);
        
    //     // Add CSS
    //     $cssUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/codecheck.css';
    //     $templateMgr->addStyleSheet('codecheck-styles', $cssUrl, ['contexts' => 'frontend']);
        
    //     // Add JavaScript  
    //     $jsUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/codecheck-submission.js';
    //     $templateMgr->addJavaScript('codecheck-js', $jsUrl, ['contexts' => 'frontend']);
        
    //     error_log("CODECHECK: Assets added - CSS: $cssUrl, JS: $jsUrl");
    // }

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

    /**
     * @copydoc Plugin::setEnabled()
     */
    public function setEnabled($enabled, $contextId = null)
    {
        $result = parent::setEnabled($enabled, $contextId);
        
        if ($enabled) {
            // Plugin enabled - create table if needed
            try {
                $migration = new CodecheckSchemaMigration();
                $migration->up();
                error_log("CODECHECK PLUGIN: Table created on enable");
            } catch (Exception $e) {
                error_log("CODECHECK PLUGIN: " . $e->getMessage());
            }
        }
        
        return $result;
    }
}

// For backwards compatibility
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\codecheck\CodecheckPlugin', '\CodecheckPlugin');
}