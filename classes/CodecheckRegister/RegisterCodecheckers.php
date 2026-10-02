<?php

/**
 * @file classes/CodecheckRegister/RegisterCodecheckers.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class RegisterCodecheckers
 *
 * @brief How the codecheckers of a submission appear on its register issue
 *   (#186): who is assigned, and how each is named in the issue and its
 *   comments.
 *
 * Pure, so the rules are unit tested without GitHub.
 *
 * **A codechecker without a GitHub username is still a working case**, not an
 * error. The register issue then cannot be assigned, and says instead that the
 * journal coordinates the check and where to reach it — which is what a
 * CODECHECK editor reading the register needs in order to ask.
 */

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;

class RegisterCodecheckers
{
    /**
     * The usernames to assign, in list order and once each.
     *
     * @param mixed $codecheckers the list as it arrives or as it is stored
     *
     * @return string[]
     */
    public static function usernames(mixed $codecheckers): array
    {
        $usernames = [];
        foreach (CodecheckCodecheckers::withNormalizedEntries($codecheckers) as $entry) {
            if ($entry['github'] !== '' && !in_array($entry['github'], $usernames, true)) {
                $usernames[] = $entry['github'];
            }
        }

        return $usernames;
    }

    /**
     * Splits the list by whether GitHub holds its codechecker assigned.
     *
     * Usernames are compared without case, as GitHub compares them.
     *
     * @param mixed $codecheckers the list as it arrives or as it is stored
     * @param string[] $assigned who is assigned to the issue
     *
     * @return array{assigned: array<int, array>, unassigned: array<int, array>}
     */
    public static function split(mixed $codecheckers, array $assigned): array
    {
        $assigned = array_map('strtolower', $assigned);
        $split = ['assigned' => [], 'unassigned' => []];

        foreach (CodecheckCodecheckers::withNormalizedEntries($codecheckers) as $entry) {
            if ($entry['name'] === '' && $entry['github'] === '') {
                continue;
            }
            $isAssigned = $entry['github'] !== '' && in_array(strtolower($entry['github']), $assigned, true);
            $split[$isAssigned ? 'assigned' : 'unassigned'][] = $entry;
        }

        return $split;
    }

    /**
     * One codechecker in Markdown: the name, a link to the ORCID record, and
     * the username as a mention when `$mention` is set.
     *
     * The name is the one part nobody checked, and it is published under the
     * journal's account. It is kept to one line, escaped, and every `@` and `#`
     * in it is followed by a zero-width space, so a name can neither format the
     * post, nor mention somebody, nor reference another issue. An HTML entity
     * does not do this: GitHub decodes `&#64;name` back into a mention, which is
     * why `&` is written as `&amp;` too. The mention is the one place a username
     * becomes a notification, and it is only made for a username that passed
     * `CodecheckCodecheckers::isGithubUsername()`.
     */
    public static function describe(array $entry, bool $mention = true): string
    {
        $entry = CodecheckCodecheckers::normalizedEntry($entry);

        $name = trim(preg_replace('/\s+/u', ' ', $entry['name']));
        $name = strtr(CodecheckPostOrigin::escapeMarkdown(str_replace('&', '&amp;', $name)), [
            '@' => "@\u{200B}",
            '#' => "#\u{200B}",
        ]);
        if ($name === '') {
            $name = $entry['github'];
        }

        $parts = [$name];
        if ($entry['orcid'] !== '') {
            $orcidUrl = CodecheckCodecheckers::ORCID_URI_PREFIX . $entry['orcid'];
            $parts[] = "([ORCID {$entry['orcid']}]({$orcidUrl}))";
        }
        if ($mention && $entry['github'] !== '') {
            $parts[] = '@' . $entry['github'];
        }

        return implode(' ', $parts);
    }

    /**
     * The list, each named by `describe()` without a mention, joined for a
     * line of Markdown.
     */
    public static function describeAll(mixed $codecheckers): string
    {
        return implode(', ', array_map(
            fn (array $entry) => self::describe($entry, false),
            array_filter(
                CodecheckCodecheckers::withNormalizedEntries($codecheckers),
                fn (array $entry) => $entry['name'] !== '' || $entry['github'] !== ''
            )
        ));
    }
}
