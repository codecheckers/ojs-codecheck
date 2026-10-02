<?php

/**
 * @file classes/Submission/CertificateReference.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CertificateReference
 *
 * @brief The CODECHECK certificate as a line in the article's references
 *   (#183), and how that line joins the references already there.
 *
 * No database, so the rule is unit tested like `CodecheckRepositories`. What
 * references reach in OJS 3.5 — the article page, Google Scholar's
 * `citation_reference`, JATS, the PubMed and Native exports, but not Crossref —
 * is on the issue.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\Constants;

class CertificateReference
{
    /** The DOI prefix of Zenodo, where most certificates are deposited. */
    private const ZENODO_DOI_PREFIX = '10.5281/';

    /**
     * The reference, `{codecheckers}. ({year}). CODECHECK certificate
     * {YYYY-NNN}. [Zenodo. ]{link}`, or `null` when there is no certificate to
     * cite or nothing to link it to.
     *
     * The names are as stored, in record order: the record holds one free-text
     * name per codechecker, and turning it into "Family, I." is wrong for
     * particles and for names that are not Western. The link is the
     * certificate's DOI where there is one, its register page otherwise.
     */
    public static function format(CodecheckSubmission $record): ?string
    {
        $certificate = trim($record->getCertificate());
        $link = self::link($record);
        if ($certificate === '' || $link === '') {
            return null;
        }

        $parts = [];

        $names = $record->getCodecheckerNames();
        if (trim($names) !== '') {
            $parts[] = self::sentence($names);
        }

        if (preg_match('/^(\d{4})/', (string) $record->getCheckTime(), $matches)) {
            $parts[] = '(' . $matches[1] . ').';
        }

        $identifier = Constants::certificateIdentifier($certificate);
        $parts[] = $identifier === null ? 'CODECHECK certificate.' : 'CODECHECK certificate ' . $identifier . '.';

        $doi = $record->getReportDoi();
        if ($doi !== null && str_starts_with($doi, self::ZENODO_DOI_PREFIX)) {
            $parts[] = 'Zenodo.';
        }

        $parts[] = $link;

        return implode(' ', $parts);
    }

    /**
     * The references with the certificate's line in them: a line that already
     * refers to this certificate is replaced where it stands, otherwise the
     * line is appended. No other line is touched — not even a second line for
     * the same certificate, because only an act of removal removes an entry
     * (#170). Answers the references unchanged, character for character, when
     * the line is already there, so a caller can compare and write nothing.
     */
    public static function merge(string $citationsRaw, string $line, CodecheckSubmission $record): string
    {
        $lines = $citationsRaw === '' ? [] : preg_split('/\R/', $citationsRaw);
        $refersToCertificate = self::matcher($record);

        foreach ($lines as $index => $existing) {
            if ($refersToCertificate($existing)) {
                if ($existing === $line) {
                    return $citationsRaw;
                }
                $lines[$index] = $line;
                return implode("\n", $lines);
            }
        }

        // Appended after the last line with text, so trailing blank lines do
        // not end up between the references and the certificate.
        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }
        $lines[] = $line;

        return implode("\n", $lines);
    }

    /**
     * Whether a reference line refers to this certificate: it carries the
     * certificate's DOI, its register address, the address the line links to,
     * or its identifier. Each is matched whole — followed by the end of the
     * line, a space, closing punctuation or a final full stop — so a DOI or
     * address that merely begins the same way, `…zenodo.1234567` for
     * `…zenodo.123456`, is another reference and left alone.
     *
     * @return callable(string): bool
     */
    private static function matcher(CodecheckSubmission $record): callable
    {
        $end = '(?=$|[\s)\]>"\',;]|\.(?:$|\s))';
        $patterns = [];

        $doi = $record->getReportDoi();
        if ($doi !== null) {
            $patterns[] = '~(?<![0-9a-z])' . preg_quote($doi, '~') . $end . '~i';
        }
        foreach ([Constants::getRegisterCertificateUrl($record->getCertificate()), self::link($record)] as $address) {
            if ($address !== '') {
                $patterns[] = '~' . preg_quote(rtrim($address, '/'), '~') . '/?' . $end . '~i';
            }
        }
        $identifier = Constants::certificateIdentifier($record->getCertificate());
        if ($identifier !== null) {
            $patterns[] = '~CODECHECK certificate ' . preg_quote($identifier, '~') . '(?![0-9-])~i';
        }

        return function (string $line) use ($patterns): bool {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    return true;
                }
            }
            return false;
        };
    }

    /** What the line links to: the certificate's DOI, else its own address. */
    private static function link(CodecheckSubmission $record): string
    {
        return $record->getDoiLink() ?: $record->getCertificateLink();
    }

    /** Ends a part with one full stop, whatever the stored text ends with. */
    private static function sentence(string $text): string
    {
        return rtrim(trim($text), '.') . '.';
    }
}
