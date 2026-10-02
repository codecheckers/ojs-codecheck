<?php

/**
 * @file classes/CodecheckRegister/CommunityCodecheckers.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CommunityCodecheckers
 *
 * @brief The CODECHECK community's own lists of codecheckers
 *   (`codecheckers/codecheckers`), read for one thing: which GitHub username
 *   belongs to an ORCID iD, so the "add codechecker" dialog can suggest it
 *   (#186).
 *
 * It is a suggestion and nothing more. The editor sees it and can change or
 * clear it, and nothing is assigned on the strength of it alone.
 *
 * The lists are read from `raw.githubusercontent.com`, which is not the GitHub
 * API and does not count against its unauthenticated 60 requests an hour, and
 * the result is kept for six hours — the venue labels' refresh interval — in
 * Laravel's cache. A list that cannot be read means no suggestion from it,
 * never an error, because the dialog works without one.
 */

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Cache;

class CommunityCodecheckers
{
    /**
     * The three lists the community keeps; each has `handle` and `ORCID`
     * columns. `HEAD` rather than a branch name: the repository's default
     * branch is `master`, and a guessed `main` answered 404 for every list.
     */
    public const LIST_URLS = [
        'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/codecheckers.csv',
        'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/agile-codecheckers.csv',
        'https://raw.githubusercontent.com/codecheckers/codecheckers/HEAD/institutional-codecheckers.csv',
    ];

    private const CACHE_KEY = 'codecheck-community-codecheckers';

    private const CACHE_SECONDS = 6 * 60 * 60;

    private const RETRY_SECONDS = 5 * 60;

    /**
     * The GitHub username the community lists give for an ORCID iD, if any.
     */
    public static function githubUsernameFor(string $orcid): ?string
    {
        $orcid = CodecheckCodecheckers::normalizeOrcid($orcid);
        if (!CodecheckCodecheckers::isOrcid($orcid)) {
            return null;
        }

        return self::usernamesByOrcid()[$orcid] ?? null;
    }

    /**
     * ORCID iD → GitHub username, from all three lists, fetched at once so a
     * cold cache costs the slowest list rather than the sum of them.
     *
     * A complete read is kept for six hours. One that failed in part keeps
     * what did load, for a few minutes only: long enough that an outage costs
     * one timeout per few minutes rather than one per editor leaving the ORCID
     * field, short enough that the missing list is soon asked for again.
     *
     * @return array<string, string>
     */
    private static function usernamesByOrcid(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not read the cached CODECHECK community lists: ' . $e->getMessage());
        }

        $usernames = [];
        $complete = true;
        try {
            $client = Application::get()->getHttpClient();
            $results = Utils::settle(array_map(
                fn (string $url) => $client->requestAsync('GET', $url, ['timeout' => 5]),
                self::LIST_URLS
            ))->wait();

            foreach ($results as $result) {
                if ($result['state'] === PromiseInterface::FULFILLED) {
                    $usernames += self::parse((string) $result['value']->getBody());
                } else {
                    $complete = false;
                    CodecheckLogger::warning('Could not read a CODECHECK community list of codecheckers: ' . $result['reason']->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $complete = false;
            CodecheckLogger::warning('Could not read the CODECHECK community lists of codecheckers: ' . $e->getMessage());
        }

        try {
            Cache::put(self::CACHE_KEY, $usernames, $complete ? self::CACHE_SECONDS : self::RETRY_SECONDS);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not cache the CODECHECK community lists: ' . $e->getMessage());
        }

        return $usernames;
    }

    /**
     * ORCID iD → GitHub username from one list.
     *
     * Columns are found by their header, since the three lists differ in
     * every other column. A row whose iD is not one (the lists write `NA`) or
     * whose handle is not a username is skipped; where an iD appears twice,
     * the first row wins.
     *
     * @return array<string, string>
     */
    public static function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        if ($lines === false || $lines === [] || $lines[0] === '') {
            return [];
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column)), str_getcsv(array_shift($lines), ',', '"', ''));
        $handleColumn = array_search('handle', $header, true);
        $orcidColumn = array_search('orcid', $header, true);
        if ($handleColumn === false || $orcidColumn === false) {
            return [];
        }

        $usernames = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '');
            $orcid = CodecheckCodecheckers::normalizeOrcid($row[$orcidColumn] ?? '');
            $handle = CodecheckCodecheckers::normalizeGithubUsername($row[$handleColumn] ?? '');

            if (CodecheckCodecheckers::isOrcid($orcid)
                && CodecheckCodecheckers::isGithubUsername($handle)
                && !isset($usernames[$orcid])) {
                $usernames[$orcid] = $handle;
            }
        }

        return $usernames;
    }
}
