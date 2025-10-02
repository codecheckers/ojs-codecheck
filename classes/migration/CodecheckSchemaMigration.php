<?php
/**
 * @file CodecheckSchemaMigration.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckSchemaMigration
 * @brief Describe database table structures for CODECHECK plugin.
 */

namespace APP\plugins\generic\codecheck\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CodecheckSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            if (Schema::hasTable('codecheck_metadata')) {
                error_log("CODECHECK MIGRATION: Table already exists, skipping creation");
                return;
            }
            
            // Create the metadata table
            Schema::create('codecheck_metadata', function (Blueprint $table) {
                $table->bigInteger('submission_id')->primary();
                $table->boolean('opt_in')->default(false);
                $table->string('code_repository', 500)->nullable();
                $table->string('data_repository', 500)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('submission_id');
            });
            
            // Create CODECHECK genres for all contexts
            $this->createCodecheckGenres();
            
        } catch (Exception $e) {
            error_log("CODECHECK MIGRATION ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    private function createCodecheckGenres(): void
    {
        $contextDao = \APP\core\Application::getContextDAO();
        $genreDao = \PKP\submission\DAO::getDAO('GenreDAO');
        
        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            // Create CODECHECK README genre
            $readmeGenre = $genreDao->newDataObject();
            $readmeGenre->setContextId($context->getId());
            $readmeGenre->setName('CODECHECK README', 'en');
            $readmeGenre->setDesignation('codecheck_readme');
            $readmeGenre->setCategory(GENRE_CATEGORY_SUPPLEMENTARY);
            $readmeGenre->setSupplementary(true);
            $readmeGenre->setRequired(false);
            $readmeGenre->setSortOrder(100);
            $genreDao->insertObject($readmeGenre);
            
            // Create codecheck.yml genre
            $ymlGenre = $genreDao->newDataObject();
            $ymlGenre->setContextId($context->getId());
            $ymlGenre->setName('codecheck.yml', 'en');
            $ymlGenre->setDesignation('codecheck_yml');
            $ymlGenre->setCategory(GENRE_CATEGORY_SUPPLEMENTARY);
            $ymlGenre->setSupplementary(true);
            $ymlGenre->setRequired(false);
            $ymlGenre->setSortOrder(101);
            $genreDao->insertObject($ymlGenre);
            
            // Create LICENSE genre
            $licenseGenre = $genreDao->newDataObject();
            $licenseGenre->setContextId($context->getId());
            $licenseGenre->setName('CODECHECK LICENSE', 'en');
            $licenseGenre->setDesignation('codecheck_license');
            $licenseGenre->setCategory(GENRE_CATEGORY_SUPPLEMENTARY);
            $licenseGenre->setSupplementary(true);
            $licenseGenre->setRequired(false);
            $licenseGenre->setSortOrder(102);
            $genreDao->insertObject($licenseGenre);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('codecheck_metadata');
    }
}