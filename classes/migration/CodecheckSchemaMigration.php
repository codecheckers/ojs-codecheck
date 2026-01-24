<?php
namespace APP\plugins\generic\codecheck\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CodecheckSchemaMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('codecheck_metadata')) {
            Schema::create('codecheck_metadata', function (Blueprint $table) {
                $table->bigInteger('submission_id')->primary();
                $table->string('version', 50)->default('latest');
                $table->string('publication_type', 50)->default('doi');
                $table->text('manifest')->nullable();
                $table->string('repository', 500)->nullable();
                $table->text('source')->nullable();
                $table->text('codecheckers')->nullable();
                $table->string('certificate', 100)->nullable();
                $table->timestamp('check_time')->nullable();
                $table->text('summary')->nullable();
                $table->string('report', 500)->nullable();
                $table->text('additional_content')->nullable();
                $table->timestamps();
                $table->index('submission_id');
            });
        }
        
        $this->createCodecheckGenres();
    }

    private function createCodecheckGenres(): void
    {
        $contextDao = \APP\core\Application::getContextDAO();
        $genreDao = \PKP\db\DAORegistry::getDAO('GenreDAO');
        
        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $existingGenres = $genreDao->getByContextId($context->getId());
            $ymlExists = false;
            
            while ($genre = $existingGenres->next()) {
                if ($genre->getLocalizedName() === 'codecheck.yml') {
                    $ymlExists = true;
                    break;
                }
            }
            
            if (!$ymlExists) {
                $ymlGenre = $genreDao->newDataObject();
                $ymlGenre->setContextId($context->getId());
                $ymlGenre->setName('codecheck.yml', 'en');
                $ymlGenre->setCategory(GENRE_CATEGORY_SUPPLEMENTARY);
                $ymlGenre->setSupplementary(true);
                $ymlGenre->setRequired(false);
                $ymlGenre->setSequence(101);
                $genreDao->insertObject($ymlGenre);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('codecheck_metadata');
    }
}