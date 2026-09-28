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
 *   saying it changed (#150), and the labels that say where the check stands
 *   (#174).
 *
 * Two halves of one idea, in one class because they need the same four things
 * resolved — the journal's "update status" choice, the credentials, the register
 * and the issue number — and a second resolver would be a second reader of all
 * four. Each half is attempted independently, so a refused comment still brings
 * the labels with it.
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
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use Illuminate\Support\Facades\DB;
use PKP\plugins\PluginRegistry;

class CodecheckStatusRegisterUpdate
{
    /**
     * Bring the submission's register issue up to date with the new status, if
     * there is an issue to bring up to date.
     *
     * @param string $status The status as a locale key, as stored.
     */
    public static function apply(int $submissionId, string $status): void
    {
        try {
            $plugin = PluginRegistry::getPlugin('generic', 'codecheckplugin');
            $context = Application::get()->getRequest()->getContext();

            if (!$plugin || !$context) {
                return;
            }

            $contextId = $context->getId();

            // The journal chose whether the register issue reflects the status at
            // all. If it does not, a comment saying it changed would be noise in
            // someone else's repository.
            $updateFields = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_FIELDS);
            if (!is_array($updateFields)
                || !in_array(Constants::CODECHECK_GITHUB_REGISTER_ISSUE_UPDATE_STATUS, $updateFields)) {
                return;
            }

            $token = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN);
            $organization = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION);
            $repository = $plugin->getSetting($contextId, Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY);

            if (empty($token) || empty($organization) || empty($repository)) {
                return;
            }

            $issueNumber = self::issueNumberInRegister($submissionId, $organization, $repository);
            if ($issueNumber === null) {
                return;
            }

            $client = new CodecheckGithubRegisterApiClient(
                $token,
                $organization,
                $repository,
                (string) $submissionId,
                $context
            );

            // Independent halves: the labels are the part a reader of the
            // register filters on, so a refused comment must not cost them.
            self::comment($client, $issueNumber, $submissionId, $status);
            self::syncLabels($client, $issueNumber, $submissionId, $status);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not carry the CODECHECK status to the register issue: ' . $e->getMessage());
        }
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
        string $status
    ): void {
        try {
            $body = self::body($status);
            if ($body === null) {
                return;
            }

            $client->commentOnIssue($issueNumber, $body);

            CodecheckLogger::info(
                "Commented the CODECHECK status on register issue #{$issueNumber} for submission {$submissionId}."
            );
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not comment the CODECHECK status on the register issue: ' . $e->getMessage());
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
                    CodecheckLogger::warning(
                        "Could not remove the register label '{$label}': " . $e->getMessage()
                    );
                }
            }

            try {
                $client->addLabelsToIssue($issueNumber, $changes['add']);
                $added = $changes['add'];
            } catch (\Throwable $e) {
                CodecheckLogger::warning(
                    'Could not add the register labels [' . implode(', ', $changes['add']) . ']: ' . $e->getMessage()
                );
            }

            CodecheckLogger::info(
                "Register issue #{$issueNumber} for submission {$submissionId}: added ["
                . implode(', ', $added) . '], removed [' . implode(', ', $removed) . '].'
            );
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not update the labels of the register issue: ' . $e->getMessage());
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
        string $organization,
        string $repository
    ): ?int {
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first(['issue']);

        $issue = json_decode($metadata->issue ?? '', true);

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
     * The comment, as one translated sentence.
     */
    private static function body(string $status): ?string
    {
        $translated = __($status);

        // __() renders a key it cannot resolve as ##the.key##. Publishing that
        // into the register would be worse than saying nothing, so say nothing.
        if ($translated === '' || str_starts_with($translated, '##')) {
            CodecheckLogger::warning("Not commenting an unresolvable CODECHECK status on the register: {$status}");
            return null;
        }

        return __('plugins.generic.codecheck.register.issue.statusComment', [
            'status' => $translated,
        ]);
    }
}
