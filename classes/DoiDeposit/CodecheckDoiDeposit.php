<?php

/**
 * @file classes/DoiDeposit/CodecheckDoiDeposit.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckDoiDeposit
 *
 * @brief Adds the CODECHECK links to the Crossref and DataCite records OJS
 *   deposits for an article (#19), and marks the article's DOI for re-deposit
 *   when the links change after it was deposited.
 *
 * PKP's Crossref and DataCite plugins are not touched. `Filter::execute()`
 * raises `<filter class>::execute` with the finished document, after it is
 * built and before OJS validates and deposits it, and the callbacks here add to
 * it. Two properties are easy to undo by accident:
 *
 * - **The hooks are registered outside the plugin's enabled check**, and the
 *   journal comes from the document. Deposits are queued jobs: in a CLI worker
 *   OJS loads every generic plugin with no journal, where `getEnabled()` reads
 *   the site row, so the plugin is asked whether it is enabled in the record's
 *   journal instead (Crossref Reference Linking does the same, pkp-lib#12940).
 *   For the same reason the opt-in is read from the database: the submission
 *   schema carries `codecheckOptIn` only where the plugin registered it. A job
 *   run at the end of a web request for *another* journal, or a site page,
 *   loads only that journal's enabled plugins, so this plugin never hears of
 *   the deposit and the record goes out without links; nothing a plugin can do
 *   reaches that (pkp-lib#9345).
 * - **The links are not validated at deposit time.** Both schemas take any
 *   text as the related identifier, and everything else written is fixed, so
 *   a check per deposit could only repeat what the unit tests establish once
 *   against the published schemas — while fetching every schema file a second
 *   time, about 13 s for Crossref, inside a job limited to 30 s. OJS validates
 *   the whole record afterwards, and an editor sees the result by exporting the
 *   article's XML from the DOI list.
 */

namespace APP\plugins\generic\codecheck\classes\DoiDeposit;

use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\plugins\PluginRegistry;
use PKP\submission\PKPSubmission;

class CodecheckDoiDeposit
{
    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /**
     * `articlecrossrefxmlfilter::execute`. One document may hold several
     * articles, each found by its DOI.
     *
     * @param array{0: mixed} $args The document, by reference.
     */
    public function addToCrossref(string $hookName, array $args): bool
    {
        $doc = $args[0] ?? null;
        if (!self::isDocument($doc)) {
            return false;
        }

        try {
            foreach ($doc->getElementsByTagNameNS($doc->documentElement->namespaceURI, 'journal_article') as $article) {
                $doi = CrossrefRelations::articleDoi($article);
                $ids = $doi === null ? null : self::idsForDoi($doi);
                if ($ids !== null && $this->plugin->isDoiDepositLinksEnabled($ids['contextId'])) {
                    self::logAdded('Crossref', $ids['submissionId'], CrossrefRelations::addToArticle($article, self::linksFor($ids['submissionId'])));
                }
            }
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not add the CODECHECK links to the Crossref record: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * `datacitexmlfilter::execute`. The same filter writes issues, articles and
     * galleys; only an article gets links.
     *
     * @param array{0: mixed} $args The document, by reference.
     */
    public function addToDatacite(string $hookName, array $args): bool
    {
        $doc = $args[0] ?? null;
        if (!self::isDocument($doc)) {
            return false;
        }

        try {
            $ids = DataciteRelations::articleIds($doc);
            if ($ids !== null
                && $this->plugin->isDoiDepositLinksEnabled($ids['contextId'])
                && CodecheckSubmissionDAO::contextIdOf($ids['submissionId']) === $ids['contextId']) {
                self::logAdded('DataCite', $ids['submissionId'], DataciteRelations::addToResource($doc, self::linksFor($ids['submissionId'])));
            }
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not add the CODECHECK links to the DataCite record: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * The links a deposit of the article would carry now, or `null` when the
     * journal has not asked for a re-deposit when they change — which costs a
     * query to find out, and nothing more.
     *
     * Taken before a write that may change them, and handed to
     * `redepositIfChanged()` after it.
     *
     * @return null|array<int, array{relation: string, type: string, identifier: string}>
     */
    public static function linksBeforeChange(int $submissionId): ?array
    {
        try {
            $plugin = PluginRegistry::getPlugin('generic', 'codecheckplugin');

            return $plugin instanceof CodecheckPlugin && $plugin->isDoiRedepositEnabled(CodecheckSubmissionDAO::contextIdOf($submissionId))
                ? self::linksFor($submissionId)
                : null;
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not read the CODECHECK links of submission #' . $submissionId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark the article's DOI stale when the links a deposit would carry have
     * changed — the certificate published after the article, its DOI entered
     * after that, a repository added, or the status taken back — so that OJS
     * deposits the record again and Crossref or DataCite hold what the journal
     * now says.
     *
     * OJS's `markStale()` touches only a DOI already submitted or registered,
     * and only automatic deposit picks a stale one up; with manual deposit the
     * editor sees it marked stale in the DOI list. Best effort: the write that
     * changed the links is never undone over this.
     *
     * @param null|array<int, array{relation: string, type: string, identifier: string}> $before From `linksBeforeChange()`.
     */
    public static function redepositIfChanged(int $submissionId, ?array $before): void
    {
        if ($before === null) {
            return;
        }

        try {
            if (self::linksFor($submissionId) === $before) {
                return;
            }

            // The publication OJS deposits: both filters write the current one.
            $publication = Repo::submission()->get($submissionId)?->getCurrentPublication();
            $doiId = $publication?->getData('doiId');
            if (!$doiId || (int) $publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
                return;
            }

            Repo::doi()->markStale([$doiId]);
            CodecheckLogger::info('Marked the DOI of submission #' . $submissionId . ' for re-deposit, as its CODECHECK links changed.');
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not mark the DOI of submission #' . $submissionId . ' for re-deposit: ' . $e->getMessage());
        }
    }

    private static function isDocument(mixed $doc): bool
    {
        return $doc instanceof DOMDocument && $doc->documentElement instanceof DOMElement;
    }

    private static function logAdded(string $format, int $submissionId, int $added): void
    {
        if ($added > 0) {
            CodecheckLogger::info('Added ' . $added . ' CODECHECK link(s) to the ' . $format . ' record of submission #' . $submissionId . '.');
        }
    }

    /**
     * The links for one article as the check stands now. Whether the journal
     * wants them is the caller's question.
     *
     * @return array<int, array{relation: string, type: string, identifier: string}>
     */
    private static function linksFor(int $submissionId): array
    {
        return CodecheckDepositLinks::forCheck(
            CodecheckSubmissionDAO::isOptedIn($submissionId),
            (string) (CodecheckStatusHandler::getCurrentStatusData($submissionId)->status ?? ''),
            (new CodecheckSubmissionDAO())->getBySubmissionId($submissionId)
        );
    }

    /**
     * The article a Crossref DOI belongs to. Every version of a submission may
     * carry the same DOI, so the answer is the one submission they share, or
     * nothing when the DOI is unknown or ambiguous.
     *
     * @return array{contextId: int, submissionId: int}|null
     */
    private static function idsForDoi(string $doi): ?array
    {
        $rows = DB::table('dois as d')
            ->join('publications as p', 'p.doi_id', '=', 'd.doi_id')
            ->join('submissions as s', 's.submission_id', '=', 'p.submission_id')
            ->where('d.doi', '=', $doi)
            ->distinct()
            ->get(['s.submission_id', 's.context_id']);

        if ($rows->count() !== 1) {
            return null;
        }

        return ['contextId' => (int) $rows[0]->context_id, 'submissionId' => (int) $rows[0]->submission_id];
    }
}
