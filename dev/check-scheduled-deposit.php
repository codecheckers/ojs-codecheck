<?php

/**
 * @file dev/check-scheduled-deposit.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * Checks that the register deposit runs when OJS's scheduled task publishes an
 * article (#188): `make check-scheduled-deposit THROWAWAY=<name>`.
 *
 * The scheduled task (`PublishSubmissions`) runs on the command line, where
 * OJS loads every generic plugin with no journal. There the deposit hook was
 * attached only if the site-level enabled check said so, and it took its
 * journal from the request, which has none: every scheduled article was
 * published without its `register.csv` row.
 *
 * This boots OJS as a command-line tool does, schedules an opted-in published
 * article for yesterday, runs the task's own `executeActions()` and reads what
 * the plugin logged. It makes no call to GitHub: the dataset marks no
 * repository as holding the `codecheck.yml`, so the deposit stops at that
 * check and logs it, naming the submission — which it can only reach having
 * been attached, found the article's journal, seen the deposit switched on and
 * read the opt-in. Everything after that check is the same code the web path
 * runs.
 *
 * Writes to the database it is pointed at, so run it against a throwaway
 * instance, never the shared one. It takes a published article and puts it
 * back as published, with its original publication date, whatever the task
 * does. It refuses to run where the deposit could reach GitHub: an article
 * marking a repository as holding the `codecheck.yml`, or another article
 * already scheduled, which the task would publish too.
 *
 * Usage: OJS_ROOT=… php dev/check-scheduled-deposit.php [journal] [submission id]
 */

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\submission\PKPSubmission;
use PKP\task\PublishSubmissions;

$ojsRoot = getenv('OJS_ROOT') ?: dirname(__DIR__, 4);
if (!is_file($ojsRoot . '/tools/bootstrap.php')) {
    fwrite(STDERR, "No OJS installation at {$ojsRoot}; set OJS_ROOT.\n");
    exit(1);
}

$journalPath = $argv[1] ?? 'codecheck';
$submissionId = (int) ($argv[2] ?? 5);

// What the plugin logs goes to a file of our own, to be read back below.
$log = tempnam(sys_get_temp_dir(), 'codecheck-scheduled-deposit-');
ini_set('error_log', $log);

require $ojsRoot . '/tools/bootstrap.php';
// As every command-line tool, the scheduler included: the generic plugins
// are loaded with no journal.
new \PKP\cliTool\CommandLineTool([]);

$context = \APP\core\Application::getContextDAO()->getByPath($journalPath);
if (!$context) {
    fwrite(STDERR, "No journal with the path '{$journalPath}'.\n");
    exit(1);
}

$submission = Repo::submission()->get($submissionId);
if (!$submission || (int) $submission->getData('contextId') !== (int) $context->getId()) {
    fwrite(STDERR, "No submission #{$submissionId} in '{$journalPath}'.\n");
    exit(1);
}
$publication = $submission->getCurrentPublication();
if ((int) $publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
    fwrite(STDERR, "Submission #{$submissionId} is not published; it has to start out published, which is how it is left.\n");
    exit(1);
}
$originalDate = $publication->getData('datePublished');

// The check stops at "no repository holds the codecheck.yml", before anything
// reaches GitHub. Refuse rather than assume: with one marked, the deposit
// would fetch the file and open a real pull request on the register. The task
// publishes every due article of every journal, so no other may be waiting.
$repositories = json_decode((string) DB::table('codecheck_metadata')->where('submission_id', $submissionId)->value('repository'), true);
if (array_filter($repositories['repositories'] ?? [], fn ($entry) => ($entry['containsCodecheckYaml'] ?? false) === true) !== []) {
    fwrite(STDERR, "Submission #{$submissionId} marks a repository as holding the codecheck.yml; the deposit would reach GitHub.\n");
    exit(1);
}
if (DB::table('submissions')->where('status', PKPSubmission::STATUS_SCHEDULED)->exists()) {
    fwrite(STDERR, "Another article is scheduled already; the task would publish it too.\n");
    exit(1);
}

// Scheduled for yesterday, as an editor scheduling an article in an issue
// leaves it: due, so the task publishes it. Written to the tables directly:
// OJS 3.5's `unpublish()` reads the journal from the request and fails on the
// command line (its `publish()` does not, which is what lets the task run).
DB::table('publications')->where('publication_id', $publication->getId())->update([
    'status' => PKPSubmission::STATUS_SCHEDULED,
    'date_published' => date('Y-m-d', strtotime('-1 day')),
]);
DB::table('submissions')->where('submission_id', $submissionId)->update(['status' => PKPSubmission::STATUS_SCHEDULED]);
echo "Submission #{$submissionId} scheduled for yesterday\n";

$published = false;
try {
    (new PublishSubmissions())->executeActions();
    $published = (int) Repo::submission()->get($submissionId)->getCurrentPublication()->getData('status') === PKPSubmission::STATUS_PUBLISHED;
} finally {
    // Back as it was found, whatever the task did or threw.
    if (!$published) {
        Repo::publication()->publish(Repo::publication()->get($publication->getId()));
    }
    DB::table('publications')->where('publication_id', $publication->getId())->update(['date_published' => $originalDate]);
    echo "Submission #{$submissionId} " . ($published ? 'published by the task' : 'NOT published by the task; published again by hand') . ", date put back to {$originalDate}\n";
}

$logged = (string) file_get_contents($log);
unlink($log);

$deposit = array_values(array_filter(
    explode("\n", $logged),
    // The whole number: #5 must not be satisfied by #52.
    fn (string $line) => str_contains($line, 'CODECHECK Register Deposit:') && preg_match('/#' . $submissionId . '(?!\d)/', $line)
));

if (!$published || $deposit === []) {
    echo "FAIL: the scheduled task published submission #{$submissionId} without the register deposit running.\n";
    echo "What the plugin logged:\n" . ($logged === '' ? "(nothing)\n" : $logged);
    exit(1);
}

echo "PASS: the register deposit ran for the scheduled article:\n  " . trim($deposit[0]) . "\n";
