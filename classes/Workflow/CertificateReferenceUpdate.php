<?php

/**
 * @file classes/Workflow/CertificateReferenceUpdate.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CertificateReferenceUpdate
 *
 * @brief Writes the CODECHECK certificate into an article's references (#183):
 *   from the editorial form's button, and on publication when the journal
 *   asked for that. The line itself and how it joins the list are
 *   `CertificateReference`'s.
 *
 * The two writes take different paths through OJS on purpose:
 *
 * - **The button** edits the latest publication through
 *   `Repo::publication()->edit()`, OJS's own path: it passes the old
 *   publication, so the DAO reparses the references and `Publication::edit`
 *   fires for anything else listening.
 * - **On publication** that path is closed. `publish()` calls
 *   `dao->update()` without the old publication, so a `citationsRaw` set in
 *   `Publication::publish::before` is silently not reparsed; the references
 *   are imported with `CitationDAO::importCitations()` here instead. That hook
 *   runs inside `publish()`, so it fires on every publishing path — the REST
 *   API, publishing an issue, and the scheduled task, which runs on the
 *   command line. That is why it is registered outside the enabled check and
 *   asks the publication's own journal.
 */

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CertificateReference;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionDAO;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\citation\CitationDAO;
use PKP\db\DAORegistry;
use PKP\submission\PKPSubmission;

class CertificateReferenceUpdate
{
    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /**
     * `Publication::publish::before`, with `[&$newPublication, $publication]`.
     * Best effort: publishing is never refused over a reference.
     *
     * Every journal's publishing passes through here, so the journal's mode is
     * asked first, from one column rather than a loaded submission.
     */
    public function addOnPublish(string $hookName, array $args): bool
    {
        $newPublication = $args[0] ?? null;
        if (!$newPublication instanceof Publication) {
            return false;
        }

        $submissionId = (int) $newPublication->getData('submissionId');
        try {
            if ($this->plugin->getCertificateReferenceMode(CodecheckSubmissionDAO::contextIdOf($submissionId)) !== Constants::CODECHECK_CERTIFICATE_REFERENCE_PUBLISH
                || !CodecheckSubmissionDAO::isOptedIn($submissionId)) {
                return false;
            }

            $references = self::references($submissionId, $newPublication);
            if ($references === null) {
                CodecheckLogger::info('Submission #' . $submissionId . ' is published without its CODECHECK certificate among the references: the certificate is not published yet.');
                return false;
            }
            if (!$references['changed']) {
                return false;
            }

            /** @var CitationDAO $citationDao */
            $citationDao = DAORegistry::getDAO('CitationDAO');
            $citationDao->importCitations((int) $newPublication->getId(), $references['merged']);
            $newPublication->setData('citationsRaw', $references['merged']);
            CodecheckLogger::info('Listed the CODECHECK certificate among the references of submission #' . $submissionId . '.');
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not list the CODECHECK certificate among the references of submission #' . $submissionId . ': ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Add or refresh the line in the submission's latest publication, for the
     * editorial form's button.
     *
     * @return array{line: string, changed: bool}|array{error: string, status: int}
     */
    public function addToLatestPublication(Submission $submission, int $userId): array
    {
        $submissionId = (int) $submission->getId();
        if ($this->plugin->getCertificateReferenceMode((int) $submission->getData('contextId')) === Constants::CODECHECK_CERTIFICATE_REFERENCE_OFF
            || !CodecheckSubmissionDAO::isOptedIn($submissionId)) {
            return ['error' => __('plugins.generic.codecheck.certificateReference.disabled'), 'status' => 400];
        }

        // Who may ask before what state the publication is in.
        if (!Repo::submission()->canEditPublication($submissionId, $userId)) {
            return ['error' => __('plugins.generic.codecheck.certificateReference.notAllowed'), 'status' => 403];
        }

        // OJS locks a published version against edits; a reference belongs in
        // the next version, which is then refreshed when it is published.
        $publication = $submission->getLatestPublication();
        if (!$publication || (int) $publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return ['error' => __('plugins.generic.codecheck.certificateReference.published'), 'status' => 400];
        }

        $references = self::references($submissionId, $publication);
        if ($references === null) {
            return ['error' => __('plugins.generic.codecheck.certificateReference.noCertificate'), 'status' => 400];
        }

        if ($references['changed']) {
            Repo::publication()->edit($publication, ['citationsRaw' => $references['merged']]);
        }

        return ['line' => $references['line'], 'changed' => $references['changed']];
    }

    /**
     * The publication's references with the certificate's line merged in, or
     * `null` when there is no published certificate to cite. Only once the
     * status says the certificate is published, as for the DOI deposit (#19):
     * an identifier is reserved when a check starts, and its register page
     * does not exist until the certificate does.
     *
     * @return array{line: string, merged: string, changed: bool}|null
     */
    private static function references(int $submissionId, Publication $publication): ?array
    {
        $status = CodecheckStatusHandler::getCurrentStatusData($submissionId)->status ?? null;
        if (!in_array($status, Constants::CODECHECK_STATUSES_CERTIFICATE_PUBLISHED, true)) {
            return null;
        }

        $record = (new CodecheckSubmissionDAO())->getBySubmissionId($submissionId);
        $line = $record === null ? null : CertificateReference::format($record);
        if ($line === null) {
            return null;
        }

        $raw = (string) $publication->getData('citationsRaw');
        $merged = CertificateReference::merge($raw, $line, $record);

        return ['line' => $line, 'merged' => $merged, 'changed' => $merged !== $raw];
    }
}
