<?php

/**
 * @file classes/Submission/CodecheckCodecheckers.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckCodecheckers
 *
 * @brief The rules for the `codecheckers` list of `codecheck_metadata`, and for
 *   the ORCID iDs and GitHub usernames in it.
 *
 * The list is `[{name, orcid, github}, …]`. The GitHub username is what the
 * register issue is assigned to (#186); entries written before it existed
 * have no `github` key and read as having no username. An ORCID iD used to be stored exactly as it
 * was typed, unchecked, and it reaches the generated `codecheck.yml`, the
 * article page and the public register from there — so this class is the same
 * arrangement the repository addresses already have (`CodecheckRepositories`,
 * `Constants::isWebUrl()`): one rule, applied at the write boundary, mirrored in
 * JavaScript by `resources/js/orcid.js` so the dialog can refuse a mistyped iD
 * before it is sent.
 *
 * **The ISO 7064 MOD 11-2 check digit is computed here, not delegated to
 * `PKP\validation\ValidatorORCID`,** although that is the same algorithm and
 * reusing it was the obvious thing to do. Two reasons, both established by
 * trying it: it resolves Laravel's `validator` out of the container and the
 * `orcid` rule is registered by `PKP\core\ValidationServiceProvider`, so it
 * throws `BindingResolutionException` anywhere the application is not fully
 * booted — which means **no test can reach it**, and this plugin's PHPUnit
 * suite deliberately does not boot OJS. And it insists on the full
 * `https://orcid.org/…` form, while the plugin stores the bare `0000-…` one,
 * so a wrapper doing the conversion was needed either way.
 *
 * The cost is a second copy of fifteen lines of arithmetic. It is a rule with a
 * published specification that has not changed since 2012, it is pinned by
 * `tests/SubmissionUnitTests/CodecheckCodecheckersUnitTest.php` against ORCID's
 * own test identifiers, and `resources/js/orcid.js` is a third copy regardless,
 * because the browser cannot call PHP. A dependency nothing can test was the
 * worse trade.
 *
 * **One stored shape, one shape in the `codecheck.yml`.** OJS stores an author's
 * ORCID as the full URI — its own templates use the stored value as an `href` —
 * while a codechecker's is bare, so a generated file carried both forms at
 * once, in two neighbouring sections. `normalizeOrcid()` is what
 * `CodecheckMetadataHandler::buildYaml()` puts both through.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

class CodecheckCodecheckers
{
    /** Where a bare iD resolves, for anything that has to render one as a link. */
    public const ORCID_URI_PREFIX = 'https://orcid.org/';

    /** Sixteen digits in four groups; the last may be the check character `X`. */
    private const BARE = '/^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/D';

    /**
     * GitHub's rule for a username: up to 39 letters, digits and single
     * hyphens, neither starting nor ending with a hyphen.
     */
    private const GITHUB_USERNAME = '/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/D';

    /**
     * Reduces an ORCID iD to the bare `0000-0000-0000-0000` form that is stored.
     *
     * Accepts what someone is likely to paste: the iD on its own, or the
     * address of an ORCID record, with or without a scheme, from `orcid.org`,
     * `www.orcid.org` or `sandbox.orcid.org`, and with the query string the
     * profile page carries. Mirrored by `normalizeOrcid` in
     * `resources/js/orcid.js`; the two must agree, or the browser and the
     * server disagree about the same value.
     *
     * Nothing is validated here — an unrecognisable value comes back trimmed
     * and unchanged, for `isOrcid()` to refuse and for a refusal message to
     * quote back.
     */
    public static function normalizeOrcid(mixed $value): string
    {
        $orcid = trim((string) ($value ?? ''));
        $orcid = preg_replace('#^(?:https?://)?(?:www\.|sandbox\.)*orcid\.org/#i', '', $orcid);
        $orcid = preg_replace('/[?#].*$/', '', $orcid);

        return strtoupper(rtrim($orcid, '/'));
    }

    /**
     * Whether a value is an ORCID iD: the right shape, and a check digit that
     * agrees.
     *
     * The empty value is not an iD. A codechecker without one is ordinary — the
     * field is optional — so callers judging a stored record ask this only of
     * the ones that are not empty.
     */
    public static function isOrcid(mixed $value): bool
    {
        $orcid = self::normalizeOrcid($value);

        if ($orcid === '') {
            return false;
        }

        if (!preg_match(self::BARE, $orcid)) {
            return false;
        }

        // ISO 7064 MOD 11-2, as ORCID specifies it and as `resources/js/orcid.js`
        // computes it: double-and-add across the first fifteen digits, and the
        // remainder decides the sixteenth character.
        $digits = str_replace('-', '', $orcid);
        $total = 0;
        for ($position = 0; $position < 15; $position++) {
            $total = ($total + (int) $digits[$position]) * 2;
        }

        $expected = (12 - ($total % 11)) % 11;

        return $digits[15] === ($expected === 10 ? 'X' : (string) $expected);
    }

    /**
     * Reduces a GitHub username to the bare name that is stored.
     *
     * Accepts what someone is likely to paste: the name, the `@name` form the
     * community list and GitHub's own mentions use, or the address of the
     * profile. Mirrored by `normalizeGithubUsername` in
     * `resources/js/githubUsername.js`. Nothing is validated here, as with
     * `normalizeOrcid()`.
     */
    public static function normalizeGithubUsername(mixed $value): string
    {
        $username = trim((string) ($value ?? ''));
        $username = preg_replace('#^(?:https?://)?(?:www\.)?github\.com/#i', '', $username);
        $username = preg_replace('/[?#].*$/', '', $username);

        return ltrim(rtrim($username, '/'), '@');
    }

    /**
     * Whether a value is a GitHub username. The empty value is not one; the
     * field is optional, as the ORCID iD is.
     */
    public static function isGithubUsername(mixed $value): bool
    {
        return (bool) preg_match(self::GITHUB_USERNAME, self::normalizeGithubUsername($value));
    }

    /**
     * The GitHub usernames in a codechecker list that are not usernames, in
     * the form they were given.
     *
     * @return array<int, string>
     */
    private static function unusableGithubUsernames(mixed $codecheckers): array
    {
        return self::unusable($codecheckers, 'github', [self::class, 'isGithubUsername']);
    }

    /**
     * The unusable GitHub usernames a save would *introduce* — only what is new
     * is judged, as for the ORCID iDs below.
     *
     * @return array<int, string>
     */
    public static function newUnusableGithubUsernames(mixed $incoming, mixed $stored): array
    {
        return self::newlyIntroduced(self::unusableGithubUsernames($incoming), self::unusableGithubUsernames($stored));
    }

    /**
     * The ORCID iDs in a codechecker list that are not ORCID iDs.
     *
     * Reported in the form they were given, not normalised, so a refusal
     * message shows the editor what they typed.
     *
     * @param mixed $codecheckers the list as it arrives, or as it is stored
     *
     * @return array<int, string>
     */
    public static function unusableOrcids(mixed $codecheckers): array
    {
        return self::unusable($codecheckers, 'orcid', [self::class, 'isOrcid']);
    }

    /**
     * The unusable ORCID iDs a save would *introduce*.
     *
     * Only what is new is judged, as for repository addresses: refusing a
     * record for a value already in it turns away saves that changed something
     * else entirely, and the editor is given no way out (issue #170).
     *
     * @return array<int, string>
     */
    public static function newUnusableOrcids(mixed $incoming, mixed $stored): array
    {
        return self::newlyIntroduced(self::unusableOrcids($incoming), self::unusableOrcids($stored));
    }

    /**
     * The ORCID iDs recorded for the codecheckers of a list, in the stored form.
     *
     * This is who may be credited for a check on ORCID (GHSA-4p3r-qgp4-g74r):
     * an ORCID account is connected to a submission, and deposited for, only
     * when its iD is here. A codechecker recorded by name alone has no iD, and
     * so cannot be credited until one is recorded. Anything that is not an iD
     * is left out rather than compared. Each entry's iD is the one
     * `normalizedEntry()` would store: an entry from before that rule, spelled
     * `ORCID` or failing the check digit, counts as having none.
     *
     * @param mixed $codecheckers the list as stored, JSON or decoded
     *
     * @return array<int, string>
     */
    public static function recordedOrcids(mixed $codecheckers): array
    {
        $orcids = array_column(self::withNormalizedEntries($codecheckers), 'orcid');

        return array_values(array_unique(array_filter($orcids, fn ($orcid) => $orcid !== '')));
    }

    /**
     * The list with every ORCID iD and GitHub username reduced to the stored
     * form, and each name trimmed.
     *
     * Applied on the way in so the column holds one shape whatever the client
     * sent — the dialog normalises too, but the endpoint is reachable without
     * it.
     *
     * @return array<int, array{name: string, orcid: string, github: string}>
     */
    public static function withNormalizedEntries(mixed $codecheckers): array
    {
        return array_map([self::class, 'normalizedEntry'], self::entries($codecheckers));
    }

    /**
     * One entry in the stored form.
     *
     * An unrecognisable value is refused before this is reached, so what is
     * stored is either a well-formed value or nothing.
     *
     * @return array{name: string, orcid: string, github: string}
     */
    public static function normalizedEntry(array $codechecker): array
    {
        $orcid = self::normalizeOrcid($codechecker['orcid'] ?? '');
        $github = self::normalizeGithubUsername($codechecker['github'] ?? '');

        return [
            'name' => trim((string) ($codechecker['name'] ?? '')),
            'orcid' => self::isOrcid($orcid) ? $orcid : '',
            'github' => self::isGithubUsername($github) ? $github : '',
        ];
    }

    /**
     * The values under `$key` that `$isUsable` refuses, in the form they were
     * given, so a refusal message shows the editor what they typed.
     *
     * @return array<int, string>
     */
    private static function unusable(mixed $codecheckers, string $key, callable $isUsable): array
    {
        $unusable = [];

        foreach (self::entries($codecheckers) as $codechecker) {
            $value = trim((string) ($codechecker[$key] ?? ''));

            if ($value !== '' && !$isUsable($value)) {
                $unusable[] = $value;
            }
        }

        return array_values(array_unique($unusable));
    }

    /**
     * Refusing a record for a value already in it turns away saves that changed
     * something else entirely, and the editor is given no way out (issue #170).
     *
     * @return array<int, string>
     */
    private static function newlyIntroduced(array $incoming, array $alreadyStored): array
    {
        return array_values(array_filter(
            $incoming,
            fn (string $value) => !in_array($value, $alreadyStored, true)
        ));
    }

    /**
     * The list, whether it arrives as an array or as the JSON the column holds.
     *
     * @return array<int, array>
     */
    private static function entries(mixed $codecheckers): array
    {
        if (is_string($codecheckers)) {
            $codecheckers = json_decode($codecheckers, true);
        }

        if (!is_array($codecheckers)) {
            return [];
        }

        return array_values(array_filter($codecheckers, 'is_array'));
    }
}
