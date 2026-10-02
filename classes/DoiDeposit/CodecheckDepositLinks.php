<?php

/**
 * @file classes/DoiDeposit/CodecheckDepositLinks.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckDepositLinks
 *
 * @brief Which CODECHECK links an article's DOI deposit carries (#19): the
 *   certificate as its review, and the public repositories as its supplements.
 *
 * Pure, so the rule is unit tested without a database. What the Crossref and
 * DataCite records call each link is decided where those records are written,
 * in `CrossrefRelations` and `DataciteRelations`.
 */

namespace APP\plugins\generic\codecheck\classes\DoiDeposit;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;

class CodecheckDepositLinks
{
    /** The certificate, which reviews the article. */
    public const RELATION_REVIEW = 'review';

    /** A repository holding the article's code or data. */
    public const RELATION_SUPPLEMENT = 'supplement';

    public const TYPE_DOI = 'doi';
    public const TYPE_URL = 'url';

    /**
     * The links for one check, or none.
     *
     * Only once the certificate is published: before that the certificate DOI
     * may not exist yet, and Crossref refuses a relation to a DOI it cannot
     * find. The repositories wait for the same moment, so a deposit never
     * claims a check that has not concluded. Only the repositories a reader may
     * see, which are the ones written to the `codecheck.yml`; a hidden one is
     * never deposited (#169).
     *
     * @param string $status The current CODECHECK status, as stored.
     *
     * @return array<int, array{relation: string, type: string, identifier: string}>
     */
    public static function forCheck(bool $optedIn, string $status, ?CodecheckSubmission $record): array
    {
        if (!$optedIn || $record === null || !in_array($status, Constants::CODECHECK_STATUSES_CERTIFICATE_PUBLISHED, true)) {
            return [];
        }

        $links = [];

        // The certificate only as a DOI: a report at another address is not
        // something the deposit can call the article's review.
        $certificateDoi = $record->getReportDoi();
        if ($certificateDoi !== null) {
            $links[] = ['relation' => self::RELATION_REVIEW, 'type' => self::TYPE_DOI, 'identifier' => $certificateDoi];
        }

        foreach ($record->getPublicRepositories() as $repository) {
            if ($repository['isWebLink']) {
                $links[] = self::link(self::RELATION_SUPPLEMENT, $repository['url']);
            }
        }

        return $links;
    }

    /**
     * The bare DOI of a doi.org address, or `null` for anything else.
     */
    public static function doiFromUrl(string $url): ?string
    {
        if (preg_match('#^https?://(?:dx\.)?doi\.org/(10\.\d{4,}(?:\.\d+)*/\S+)$#i', trim($url), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * A doi.org address is deposited as the DOI it resolves, anything else as
     * the address itself.
     *
     * @return array{relation: string, type: string, identifier: string}
     */
    private static function link(string $relation, string $address): array
    {
        $doi = self::doiFromUrl($address);

        return [
            'relation' => $relation,
            'type' => $doi === null ? self::TYPE_URL : self::TYPE_DOI,
            'identifier' => $doi ?? trim($address),
        ];
    }
}
