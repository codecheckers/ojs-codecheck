<?php

/**
 * @file dev/doi-test-config.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * Configures a development journal for DOI deposits, so the CODECHECK links
 * (#19) can be seen in a Crossref or DataCite export: `make doi-test-config`.
 *
 * - switches DOIs on for articles, with the prefix 10.5555 — the one Crossref's
 *   documentation uses in its examples, which Crossref refuses to register;
 * - enables the Crossref and DataCite plugins in test mode, with credentials
 *   that are deliberately not real, so nothing can be deposited by accident.
 *   DataCite's test DOI prefix is 10.5072, its retired test prefix: test mode
 *   rewrites every DOI to it, which is the case the plugin must not look
 *   articles up by DOI for;
 * - makes one of the two the journal's registration agency (`AGENCY=…`), and
 *   gives the journal a publisher if it has none: Crossref requires one;
 * - gives every published article a DOI, 10.5555/codecheck-demo.<id>, marked
 *   registered with `REGISTERED=1` so that a change of the CODECHECK links can
 *   be seen marking it stale.
 *
 * Writes to the database it is pointed at, so run it against a throwaway
 * instance (`make throwaway-up`), never the shared one. Idempotent: an article
 * that already has a DOI keeps it.
 *
 * With `CERTIFICATES="2 7"`, it also prepares those submissions for the
 * CODECHECK side: their certificate is recorded as published (a status row
 * written directly — recording it through the plugin would comment on the
 * register issue on GitHub), the journal switches *Add CODECHECK links to DOI
 * deposits* on, and lists certificates among the references by button (#183),
 * with the journal's own References switch on so they can be seen.
 *
 * Usage: OJS_ROOT=… php dev/doi-test-config.php [journal] [crossref|datacite] [registered] [submission id…]
 */

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\doi\Doi;
use PKP\plugins\PluginRegistry;
use PKP\submission\PKPSubmission;

const DOI_PREFIX = '10.5555';
const DATACITE_TEST_PREFIX = '10.5072';
const AGENCIES = ['crossref' => 'crossrefplugin', 'datacite' => 'dataciteplugin'];

$ojsRoot = getenv('OJS_ROOT') ?: dirname(__DIR__, 4);
if (!is_file($ojsRoot . '/tools/bootstrap.php')) {
    fwrite(STDERR, "No OJS installation at {$ojsRoot}; set OJS_ROOT.\n");
    exit(1);
}

$journalPath = $argv[1] ?? 'codecheck';
$agency = $argv[2] ?? 'crossref';
$registered = in_array($argv[3] ?? '', ['1', 'registered', 'yes'], true);
$certificates = array_map('intval', array_slice($argv, 4));
if (!isset(AGENCIES[$agency])) {
    fwrite(STDERR, "Unknown agency '{$agency}'; use crossref or datacite.\n");
    exit(1);
}

require $ojsRoot . '/tools/bootstrap.php';
// Sets up the request router and loads the generic plugins, as every OJS
// command-line tool does; plugins cannot register without the router.
new \PKP\cliTool\CommandLineTool([]);

$contextDao = Application::getContextDAO();
$context = $contextDao->getByPath($journalPath);
if (!$context) {
    fwrite(STDERR, "No journal with the path '{$journalPath}'.\n");
    exit(1);
}
$contextId = (int) $context->getId();

// The two agencies, in test mode with credentials nothing will accept.
$pluginSettings = [
    'crossrefplugin' => [
        'enabled' => true,
        'depositorName' => 'CODECHECK Demo Journal (test)',
        'depositorEmail' => 'doi-test@example.org',
        'username' => 'not-a-real-crossref-account',
        'password' => 'not-a-real-password',
        'testMode' => true,
        'automaticRegistration' => false,
    ],
    'dataciteplugin' => [
        'enabled' => true,
        'username' => 'NOT.A.REAL.ACCOUNT',
        'password' => 'not-a-real-password',
        'testUsername' => 'NOT.A.REAL.TEST.ACCOUNT',
        'testPassword' => 'not-a-real-password',
        'testDOIPrefix' => DATACITE_TEST_PREFIX,
        'testMode' => true,
        'automaticRegistration' => false,
    ],
];
foreach ($pluginSettings as $pluginName => $settings) {
    $plugin = PluginRegistry::getPlugin('generic', $pluginName);
    if (!$plugin) {
        fwrite(STDERR, "The {$pluginName} plugin is not installed.\n");
        exit(1);
    }
    foreach ($settings as $name => $value) {
        $plugin->updateSetting($contextId, $name, $value);
    }
    echo "Configured {$pluginName} in test mode\n";
}

// DOIs for articles, deposited by the chosen agency.
$context->setData(Context::SETTING_ENABLE_DOIS, true);
$context->setData(Context::SETTING_DOI_PREFIX, DOI_PREFIX);
$context->setData(Context::SETTING_ENABLED_DOI_TYPES, [Repo::doi()::TYPE_PUBLICATION]);
$context->setData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, AGENCIES[$agency]);
$context->setData(Context::SETTING_DOI_AUTOMATIC_DEPOSIT, false);
// Crossref's `registrant`, which its schema requires and the dataset leaves empty.
if (!$context->getData('publisherInstitution')) {
    $context->setData('publisherInstitution', 'CODECHECK Demo Publisher (test)');
}
$contextDao->updateObject($context);
echo 'DOIs on for articles, prefix ' . DOI_PREFIX . ", registration agency {$agency}\n";

// A DOI for every published article that has none.
$publications = DB::table('publications as p')
    ->join('submissions as s', 's.submission_id', '=', 'p.submission_id')
    ->where('s.context_id', $contextId)
    ->where('p.status', PKPSubmission::STATUS_PUBLISHED)
    ->get(['p.publication_id', 'p.submission_id', 'p.doi_id']);

// Every version of an article shares the article's DOI.
$doiIds = [];
foreach ($publications as $row) {
    $doiId = $row->doi_id ?: ($doiIds[$row->submission_id] ?? null);
    if (!$doiId) {
        $doi = Repo::doi()->newDataObject([
            'doi' => DOI_PREFIX . '/codecheck-demo.' . $row->submission_id,
            'contextId' => $contextId,
        ]);
        $doiId = Repo::doi()->add($doi);
    }
    if (!$row->doi_id) {
        DB::table('publications')->where('publication_id', $row->publication_id)->update(['doi_id' => $doiId]);
    }
    $doiIds[$row->submission_id] = $doiId;
    if ($registered) {
        DB::table('dois')->where('doi_id', $doiId)->update(['status' => Doi::STATUS_REGISTERED]);
    }
    $value = DB::table('dois')->where('doi_id', $doiId)->value('doi');
    echo "Submission {$row->submission_id}: {$value}" . ($registered ? ' (registered)' : '') . "\n";
}

if ($certificates === []) {
    exit(0);
}

// The CODECHECK side: published certificates, and the journal asking for the
// links and the reference.
$codecheck = PluginRegistry::getPlugin('generic', 'codecheckplugin');
$codecheck->updateSetting($contextId, 'codecheckDoiDepositLinks', true);
$codecheck->updateSetting($contextId, 'codecheckDoiRedeposit', true);
$codecheck->updateSetting($contextId, 'codecheckCertificateReference', 'button');
$context = $contextDao->getById($contextId);
$context->setData('citations', 'enable');
$contextDao->updateObject($context);
echo "CODECHECK links in DOI deposits on, re-deposit on, certificate reference by button, References on\n";

foreach ($certificates as $submissionId) {
    DB::table('codecheck_status')->insert([
        'submission_id' => $submissionId,
        'status' => 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => -1,
    ]);
    echo "Submission {$submissionId}: certificate recorded as published\n";
}
