<?php

/**
 * @file classes/migration/install/CodecheckSchemaMigration.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckSchemaMigration
 * @brief Create all CODECHECK database tables on fresh install.
 *        Also calls all upgrade migrations so that fresh installs
 *        and existing installs both end up at the same schema state.
 *
 * Upgrade migrations are called at the end of runUp() in the order
 * they are listed. This order matters — later migrations may depend
 * on changes made by earlier ones.
 *
 * Upgrade scripts can do more than add columns. They may also:
 * - Rename columns or tables
 * - Migrate or transform existing data values
 * - Insert default content (e.g. initial settings or lookup data)
 * - Remove columns, tables, or fields no longer in use
 */

namespace APP\plugins\generic\codecheck\classes\migration\install;

use APP\plugins\generic\codecheck\classes\migration\CodecheckMigration;
use APP\plugins\generic\codecheck\classes\migration\upgrade\I94_AddMissingColumns;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CodecheckSchemaMigration extends CodecheckMigration
{
    protected function runUp(): void
    {
        // codecheck_metadata — core CODECHECK data per submission
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
                $table->string('issue', 500)->default(json_encode(['url' => null, 'number' => null, 'labelsSelected' => []]));
                $table->timestamp('check_time')->nullable();
                $table->text('summary')->nullable();
                $table->string('report', 500)->nullable();
                $table->text('additional_content')->nullable();
                $table->timestamps();
                $table->index('submission_id');
            });
        }

        // codecheck_orcid_tokens — ORCID OAuth tokens per submission/codechecker
        if (!Schema::hasTable('codecheck_orcid_tokens')) {
            Schema::create('codecheck_orcid_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('submission_id')->unsigned();
                $table->string('orcid_id', 20)->nullable();
                $table->string('access_token', 255)->nullable();
                $table->string('refresh_token', 255)->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('put_code', 50)->nullable();
                $table->enum('deposit_status', ['pending', 'success', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('deposited_at')->nullable();
                $table->timestamps();
                $table->index('submission_id');
                $table->index(['submission_id', 'orcid_id']);
            });
        }

        // codecheck_issue_labels — cached GitHub issue labels
        if (!Schema::hasTable('codecheck_issue_labels')) {
            Schema::create('codecheck_issue_labels', function (Blueprint $table) {
                $table->string('label', 200)->default('');
                $table->string('labels_last_updated', 100)->default(date('Y-m-d H:i:s'));
            });
        }

        // codecheck_status — CODECHECK status history per submission
        if (!Schema::hasTable('codecheck_status')) {
            Schema::create('codecheck_status', function (Blueprint $table) {
                $table->bigInteger('status_id')->autoIncrement()->primary();
                $table->bigInteger('submission_id');
                $table->foreign('submission_id', 'codecheck_status_metadata')
                    ->references('submission_id')
                    ->on('codecheck_metadata')
                    ->onDelete('cascade');
                $table->string('status', 300);
                $table->timestamp('timestamp');
                $table->bigInteger('user_id');
                $table->timestamps();
                $table->index('status_id');
            });
        }

        $this->createCodecheckGenres();

        // Run upgrade migrations in order — each is idempotent so safe to run
        // on both fresh installs and existing ones. Add new migrations here.
        (new I94_AddMissingColumns())->up();
    }

    /**
     * Create the codecheck.yml genre for all existing journal contexts.
     * Skips contexts that already have it.
     */
    private function createCodecheckGenres(): void
    {
        $contextDao = \APP\core\Application::getContextDAO();
        $genreDao = \PKP\db\DAORegistry::getDAO('GenreDAO');

        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $existingGenres = $genreDao->getByContextId($context->getId());
            $ymlExists = false;

            while ($genre = $existingGenres->next()) {
                // Compare against every stored locale rather than
                // getLocalizedName(): that resolves the locale through the
                // current request context, which does not exist when the
                // migration runs from the command line, and it would not match
                // the 'en' name written below on a journal whose primary locale
                // is something else — creating a duplicate genre each time.
                $names = $genre->getData('name');
                $names = is_array($names) ? $names : [$names];

                if (in_array('codecheck.yml', $names, true)) {
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
}