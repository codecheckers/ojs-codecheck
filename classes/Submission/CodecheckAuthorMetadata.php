<?php

/**
 * @file classes/Submission/CodecheckAuthorMetadata.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckAuthorMetadata
 *
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

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;

class CodecheckAuthorMetadata
{
    private int $submissionId;
    private ?array $repositories = null;
    private ?array $manifestFiles = null;

    /** The author's comment on each expected output, keyed by file name. */
    private array $manifestComments = [];

    /** The stored row, loaded on first use by storedRecord(). */
    private ?object $existing = null;

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
     * @param string[] $lines one expected output per entry, as the wizard's
     *                        field writes it: `file`, or `file - comment`
     */
    public function setManifest(array $lines): void
    {
        $this->manifestFiles = [];
        $this->manifestComments = [];

        foreach ($lines as $line) {
            ['file' => $file, 'comment' => $comment] = self::parseManifestLine($line);
            if ($file === '') {
                continue;
            }
            $this->manifestFiles[] = $file;
            $this->manifestComments[$file] = $comment;
        }
    }

    /**
     * Split one line of the wizard's expected-output field into the file and
     * the author's comment on it.
     *
     * The field writes `file - comment`, and the whole line used to be stored as
     * the file name, so the comment was lost and the name no longer matched
     * the file. The first ` - ` separates them, as the field reads it back.
     *
     * @return array{file: string, comment: string}
     */
    public static function parseManifestLine(string $line): array
    {
        $parts = explode(' - ', $line, 2);

        return [
            'file' => trim($parts[0]),
            'comment' => trim($parts[1] ?? ''),
        ];
    }

    /**
     * What an existing check's `codecheck.yml` can give the wizard (#190): its
     * repositories and expected outputs, in the shape of the wizard's fields.
     * Everything else in the file is the editorial record's.
     *
     * `repository` is a string or a list; anything that is not a web address
     * is left out, as the wizard would refuse it.
     *
     * @return array{repositories: string[], manifest: array<int, array{file: string, comment: string}>}
     */
    public static function entriesFromCodecheckYaml(array $yml): array
    {
        $repositories = $yml['repository'] ?? [];
        $repositories = is_array($repositories) ? $repositories : [$repositories];
        $repositories = array_values(array_unique(array_map(
            'trim',
            array_filter($repositories, fn ($url) => is_string($url) && Constants::isWebUrl($url))
        )));

        // The wizard's field is one `file - comment` line per entry, so line
        // breaks in a comment become spaces, and a file it cannot write back
        // as one line — a line break, or the separator in its name — is left out.
        $oneLine = fn ($value) => is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '') : '';
        $manifest = [];
        foreach (is_array($yml['manifest'] ?? null) ? $yml['manifest'] : [] as $entry) {
            $file = is_array($entry) && is_scalar($entry['file'] ?? null) ? trim((string) $entry['file']) : '';
            if ($file === '' || preg_match('/[\r\n]/', $file) || str_contains($file, ' - ')) {
                continue;
            }
            $manifest[] = ['file' => $file, 'comment' => $oneLine($entry['comment'] ?? '')];
        }

        return ['repositories' => $repositories, 'manifest' => $manifest];
    }

    /**
     * Whether a value can point to an existing check (#190): a web address, or
     * a DOI as `Constants::bareDoi()` reads one. An empty value is no pointer,
     * and is allowed when saving: it removes one.
     */
    public static function isExistingCheckAddress(?string $value): bool
    {
        return trim((string) $value) === ''
            || Constants::isWebUrl($value)
            || Constants::bareDoi($value) !== null;
    }

    /**
     * The addresses this save would introduce that cannot be a repository link.
     *
     * Asked before saving, so the submission can be refused rather than the
     * addresses quietly dropped — PKP's own model is that the client posts what
     * the author typed and the server says no, with the error keyed to the
     * field (`FormComponent::$errors`). Nothing here alters the payload.
     *
     * Only what this save introduces: an address already in the record is left
     * alone, or an editor would be locked out by a value they did not enter
     * (issue #170).
     *
     * @return array<int, string>
     */
    public function introducedUnusableRepositories(): array
    {
        if ($this->repositories === null) {
            return [];
        }

        return CodecheckRepositories::newUnusableUrls(
            $this->repositories,
            $this->storedRecord()->repository ?? null
        );
    }

    /** The stored row, read once per instance. */
    private function storedRecord(): ?object
    {
        return $this->existing ??= DB::table('codecheck_metadata')
            ->where('submission_id', $this->submissionId)
            ->first();
    }

    public function save(): void
    {
        $existing = $this->storedRecord();

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
                fn ($url) => ['url' => $url, 'hidden' => false, 'providedByAuthor' => true, 'containsCodecheckYaml' => false]
            );
            $update['repository'] = json_encode($repositoryData);
        }

        if ($this->manifestFiles !== null) {
            $current = json_decode($existing->manifest ?? '', true);
            $current = is_array($current) ? $current : [];

            $manifest = $this->merge(
                $current,
                $this->manifestFiles,
                'file',
                fn ($file) => ['file' => $file, 'comment' => '', 'hidden' => false, 'providedByAuthor' => true]
            );
            $update['manifest'] = json_encode($this->withAuthorComments($manifest));
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
     * Put the author's comments on their entries. An empty comment leaves the
     * stored one alone, so a comment the codechecker wrote is not wiped by an
     * author who left the field blank.
     */
    private function withAuthorComments(array $manifest): array
    {
        return array_map(function ($entry) {
            $comment = $this->manifestComments[$entry['file'] ?? ''] ?? '';
            if (!empty($entry['providedByAuthor']) && $comment !== '') {
                $entry['comment'] = $comment;
            }
            return $entry;
        }, $manifest);
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
