<?php
/**
 * @file classes/Submission/CodecheckRepositories.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckRepositories
 * @brief The rules for reading the `repository` blob of `codecheck_metadata`.
 *
 * The blob is `{"repositories": [{url, hidden, providedByAuthor,
 * containsCodecheckYaml}, …]}`. Which entry holds the `codecheck.yml` used to be
 * a separate index into that list, and nothing kept the two in step — issue #154.
 * The flag now travels on the entry, and the rules for reading it live here so
 * that the article page, the publication validator and the register deposit
 * cannot drift apart again, which is the defect in a different costume.
 *
 * Note the deliberate asymmetry between the questions this class answers:
 * `publicEntries()` withholds hidden repositories because it feeds what readers
 * see, while `selectedUrl()` does not, because a repository may be kept private
 * and still be the one carrying the `codecheck.yml` that has to be fetched.
 *
 * Fetching is not publishing, though: `publicSelectedUrl()` is the third
 * question, asked by anything that writes the chosen repository somewhere
 * public. The register deposit used to ask `selectedUrl()` and commit the
 * answer to a public pull request, so a repository marked "Keep private" was
 * withheld from the article page, the issue table of contents, the generated
 * `codecheck.yml` and the register issue — and then named in `register.csv`
 * (issue #169).
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\Constants;

class CodecheckRepositories
{
    /**
     * The entries a reader may see: not hidden, and with an address.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicEntries(mixed $repositoryData): array
    {
        $entries = [];

        foreach (self::entries($repositoryData) as $entry) {
            if (empty($entry['hidden']) && ($entry['url'] ?? '') !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * The entry holding the `codecheck.yml`: the first one flagged that has an
     * address.
     *
     * Every question below is asked of *this* entry rather than re-scanned with
     * an extra clause. Scanning twice with different clauses is how a filtered
     * pass can answer with a repository the unfiltered pass did not choose —
     * `withOneMarked()` keeps the choice exclusive on the way in, but it guards
     * one write path, and a blob with two flags would otherwise have the
     * register deposit name a repository nobody checked.
     *
     * @return array<string, mixed>|null
     */
    private static function selectedEntry(mixed $repositoryData): ?array
    {
        foreach (self::entries($repositoryData) as $entry) {
            if (empty($entry['containsCodecheckYaml'])) {
                continue;
            }

            if (trim((string) ($entry['url'] ?? '')) !== '') {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The address of the repository holding the `codecheck.yml`, hidden or not.
     */
    public static function selectedUrl(mixed $repositoryData): ?string
    {
        $entry = self::selectedEntry($repositoryData);

        return $entry === null ? null : trim((string) $entry['url']);
    }

    /**
     * The address of the repository holding the `codecheck.yml` that a reader
     * may also see.
     *
     * This is what belongs in anything public — the `Repository` column of the
     * `register.csv` row above all, which is committed to a public pull request
     * against the CODECHECK Register. A private repository cannot stand there,
     * and no other repository may stand in for it either: the register row
     * would then name something nobody checked. Publication is blocked instead
     * (issue #169).
     *
     * Note this asks whether *the* chosen repository is public, and never which
     * public repository could stand in for it.
     */
    public static function publicSelectedUrl(mixed $repositoryData): ?string
    {
        $entry = self::selectedEntry($repositoryData);

        return $entry === null || !empty($entry['hidden']) ? null : trim((string) $entry['url']);
    }

    /**
     * Whether the repository holding the `codecheck.yml` is one no reader may
     * see — as opposed to there being none marked at all, which is a different
     * complaint with a different remedy.
     *
     * Both the publication gate and the register deposit have to tell those two
     * apart, so the distinction lives here rather than as a pair of calls at
     * each of them (issue #169).
     */
    public static function selectedIsPrivate(mixed $repositoryData): bool
    {
        $entry = self::selectedEntry($repositoryData);

        return $entry !== null && !empty($entry['hidden']);
    }

    /**
     * The addresses that are not usable as repository links.
     *
     * A repository URL is written into the public article page, into the
     * `register.csv` deposit and into a public GitHub issue, and nothing
     * validated it on the way in — so `javascript:` and friends could be stored
     * and then published (Issue #154). `filter_var(…, FILTER_VALIDATE_URL)` is
     * not enough on its own: it accepts `javascript://…`.
     *
     * @return array<int, string>
     */
    public static function unusableUrls(mixed $repositoryData): array
    {
        $unusable = [];

        foreach (self::entries($repositoryData) as $entry) {
            $url = trim((string) ($entry['url'] ?? ''));
            if ($url !== '' && !Constants::isWebUrl($url)) {
                $unusable[] = $url;
            }
        }

        return $unusable;
    }

    /**
     * The blob with at most one entry flagged.
     *
     * Only the editorial form enforces that one repository is chosen, and it is
     * the browser doing it; what arrives at the API is whatever was sent. Two
     * flagged entries would have the article page marking both while the
     * register deposited one, so the extra flags are cleared on the way in.
     */
    public static function withOneMarked(mixed $repositoryData): mixed
    {
        if (!is_array($repositoryData)) {
            return $repositoryData;
        }

        $key = is_array($repositoryData['repositories'] ?? null) ? 'repositories' : null;
        $list = $key === null ? $repositoryData : $repositoryData['repositories'];

        if (!is_array($list)) {
            return $repositoryData;
        }

        $seen = false;
        foreach ($list as $index => $entry) {
            if (!is_array($entry) || empty($entry['containsCodecheckYaml'])) {
                continue;
            }

            // An entry with no address cannot be the one holding the file: every
            // reader skips it, so keeping the flag there would show the editor a
            // selection that publication validation then says is missing.
            $usable = !$seen && trim((string) ($entry['url'] ?? '')) !== '';
            $list[$index]['containsCodecheckYaml'] = $usable;
            $seen = $seen || $usable;
        }

        if ($key === null) {
            return $list;
        }

        $repositoryData['repositories'] = $list;

        return $repositoryData;
    }

    /**
     * The addresses a reader may see.
     *
     * @return array<int, string>
     */
    public static function publicUrls(mixed $repositoryData): array
    {
        return array_column(self::publicEntries($repositoryData), 'url');
    }

    /**
     * The repository entries, whatever shape the list arrived in: the stored
     * blob as JSON, as a decoded array or as objects; a bare list of entries, as
     * the editorial form posts it; or a bare list of URL strings.
     *
     * The pre-#154 `repoWithCodecheckYaml` index is deliberately not read here.
     * The upgrade migration converts it, and accepting both shapes at once means
     * a half-converted record resolves differently depending on which reader
     * asks — which is the class of defect #154 is about.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function entries(mixed $repositoryData): array
    {
        if (is_string($repositoryData)) {
            $repositoryData = json_decode($repositoryData, true);
        }

        if (is_object($repositoryData)) {
            $repositoryData = json_decode(json_encode($repositoryData), true);
        }

        if (!is_array($repositoryData)) {
            return [];
        }

        $list = $repositoryData['repositories'] ?? $repositoryData;
        if (!is_array($list)) {
            return [];
        }

        $entries = [];
        foreach ($list as $entry) {
            if (is_string($entry)) {
                $entry = ['url' => $entry];
            }

            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
