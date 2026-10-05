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
 * API and does not count against its unauthenticated 60 requests an hour, by
 * the scheduled refresh (#65), and kept in Laravel's cache. A lookup reads them
 * itself only when nothing is cached: before the first refresh, after the
 * cache was cleared, or where the scheduler never runs. A list that cannot be
 * read means no suggestion from it, never an error, because the dialog works
 * without one.
 */

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Tasks\RefreshCodecheckLists;
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

    /**
     * The scheduled refresh's read is kept far longer than the refresh
     * interval, so the next refresh replaces it rather than it expiring.
     */
    private const REFRESH_CACHE_SECONDS = 30 * 24 * 60 * 60;

    /** A lookup's own read, where the scheduler did not provide one. */
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
     * ORCID iD → GitHub username, from the cache or, when nothing is cached,
     * from the lists themselves.
     *
     * A read that failed in part keeps what did load for a few minutes only:
     * long enough that an outage costs one timeout per few minutes rather than
     * one per editor leaving the ORCID field, short enough that the missing
     * list is soon asked for again.
     *
     * @return array<string, string>
     */
    private static function usernamesByOrcid(): array
    {
        $cached = self::cached();
        if ($cached !== null) {
            return $cached;
        }

        ['usernames' => $usernames, 'complete' => $complete] = self::read();
        self::cache($usernames, $complete ? self::CACHE_SECONDS : self::RETRY_SECONDS);

        return $usernames;
    }

    /**
     * Reads the lists again and caches them, for the scheduled refresh. Only
     * a complete read replaces what is cached: a partial one would trade a
     * whole list for part of it until the next refresh.
     *
     * @return bool whether every list was read
     */
    public static function refresh(): bool
    {
        ['usernames' => $usernames, 'complete' => $complete] = self::read();
        if ($complete) {
            self::cache($usernames, self::REFRESH_CACHE_SECONDS);
        }

        return $complete;
    }

    /** @return array<string, string>|null */
    private static function cached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            return is_array($cached) ? $cached : null;
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not read the cached CODECHECK community lists: ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<string, string> $usernames */
    private static function cache(array $usernames, int $seconds): void
    {
        try {
            Cache::put(self::CACHE_KEY, $usernames, $seconds);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not cache the CODECHECK community lists: ' . $e->getMessage());
        }
    }

    /**
     * Reads all three lists at once, so a read costs the slowest list rather
     * than the sum of them.
     *
     * @return array{usernames: array<string, string>, complete: bool}
     */
    private static function read(): array
    {
        // Not in OJS's sandbox mode; the dialog then suggests no username.
        if (!RefreshCodecheckLists::listsReadable()) {
            return ['usernames' => [], 'complete' => false];
        }

        $usernames = [];
        $complete = true;
        try {
            $client = Application::get()->getHttpClient();
            $results = Utils::settle(array_map(
                fn (string $url) => $client->requestAsync('GET', $url, ['timeout' => RefreshCodecheckLists::READ_TIMEOUT_SECONDS]),
                self::LIST_URLS
            ))->wait();

            foreach ($results as $result) {
                if ($result['state'] !== PromiseInterface::FULFILLED) {
                    $complete = false;
                    CodecheckLogger::warning('Could not read a CODECHECK community list of codecheckers: ' . $result['reason']->getMessage());
                    continue;
                }
                $parsed = self::parse((string) $result['value']->getBody());
                if ($parsed === []) {
                    CodecheckLogger::warning('A CODECHECK community list of codecheckers had no codechecker in it.');
                }
                $usernames += $parsed;
            }
            // Lists that answer but yield nobody at all — renamed columns, error
            // pages served with 200 — are not a read, or a refresh would replace
            // a good copy with an empty one. One emptied list is just empty.
            if ($usernames === []) {
                $complete = false;
            }
        } catch (\Throwable $e) {
            $complete = false;
            CodecheckLogger::warning('Could not read the CODECHECK community lists of codecheckers: ' . $e->getMessage());
        }

        return ['usernames' => $usernames, 'complete' => $complete];
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
