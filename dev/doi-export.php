<?php

/**
 * @file dev/doi-export.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * Writes the Crossref and DataCite records OJS would deposit for some articles
 * to files, validated as OJS validates them: `make doi-export`. For looking at
 * what the CODECHECK links (#19) add, after `make doi-test-config`.
 *
 * OJS 3.5's Crossref and DataCite exporters refuse its command-line tool
 * (`supportsCLI()` answers false), so this boots OJS the way that tool does
 * and calls the exporters' own `exportXML()`. Booted like that, OJS loads
 * every generic plugin with no journal — as a deposit job in a CLI worker
 * does — which is the case the CODECHECK hooks are registered for. Nothing is
 * deposited: `exportXML()` only builds and validates the record.
 *
 * Usage: OJS_ROOT=… php dev/doi-export.php <journal> <output dir> <submission id>…
 */

use APP\facades\Repo;
use PKP\plugins\PluginRegistry;

$ojsRoot = getenv('OJS_ROOT') ?: dirname(__DIR__, 4);
[, $journalPath, $outputDir] = $argv + [null, null, null];
$submissionIds = array_map('intval', array_slice($argv, 3));
if (!$journalPath || !$outputDir || $submissionIds === []) {
    fwrite(STDERR, "Usage: OJS_ROOT=… php dev/doi-export.php <journal> <output dir> <submission id>…\n");
    exit(1);
}
$outputDir = realpath($outputDir) ?: $outputDir;

require $ojsRoot . '/tools/bootstrap.php';
new \PKP\cliTool\CommandLineTool([]);

$context = \APP\core\Application::getContextDAO()->getByPath($journalPath);
if (!$context) {
    fwrite(STDERR, "No journal with the path '{$journalPath}'.\n");
    exit(1);
}

$submissions = array_values(array_filter(array_map(fn (int $id) => Repo::submission()->get($id), $submissionIds)));

/** Print what libxml reported, as OJS's export screen would show it. */
$report = function (string $format, ?array $errors): bool {
    $errors = array_filter($errors ?? [], fn ($error) => $error->level >= LIBXML_ERR_ERROR);
    if ($errors === []) {
        echo "{$format}: valid\n";
        return true;
    }
    echo "{$format}: INVALID\n";
    foreach ($errors as $error) {
        echo '  line ' . $error->line . ': ' . trim($error->message) . "\n";
    }
    return false;
};

$valid = true;

$crossref = PluginRegistry::getPlugin('importexport', 'CrossrefExportPlugin');
// OJS reads libxml's error buffer without ever clearing it, so each export
// starts from an empty one or reports the previous export's errors as its own.
libxml_clear_errors();
$errors = [];
$xml = $crossref->exportXML($submissions, $crossref->getSubmissionFilter(), $context, false, $errors);
file_put_contents($outputDir . '/crossref.xml', $xml);
$valid = $report('Crossref ' . $outputDir . '/crossref.xml', $errors) && $valid;

// DataCite records one article per document.
$datacite = PluginRegistry::getPlugin('importexport', 'DataciteExportPlugin');
foreach ($submissions as $submission) {
    libxml_clear_errors();
    $errors = [];
    $file = $outputDir . '/datacite-' . $submission->getId() . '.xml';
    file_put_contents($file, $datacite->exportXML($submission, $datacite->getSubmissionFilter(), $context, false, $errors));
    $valid = $report('DataCite ' . $file, $errors) && $valid;
}

exit($valid ? 0 : 2);
