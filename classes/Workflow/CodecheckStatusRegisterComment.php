<?php
/**
 * @file classes/Workflow/CodecheckStatusRegisterComment.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckStatusRegisterComment
 * @brief Records a CODECHECK status change as a comment on the register issue.
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

class CodecheckStatusRegisterComment
{
    /**
     * Comment on the submission's register issue, if there is one to comment on.
     *
     * @param string $status The status as a locale key, as stored.
     */
    public static function post(int $submissionId, string $status): void
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

            $issueNumber = self::issueNumber($submissionId);
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

            $client->commentOnIssue($issueNumber, self::body($status));

            CodecheckLogger::info(
                "Commented the CODECHECK status on register issue #{$issueNumber} for submission {$submissionId}."
            );
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not comment the CODECHECK status on the register issue: ' . $e->getMessage());
        }
    }

    /**
     * The register issue number recorded for this submission, if any.
     *
     * A check that has not had an identifier reserved has no issue to comment on,
     * which is the ordinary case early on rather than a failure.
     */
    private static function issueNumber(int $submissionId): ?int
    {
        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first(['issue']);

        $issue = json_decode($metadata->issue ?? '', true);
        $number = is_array($issue) ? ($issue['number'] ?? null) : null;

        return is_numeric($number) ? (int) $number : null;
    }

    /**
     * The comment, as one translated sentence.
     */
    private static function body(string $status): string
    {
        return __('plugins.generic.codecheck.register.issue.statusComment', [
            'status' => __($status),
        ]);
    }
}
