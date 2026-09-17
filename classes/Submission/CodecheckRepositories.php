<?php
/**
 * @file classes/Submission/CodecheckRepositories.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
 * Note the deliberate asymmetry between the two questions this class answers:
 * `publicEntries()` withholds hidden repositories because it feeds what readers
 * see, while `selectedUrl()` does not, because a repository may be kept private
 * and still be the one carrying the `codecheck.yml` that has to be fetched and
 * deposited.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

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
     * The address of the repository holding the `codecheck.yml`, hidden or not.
     */
    public static function selectedUrl(mixed $repositoryData): ?string
    {
        foreach (self::entries($repositoryData) as $entry) {
            if (empty($entry['containsCodecheckYaml'])) {
                continue;
            }

            $url = trim((string) ($entry['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
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
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
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
