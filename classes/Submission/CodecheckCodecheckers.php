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
 *   the ORCID iDs in it.
 *
 * The list is `[{name, orcid}, …]`. An ORCID iD used to be stored exactly as it
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
    private const BARE = '/^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/';

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
        $unusable = [];

        foreach (self::entries($codecheckers) as $codechecker) {
            $orcid = trim((string) ($codechecker['orcid'] ?? ''));

            if ($orcid !== '' && !self::isOrcid($orcid)) {
                $unusable[] = $orcid;
            }
        }

        return array_values(array_unique($unusable));
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
        $alreadyStored = self::unusableOrcids($stored);

        return array_values(array_filter(
            self::unusableOrcids($incoming),
            fn (string $orcid) => !in_array($orcid, $alreadyStored, true)
        ));
    }

    /**
     * The list with every ORCID iD reduced to the stored form, and each name
     * trimmed.
     *
     * Applied on the way in so the column holds one shape whatever the client
     * sent — the dialog normalises too, but the endpoint is reachable without
     * it.
     *
     * @return array<int, array{name: string, orcid: string}>
     */
    public static function withNormalizedOrcids(mixed $codecheckers): array
    {
        $normalized = [];

        foreach (self::entries($codecheckers) as $codechecker) {
            $orcid = self::normalizeOrcid($codechecker['orcid'] ?? '');

            $normalized[] = [
                'name' => trim((string) ($codechecker['name'] ?? '')),
                // An unrecognisable value is refused before this is reached, so
                // what is stored is either a well-formed iD or nothing.
                'orcid' => self::isOrcid($orcid) ? $orcid : '',
            ];
        }

        return $normalized;
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
