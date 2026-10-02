<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusHandler;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckStatusRegisterUpdate;

class CodecheckGithubRegisterIssue
{
    /**
     * What the JSON metadata block starts with, which is how it is found again.
     * A comment, so it says nothing to a reader and stays put whatever the
     * summary line below it says.
     */
    private const METADATA_BLOCK_MARKER = "<!-- CODECHECK metadata: written by the journal's OJS, do not edit by hand -->\n";

    /** How the block opened before it was marked, so those issues are found too. */
    private const LEGACY_METADATA_BLOCK_OPENING = "<details>\n<summary><h3>JSON encoded CODECHECK metadata</h3></summary>\n\n";

    private string $repositoryOwner;
    private string $repository;
    private string $title;
    private string $body;
    private string $submissionID;
    private array $labels;
    private string $jsonEncodedCodecheckMetadata;
    private string $codecheckStatus;
    private bool $updateStatus;

    public function __construct(
        string $repositoryOwner,
        string $repository,
        CertificateIdentifier $certificateIdentifier,
        CodecheckIssueLabels $codecheckIssueLabels,
        string $paperTitle,
        CodecheckPostOrigin $origin,
        string $authorString,
        string $submissionID,
        array $codecheckers,
        array $repositories,
        array $updateInformation
    ) {
        $this->repositoryOwner = $repositoryOwner;
        $this->repository = $repository;
        $this->submissionID = $submissionID;
        $this->codecheckStatus = '';
        $this->updateStatus = false;
        if (in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $updateInformation)) {
            $this->updateStatus = true;
            $this->codecheckStatus = CodecheckStatusHandler::getCurrentStatusData($this->submissionID)->status;
        }
        $updateCodecheckStatus = $this->updateStatus ? 'true' : 'false';
        CodecheckLogger::debug('Record / Update Status: ' . $updateCodecheckStatus);
        $authorString = empty($authorString) ? 'New CODECHECK' : $authorString;
        // The issue is public. A repository marked hidden is part of the record
        // but must never reach a reader, and the editorial form posts whole
        // entries — which would otherwise be published verbatim, hidden ones and
        // all, along with their internal flags (Issue #154).
        $repositories = CodecheckRepositories::publicUrls($repositories);
        $this->title = $this->createTitleMarkdown($authorString, $certificateIdentifier);
        $this->jsonEncodedCodecheckMetadata = $this->createJsonEncodedCodecheckMetadataMarkdown($certificateIdentifier, $origin, $submissionID, $codecheckers, $repositories);
        // The body is rendered whole on every update, so the signature closes
        // it once and is never stacked.
        $this->body = $this->createBodyMarkdown($paperTitle, $origin->getJournalName(), $repositories, $codecheckers)
            . "\n" . $this->jsonEncodedCodecheckMetadata
            . $origin->signature();
        $this->labels = $this->fillLabels($codecheckIssueLabels);
    }

    public function getRepositoryOwner(): string
    {
        return $this->repositoryOwner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    private function createTitleMarkdown(
        string $authorString,
        CertificateIdentifier $certificateIdentifier
    ): string {
        return $authorString . ' | ' . $certificateIdentifier->toStr();
    }

    /**
     * The record as JSON, for whoever processes the register by machine.
     *
     * Built as an array and encoded, where it used to be concatenated: that gave
     * a trailing comma after `journal` and left the journal name unescaped, so
     * the block was never valid JSON. `journal.url`, `journal.ojsVersion` and
     * `plugin` say which installation wrote it.
     */
    private function createJsonEncodedCodecheckMetadataMarkdown(
        CertificateIdentifier $certificateIdentifier,
        CodecheckPostOrigin $origin,
        string $submissionID,
        array $codecheckers,
        array $repositories
    ): string {
        return self::metadataBlock(
            $certificateIdentifier->toStr(),
            $this->updateStatus ? $this->codecheckStatus : null,
            $repositories,
            $codecheckers,
            $origin,
            $submissionID
        );
    }

    /**
     * The JSON metadata block on its own, so it can be rewritten from the stored
     * record when the status or the record changes, without the paper title
     * and author string the rest of the body needs (#186).
     *
     * It is the one part of the issue written from the record, so it says so
     * where it can be read without opening it: that it is the record, and as
     * of when. It claims no more than that time — a reviewer's status change,
     * or a journal that does not keep the body up to date, leaves it as it
     * was. The readable lines above it are written only by "update issue",
     * and the comments below are a record of how the check progressed.
     *
     * @param ?string $status the status key, or null when the journal does not
     *   publish the status
     * @param string[] $publicRepositories already reduced to what a reader may see
     * @param ?\DateTimeInterface $updated when it was written; now, unless a test says otherwise
     */
    public static function metadataBlock(
        string $identifier,
        ?string $status,
        array $publicRepositories,
        mixed $codecheckers,
        CodecheckPostOrigin $origin,
        string $submissionID,
        ?\DateTimeInterface $updated = null
    ): string {
        $metadata = ['identifier' => $identifier];
        if ($status !== null) {
            $metadata['status'] = $status;
        }
        $metadata += [
            'repositories' => array_values($publicRepositories),
            // In the stored shape, whatever the form sent: name, bare ORCID iD
            // and GitHub username (#186).
            'codecheckers' => CodecheckCodecheckers::withNormalizedEntries($codecheckers),
            'links' => [],
            'journal' => $origin->journalMetadata() + ['submissionID' => (int) $submissionID],
            'plugin' => $origin->pluginMetadata(),
        ];

        $updated = ($updated ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'));

        // English whatever the editor's language: the register is one shared
        // place read in English, as the signature is.
        return self::METADATA_BLOCK_MARKER
        . "<details>\n<summary><h3>CODECHECK metadata, the record of this check (JSON, updated "
        . $updated->format('Y-m-d H:i') . " UTC)</h3></summary>\n\n"
        . 'This block is the source of truth for the metadata of this check, as of the time above, written by the journal from its record. '
        . "The lines above it and the comments below record how the check progressed and may describe an earlier state.\n\n"
        . "```json\n"
        // A backtick as \u0060, which is the same string in JSON: a name holding
        // three of them would otherwise close the fence early.
        . str_replace('`', '\u0060', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE))
        . "\n```"
        . "\n\n</details>";
    }

    /**
     * An issue body with its JSON metadata block replaced, or null when the
     * body has none — one edited by hand, or written by something else — which
     * is then left as it is rather than given a second block. A block written
     * before it was marked is found, and replaced by a marked one.
     */
    public static function withMetadataBlock(string $body, string $block): ?string
    {
        $body = self::withLineFeeds($body);
        $span = self::metadataBlockSpan($body);
        if ($span === null) {
            return null;
        }

        return substr($body, 0, $span[0]) . $block . substr($body, $span[1]);
    }

    /**
     * The JSON a body's metadata block carries, or null when it has none.
     *
     * What decides whether the block needs writing: the update time in its
     * summary changes every time, so comparing whole bodies would rewrite the
     * issue on every save, changed or not.
     */
    public static function metadataJson(string $body): ?string
    {
        $block = self::metadataBlockOf($body);
        if ($block === null) {
            return null;
        }

        return preg_match('/```json\n(.*?)\n```/s', $block, $match) ? $match[1] : null;
    }

    /** Whether a body's metadata block carries the marker, rather than being one written before it. */
    public static function hasMarkedMetadataBlock(string $body): bool
    {
        return str_contains(self::withLineFeeds($body), self::METADATA_BLOCK_MARKER);
    }

    /**
     * A freshly built body, with the metadata block of the body the issue
     * has now put back in when the JSON of the two is the same — so a body
     * rewritten with nothing new in its block keeps the time the record last
     * changed, rather than the time of the latest save.
     */
    public static function keepingUnchangedMetadataBlock(string $newBody, string $currentBody): string
    {
        $current = self::metadataBlockOf($currentBody);
        if ($current === null
            || !self::hasMarkedMetadataBlock($current)
            || self::metadataJson($current) !== self::metadataJson($newBody)) {
            return $newBody;
        }

        return self::withMetadataBlock($newBody, $current) ?? $newBody;
    }

    /** A body's metadata block, line feeds only, or null when it has none. */
    private static function metadataBlockOf(string $body): ?string
    {
        $body = self::withLineFeeds($body);
        $span = self::metadataBlockSpan($body);

        return $span === null ? null : substr($body, $span[0], $span[1] - $span[0]);
    }

    /**
     * A body with GitHub's web editor's CRLF line endings made LF, which is
     * how the plugin writes it: a body saved there would otherwise no longer
     * show the block the plugin looks for.
     */
    private static function withLineFeeds(string $body): string
    {
        return str_replace("\r\n", "\n", $body);
    }

    /**
     * Where the metadata block starts and ends in a body: `[start, end]`, end
     * exclusive, or null when there is none.
     *
     * @return ?array{0: int, 1: int}
     */
    private static function metadataBlockSpan(string $body): ?array
    {
        $start = strpos($body, self::METADATA_BLOCK_MARKER);
        if ($start === false) {
            $start = strpos($body, self::LEGACY_METADATA_BLOCK_OPENING);
        }
        if ($start === false) {
            return null;
        }
        $end = strpos($body, '</details>', $start);
        if ($end === false) {
            return null;
        }

        return [$start, $end + strlen('</details>')];
    }

    private function createBodyMarkdown(
        string $paperTitle,
        string $journalName,
        array $repositories,
        array $codecheckers
    ): string {
        $repoStr = '';
        foreach ($repositories as $repo) {
            $repoStr .= "\t- " . $repo . "\n";
        }
        // Named without a mention: the body is rewritten on every update, and
        // the assignment is what says on GitHub who is checking (#186).
        $codecheckerNames = RegisterCodecheckers::describeAll($codecheckers);
        $codecheckerInformation = $codecheckerNames === ''
            ? ''
            : "<!-- Who is checking -->\n**Codecheckers:** " . $codecheckerNames . "\n\n";
        $statusInformation = $this->updateStatus ? "<!-- The current status of the CODECHECK -->\n**CODECHECK Status:** " . __($this->codecheckStatus) . "\n\n" : '';

        return "<!-- Provide the title of your published paper or preprint -->\n## " . $paperTitle . "\n\n"
        . "<!-- Provide a link to your published paper or preprint, ideally with a DOI -->\n**Article:**\n\n"
        . "<!-- Information about the Journal in which the paper/ preprint is published -->\n**Journal:** " . $journalName . ' *(Submission ID: ' . $this->submissionID . ")*\n\n"
        . "<!-- Provide a link to your code (and data) repository(s) (GitHub, GitLab, etc.) -->\n**Repositories:**\n" . $repoStr . "\n\n"
        . $codecheckerInformation
        . $statusInformation;
    }

    /**
     * The labels the issue carries: the identifier label, the ones the status
     * asks for, and the venue labels the editor chose.
     *
     * The status labels belong here as well as on a later status change (#174),
     * because the ordinary order of work records a status *before* an identifier
     * is reserved — so the issue would otherwise be opened without
     * `needs codechecker`, which is exactly the label someone looking for work
     * in the register filters on, and no later change would add it.
     */
    private function fillLabels(
        CodecheckIssueLabels $codecheckIssueLabels
    ): array {
        $labels = [Constants::CODECHECK_REGISTER_ID_ASSIGNED_LABEL];

        if ($this->updateStatus) {
            $labels = array_merge(
                $labels,
                CodecheckStatusRegisterUpdate::wantedLabels($this->codecheckStatus) ?? []
            );
        }

        $labels = array_merge($labels, $codecheckIssueLabels->get()->toArray());

        return array_values(array_unique($labels));
    }

    private function getFormattedLabelsForUrl(): string
    {
        $labels = '';
        $countLabels = 0;
        foreach ($this->labels as $label) {
            $labels = $labels . rawurlencode($label);

            if ($countLabels < count($this->labels) - 1) {
                $labels = $labels . ',';
            }

            $countLabels++;
        }

        return $labels;
    }

    public function getNewIssueUrl(): string
    {
        $url = "https://github.com/{$this->repositoryOwner}/{$this->repository}/issues/new";
        $queryTitle = 'title=' . rawurlencode($this->title);
        $queryBody = 'body=' . rawurlencode($this->body);
        $queryLabels = 'labels=' . $this->getFormattedLabelsForUrl();

        return $url . '?' . $queryTitle . '&' . $queryBody . '&' . $queryLabels;
    }
}
