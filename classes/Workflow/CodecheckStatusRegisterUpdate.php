<?php

/**
 * @file classes/Workflow/CodecheckStatusRegisterUpdate.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckStatusRegisterUpdate
 *
 * @brief Carries a CODECHECK status change to the register issue: a comment
 *   saying it changed (#150), the labels that say where the check stands
 *   (#174), and — once a codechecker is assigned — who that is (#186).
 *
 * Parts of one idea, in one class because they need the same four things
 * resolved — the journal's "update status" choice, the credentials, the register
 * and the issue number (`register()`) — and a second resolver would be a second
 * reader of all four. Each part is attempted independently, so a refused comment
 * still brings the labels with it.
 *
 * The register issue's title, body and labels are rewritten in place as a check
 * progresses, so the issue always shows the present state and never how it got
 * there. GitHub renders comments as a timeline, which is where a reader of the
 * register looks to see what happened when (#150).
 *
 * Best-effort throughout, like the register deposit: a status change is recorded
 * in the journal whether or not GitHub can be reached, and an unreachable GitHub
 * must never cost an editor their edit.
 */

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterIssue;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckPostOrigin;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\RegisterCodecheckers;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use Illuminate\Support\Facades\DB;
use PKP\plugins\PluginRegistry;

class CodecheckStatusRegisterUpdate
{
    /**
     * Whether anything could not be carried to the register in this request;
     * the log says what. A static, so the endpoint that triggered the sync can
     * tell the editor; it lasts one request under mod_php or FPM.
     */
    private static bool $failed = false;

    /**
     * For the editor, one sentence on what the register issue did not get in
     * this request, or null when it got everything it was sent — including
     * when nothing was sent. The record itself is saved either way: the
     * register is best-effort (#150, #186).
     */
    public static function warning(): ?string
    {
        if (!self::$failed) {
            return null;
        }

        return GithubHttp::wasUnreachable()
            ? GithubHttp::unreachableMessage('plugins.generic.codecheck.register.sync.unreachable')
            : __('plugins.generic.codecheck.register.sync.failed');
    }

    /** Forget the failures, for tests. */
    public static function resetFailures(): void
    {
        self::$failed = false;
    }

    /** Log a failure to reach the register, and keep it for `warning()`. */
    private static function failed(string $message): void
    {
        CodecheckLogger::warning($message);
        self::$failed = true;
    }

    /**
     * Bring the submission's register issue up to date with the new status, if
     * there is an issue to bring up to date.
     *
     * @param string $status The status as a locale key, as stored.
     */
    public static function apply(int $submissionId, string $status): void
    {
        try {
            $register = self::register($submissionId);
            if ($register === null) {
                return;
            }
            ['client' => $client, 'issueNumber' => $issueNumber, 'origin' => $origin] = $register;
            $codecheckers = $register['metadata']->codecheckers ?? null;

            // Independent parts: the labels are the part a reader of the
            // register filters on, so a refused comment must not cost them.
            // The assignment goes first because the comment says who it reached.
            // Only an editor's recording assigns, as only an editor writes to
            // the register otherwise (#173); a reviewer's still comments (#150).
            $named = null;
            if ($status === Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER && self::byEditor()) {
                $assigned = $client->syncAssignees($issueNumber, $codecheckers);
                // A failed request says nothing about who is assigned, so the
                // comment then names nobody rather than calling them unreachable.
                if ($assigned === null) {
                    self::$failed = true;
                } else {
                    $split = RegisterCodecheckers::split($codecheckers, $assigned);
                    $named = $split['assigned'] === [] && $split['unassigned'] === [] ? null : $split;
                }
            }
            self::comment($client, $issueNumber, $submissionId, $status, $named, $origin);
            self::syncLabels($client, $issueNumber, $submissionId, $status);
        } catch (\Throwable $e) {
            self::failed('Could not carry the CODECHECK status to the register issue: ' . $e->getMessage());
        }

        self::refreshMetadata($submissionId);
    }

    /**
     * Assign the codecheckers on record to the register issue, outside a status
     * change (#186): a codechecker added, or given a username, after the check
     * was marked "codechecker assigned" records no new status, and would
     * otherwise never be assigned. GitHub shows the assignment on the issue's
     * timeline, so no comment is written.
     *
     * Under the same conditions as `apply()` — the journal's "update status"
     * choice, the credentials, an issue in the configured register, and an
     * editor asking — and only once the check stands at "codechecker assigned"
     * or later: an issue still labelled `needs codechecker` with someone
     * assigned says both things at once, the state #174 removed. The
     * assignment follows the status, as the labels do.
     */
    public static function syncAssignees(int $submissionId): void
    {
        try {
            if (!self::byEditor() || !self::statusAssigns($submissionId)) {
                return;
            }

            $register = self::register($submissionId);
            if ($register === null) {
                return;
            }

            // The client has logged why; null is its failure, [] nobody to assign.
            if ($register['client']->syncAssignees($register['issueNumber'], $register['metadata']->codecheckers ?? null) === null) {
                self::$failed = true;
            }
        } catch (\Throwable $e) {
            self::failed('Could not assign the codecheckers to the register issue: ' . $e->getMessage());
        }
    }

    /**
     * Rewrite the JSON metadata block in the register issue's body from the
     * stored record (#186), so the record a machine reads off the register
     * follows a status change or a save, not only the editor's "update issue".
     *
     * The rest of the body — paper title, authors, the readable lines — needs
     * what only the form sends, and is still rewritten by "update issue" alone.
     *
     * Under the journal's choice to keep the issue body up to date, and once
     * the record carries its identifier. Whoever recorded the status: a
     * reviewer's status change already comments and moves the labels, and the
     * block, which says it is the source of truth, must not lag behind them.
     * A save reaches this for an editor only (`CodecheckMetadataHandler::afterSave()`).
     */
    public static function refreshMetadata(int $submissionId): void
    {
        try {
            $register = self::register($submissionId, Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_BODY);
            if ($register === null) {
                return;
            }

            $metadata = $register['metadata'];
            $identifier = trim((string) ($metadata->certificate ?? ''));
            if ($identifier === '') {
                return;
            }

            $status = in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $register['updateFields'])
                ? CodecheckStatusHandler::getCurrentStatusData($submissionId)->status
                : null;

            $block = CodecheckGithubRegisterIssue::metadataBlock(
                $identifier,
                $status,
                CodecheckRepositories::publicUrls($metadata->repository ?? null),
                $metadata->codecheckers ?? null,
                $register['origin'],
                (string) $submissionId
            );

            if ($register['client']->replaceIssueMetadataBlock($register['issueNumber'], $block)) {
                CodecheckLogger::info("Rewrote the metadata of register issue #{$register['issueNumber']} for submission {$submissionId}.");
            }
        } catch (\Throwable $e) {
            self::failed('Could not rewrite the metadata of the register issue: ' . $e->getMessage());
        }
    }

    /**
     * Whether the check's current status is one at which a codechecker is
     * assigned: anything past waiting for one.
     */
    private static function statusAssigns(int $submissionId): bool
    {
        $current = CodecheckStatusHandler::getCurrentStatusData($submissionId)->status ?? null;

        return is_string($current) && !in_array($current, [
            Constants::CODECHECK_STATUS_PENDING,
            Constants::CODECHECK_STATUS_NEEDS_CODECHECKER,
        ], true);
    }

    /** Whether the request comes from someone in an editorial role (#173). */
    private static function byEditor(): bool
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        return $context !== null && CodecheckSubmissionAccess::isEditor($request->getUser(), $context->getId());
    }

    /**
     * Everything a write to the register issue needs, or `null` when there is
     * to be no write: the client, the issue number, the post origin, the stored
     * record and the journal's choice of what the issue reflects.
     *
     * @param string $field the part of the issue the journal must have chosen
     *   to keep up to date: the status (comment, labels, assignees) or the body
     *
     * @return ?array{client: CodecheckGithubRegisterApiClient, issueNumber: int, origin: CodecheckPostOrigin, metadata: object, updateFields: array}
     */
    private static function register(int $submissionId, string $field = Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS): ?array
    {
        $plugin = PluginRegistry::getPlugin('generic', 'codecheckplugin');
        $context = Application::get()->getRequest()->getContext();

        if (!$plugin || !$context) {
            return null;
        }

        $contextId = $context->getId();

        // The journal chose what the register issue reflects. What it did not
        // choose is left alone: a comment saying the status changed would be
        // noise in someone else's repository.
        $updateFields = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);
        if (!is_array($updateFields) || !in_array($field, $updateFields)) {
            return null;
        }

        $token = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
        $organization = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
        $repository = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

        if (empty($token) || empty($organization) || empty($repository)) {
            return null;
        }

        // A write is due, and would fail at once: GitHub did not answer
        // earlier in this request. Reported as this sync failing, since it did.
        if (GithubHttp::wasUnreachable()) {
            self::$failed = true;
            return null;
        }

        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first(['issue', 'codecheckers', 'certificate', 'repository']);
        if (!$metadata) {
            return null;
        }

        $issueNumber = self::issueNumberInRegister($submissionId, $metadata->issue ?? null, $organization, $repository);
        if ($issueNumber === null) {
            return null;
        }

        $origin = CodecheckPostOrigin::fromContext($plugin, $context);
        $client = new CodecheckGithubRegisterApiClient(
            $token,
            $organization,
            $repository,
            (string) $submissionId,
            $origin
        );

        return [
            'client' => $client,
            'issueNumber' => $issueNumber,
            'origin' => $origin,
            'metadata' => $metadata,
            'updateFields' => $updateFields,
        ];
    }

    /**
     * Which managed labels belong on the register issue at this status, or
     * `null` when the status has no mapping — the one reader of the map.
     *
     * Asked before the issue's labels are fetched, so an unmapped status costs
     * no GitHub request.
     *
     * @return ?string[]
     */
    public static function wantedLabels(string $status): ?array
    {
        return Constants::CODECHECK_REGISTER_STATUS_LABELS[$status] ?? null;
    }

    /**
     * Which managed labels to add and remove, given what the issue carries now.
     *
     * Pure, so the rule is tested without GitHub. It walks
     * `Constants::CODECHECK_REGISTER_MANAGED_LABELS` rather than the labels on
     * the issue, which is what keeps a label the plugin does not own out of
     * `remove` (#174).
     *
     * @param ?string[] $wanted The labels that belong on the issue, or `null`
     *                           for a status with no mapping, which changes none
     * @param string[] $currentLabels The labels on the issue now
     *
     * @return array{add: string[], remove: string[]}
     */
    public static function labelChanges(?array $wanted, array $currentLabels): array
    {
        $changes = ['add' => [], 'remove' => []];

        if ($wanted === null) {
            return $changes;
        }

        foreach (Constants::CODECHECK_REGISTER_MANAGED_LABELS as $label) {
            $isWanted = in_array($label, $wanted, true);
            $isPresent = in_array($label, $currentLabels, true);

            if ($isWanted && !$isPresent) {
                $changes['add'][] = $label;
            } elseif (!$isWanted && $isPresent) {
                $changes['remove'][] = $label;
            }
        }

        return $changes;
    }

    /**
     * Say on the issue's timeline that the status changed.
     */
    private static function comment(
        CodecheckGithubRegisterApiClient $client,
        int $issueNumber,
        int $submissionId,
        string $status,
        ?array $codecheckers,
        CodecheckPostOrigin $origin
    ): void {
        try {
            $body = self::body($status, $origin, $codecheckers);
            if ($body === null) {
                return;
            }

            $client->commentOnIssue($issueNumber, $body);

            CodecheckLogger::info(
                "Commented the CODECHECK status on register issue #{$issueNumber} for submission {$submissionId}."
            );
        } catch (\Throwable $e) {
            self::failed('Could not comment the CODECHECK status on the register issue: ' . $e->getMessage());
        }
    }

    /**
     * Bring the labels the plugin owns into line with the status, one at a time.
     *
     * The labels are diffed against the **current** status rather than the one
     * being recorded, so two recordings that interleave settle on the newest
     * row instead of on whichever request finished last. The comment above says
     * what was recorded; the labels say where the check stands now.
     *
     * Every write stands alone: a refusal on one must not abandon the others,
     * or an issue is left saying both that it needs a codechecker and that one
     * is working on it — the state #174 was filed about. Removals go first, so
     * a half-done sync errs towards saying too little rather than the two at
     * once.
     */
    private static function syncLabels(
        CodecheckGithubRegisterApiClient $client,
        int $issueNumber,
        int $submissionId,
        string $status
    ): void {
        try {
            $current = CodecheckStatusHandler::getCurrentStatusData($submissionId)->status ?? $status;
            $wanted = self::wantedLabels($current);

            if ($wanted === null) {
                CodecheckLogger::warning(
                    "No register labels are defined for the CODECHECK status {$current}, so none were changed."
                );
                return;
            }

            $changes = self::labelChanges($wanted, $client->getIssueLabels($issueNumber));

            if (empty($changes['add']) && empty($changes['remove'])) {
                return;
            }

            $removed = [];
            $added = [];

            foreach ($changes['remove'] as $label) {
                try {
                    $client->removeLabelFromIssue($issueNumber, $label);
                    $removed[] = $label;
                } catch (\Throwable $e) {
                    self::failed(
                        "Could not remove the register label '{$label}': " . $e->getMessage()
                    );
                }
            }

            try {
                $client->addLabelsToIssue($issueNumber, $changes['add']);
                $added = $changes['add'];
            } catch (\Throwable $e) {
                self::failed(
                    'Could not add the register labels [' . implode(', ', $changes['add']) . ']: ' . $e->getMessage()
                );
            }

            CodecheckLogger::info(
                "Register issue #{$issueNumber} for submission {$submissionId}: added ["
                . implode(', ', $added) . '], removed [' . implode(', ', $removed) . '].'
            );
        } catch (\Throwable $e) {
            self::failed('Could not update the labels of the register issue: ' . $e->getMessage());
        }
    }

    /**
     * The recorded register issue number, but only when the recorded issue is in
     * the register the journal is configured with now.
     *
     * A check that has not had an identifier reserved has no issue to comment on,
     * which is the ordinary case early on rather than a failure.
     *
     * **The number alone is not an address.** A journal that moves from a testing
     * register to the production one — the transition `dev/live-register-tests.md`
     * describes — keeps the old issue number on its submissions, and issue #12 in
     * the new register belongs to somebody else's check. Commenting there was
     * wrong already (#150); labelling and *un*labelling it is worse (#174). The
     * stored URL says which repository the number belongs to, so it is what
     * decides.
     */
    private static function issueNumberInRegister(
        int $submissionId,
        ?string $storedIssue,
        string $organization,
        string $repository
    ): ?int {
        $issue = json_decode($storedIssue ?? '', true);

        if (!is_array($issue)) {
            return null;
        }

        $number = $issue['number'] ?? null;

        if (!is_numeric($number)) {
            return null;
        }

        if (!self::issueUrlIsInRegister($issue['url'] ?? null, $organization, $repository)) {
            CodecheckLogger::info(
                'The register issue recorded for submission ' . $submissionId . ' (' . ($issue['url'] ?? 'no URL')
                . ") is not in the configured register {$organization}/{$repository}, so it was left alone."
            );

            return null;
        }

        return (int) $number;
    }

    /**
     * Whether a recorded issue URL points into the configured register.
     *
     * A record from before the URL was stored has none; it is accepted, because
     * refusing would stop the plugin updating issues it legitimately opened.
     */
    public static function issueUrlIsInRegister(mixed $url, string $organization, string $repository): bool
    {
        if (!is_string($url) || $url === '') {
            return true;
        }

        return str_contains($url, "/{$organization}/{$repository}/");
    }

    /**
     * The comment: the status as one translated sentence, and — when the
     * status assigns a codechecker — a line per codechecker saying who it is
     * (#186).
     *
     * A codechecker GitHub holds assigned is named and mentioned. One it does
     * not is named with where to reach the journal coordinating them: that is
     * the fallback for a codechecker the register cannot reach, and the line a
     * CODECHECK editor needs in order to ask. Such a codechecker is not
     * mentioned, since a username GitHub would not assign may not be theirs.
     *
     * @param ?array{assigned: array, unassigned: array} $codecheckers
     */
    public static function body(string $status, CodecheckPostOrigin $origin, ?array $codecheckers = null): ?string
    {
        $translated = __($status);

        // __() renders a key it cannot resolve as ##the.key##. Publishing that
        // into the register would be worse than saying nothing, so say nothing.
        if ($translated === '' || str_starts_with($translated, '##')) {
            CodecheckLogger::warning("Not commenting an unresolvable CODECHECK status on the register: {$status}");
            return null;
        }

        $lines = [__('plugins.generic.codecheck.register.issue.statusComment', [
            'status' => $translated,
        ])];

        foreach ($codecheckers['assigned'] ?? [] as $entry) {
            $lines[] = __('plugins.generic.codecheck.register.issue.codecheckerAssigned', [
                'codechecker' => RegisterCodecheckers::describe($entry),
            ]);
        }

        foreach ($codecheckers['unassigned'] ?? [] as $entry) {
            $lines[] = __('plugins.generic.codecheck.register.issue.codecheckerViaJournal', [
                'codechecker' => RegisterCodecheckers::describe($entry, false),
                'journal' => CodecheckPostOrigin::escapeMarkdown($origin->getJournalName()),
                'contactUrl' => $origin->contactUrl(),
            ]);
        }

        return implode("\n\n", $lines);
    }
}
