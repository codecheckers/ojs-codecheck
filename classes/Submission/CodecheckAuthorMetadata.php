<?php
/**
 * @file classes/Submission/CodecheckAuthorMetadata.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckAuthorMetadata
 * @brief Writes what the author entered in the submission wizard into the
 *        CODECHECK record the codechecker later edits.
 *
 * There is one list of repositories and one manifest per submission, not one
 * per role. The author's entries are marked `providedByAuthor` so the workflow
 * form can label them and withhold the delete control; a codechecker may edit
 * or hide them, and may add entries of their own.
 *
 * Saving is a merge, not an overwrite:
 *
 * - an entry the author still lists keeps whatever the codechecker did to it,
 *   matched on URL for repositories and on file name for the manifest;
 * - an entry the author has removed since the last save goes too, but only if
 *   it was theirs — entries the codechecker added are never touched;
 * - the author's ordering is preserved, with codechecker entries after.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;

class CodecheckAuthorMetadata
{
    private int $submissionId;
    private ?array $repositories = null;
    private ?array $manifestFiles = null;

    public function __construct(int $submissionId)
    {
        $this->submissionId = $submissionId;
    }

    /**
     * @param string[] $urls one repository URL per entry
     */
    public function setRepositories(array $urls): void
    {
        $this->repositories = $urls;
    }

    /**
     * @param string[] $files one expected output file per entry
     */
    public function setManifest(array $files): void
    {
        $this->manifestFiles = $files;
    }

    public function save(): void
    {
        $existing = DB::table('codecheck_metadata')
            ->where('submission_id', $this->submissionId)
            ->first();

        $update = [];

        if ($this->repositories !== null) {
            $repositoryData = json_decode($existing->repository ?? '', true);
            $repositoryData = is_array($repositoryData) ? $repositoryData : [];
            $current = is_array($repositoryData['repositories'] ?? null)
                ? $repositoryData['repositories']
                : [];

            $repositoryData['repositories'] = $this->merge(
                $current,
                $this->repositories,
                'url',
                fn ($url) => ['url' => $url, 'hidden' => false, 'providedByAuthor' => true]
            );
            $repositoryData['repoWithCodecheckYaml'] = $repositoryData['repoWithCodecheckYaml'] ?? null;

            $update['repository'] = json_encode($repositoryData);
        }

        if ($this->manifestFiles !== null) {
            $current = json_decode($existing->manifest ?? '', true);
            $current = is_array($current) ? $current : [];

            $update['manifest'] = json_encode($this->merge(
                $current,
                $this->manifestFiles,
                'file',
                fn ($file) => ['file' => $file, 'comment' => '', 'hidden' => false, 'providedByAuthor' => true]
            ));
        }

        if (!$update) {
            return;
        }

        $update['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $this->submissionId)
                ->update($update);
        } else {
            $update['submission_id'] = $this->submissionId;
            $update['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($update);
        }

        CodecheckLogger::debug(
            'Saved author-provided CODECHECK metadata for submission #' . $this->submissionId
            . ' (' . implode(', ', array_keys($update)) . ')'
        );
    }

    /**
     * Reconcile the author's current list with what is already stored.
     *
     * @param array $stored existing entries, author's and codechecker's alike
     * @param string[] $submitted the author's list, in their order
     * @param string $key the field entries are identified by
     * @param callable $make builds a new entry from a submitted value
     */
    private function merge(array $stored, array $submitted, string $key, callable $make): array
    {
        $byKey = [];
        foreach ($stored as $entry) {
            if (is_array($entry) && isset($entry[$key])) {
                $byKey[$entry[$key]] = $entry;
            }
        }

        $merged = [];
        foreach ($submitted as $value) {
            if (isset($byKey[$value])) {
                // Keep the codechecker's edits, comment and hidden state.
                $entry = $byKey[$value];
                $entry['providedByAuthor'] = true;
                $merged[] = $entry;
                continue;
            }

            $merged[] = $make($value);
        }

        $submittedValues = array_flip($submitted);
        foreach ($stored as $entry) {
            if (!is_array($entry) || !isset($entry[$key])) {
                continue;
            }
            // Entries the codechecker added stay; the author's own removals apply.
            if (empty($entry['providedByAuthor']) && !isset($submittedValues[$entry[$key]])) {
                $merged[] = $entry;
            }
        }

        return $merged;
    }
}
