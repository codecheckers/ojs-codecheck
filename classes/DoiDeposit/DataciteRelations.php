<?php

/**
 * @file classes/DoiDeposit/DataciteRelations.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class DataciteRelations
 *
 * @brief Writes CODECHECK links into a DataCite record as related identifiers
 *   (#19): the certificate as `IsReviewedBy` a `Report`, a repository as
 *   `IsSupplementedBy`.
 *
 * `<resource>` is an `xs:all`, so element order is free but `relatedIdentifiers`
 * may appear once: OJS writes it only when the issue or a galley has a DOI, so
 * it is created when absent and added to when present. DOM only, no OJS, so it
 * is unit tested on fixture XML.
 */

namespace APP\plugins\generic\codecheck\classes\DoiDeposit;

use DOMDocument;
use DOMElement;

class DataciteRelations
{
    use DomChildren;

    private const RELATION_TYPES = [
        CodecheckDepositLinks::RELATION_REVIEW => 'IsReviewedBy',
        CodecheckDepositLinks::RELATION_SUPPLEMENT => 'IsSupplementedBy',
    ];

    private const IDENTIFIER_TYPES = [
        CodecheckDepositLinks::TYPE_DOI => 'DOI',
        CodecheckDepositLinks::TYPE_URL => 'URL',
    ];

    /**
     * The journal and submission a record describes, when it describes an
     * article — `null` for an issue or a galley, which carry no CODECHECK links.
     *
     * Read from OJS's `publisherId` alternate identifier,
     * `{context}[-{issue}]-{submission}`, galleys adding `-g{galley}`. Not from
     * the DOI: test mode rewrites the DOI's prefix, so a lookup by it finds
     * nothing there.
     *
     * @return array{contextId: int, submissionId: int}|null
     */
    public static function articleIds(DOMDocument $doc): ?array
    {
        $resource = $doc->documentElement;
        if (!$resource instanceof DOMElement) {
            return null;
        }

        $resourceType = self::children($resource, 'resourceType')[0] ?? null;
        if ($resourceType?->getAttribute('resourceTypeGeneral') !== 'JournalArticle') {
            return null;
        }

        foreach (self::children($resource, 'alternateIdentifiers') as $alternateIdentifiers) {
            foreach (self::children($alternateIdentifiers, 'alternateIdentifier') as $alternateIdentifier) {
                if ($alternateIdentifier->getAttribute('alternateIdentifierType') !== 'publisherId') {
                    continue;
                }
                if (preg_match('/^(\d+)(?:-\d+)?-(\d+)$/', trim($alternateIdentifier->textContent), $matches)) {
                    return ['contextId' => (int) $matches[1], 'submissionId' => (int) $matches[2]];
                }
            }
        }

        return null;
    }

    /**
     * Add the links to the record. A relation the record already carries is not
     * added a second time.
     *
     * @param array<int, array{relation: string, type: string, identifier: string}> $links
     *
     * @return int How many relations were added.
     */
    public static function addToResource(DOMDocument $doc, array $links): int
    {
        $resource = $doc->documentElement;
        $namespace = $resource->namespaceURI;
        $relatedIdentifiers = self::children($resource, 'relatedIdentifiers')[0] ?? null;

        $existing = [];
        if ($relatedIdentifiers !== null) {
            foreach (self::children($relatedIdentifiers, 'relatedIdentifier') as $relatedIdentifier) {
                $existing[self::relationKey($relatedIdentifier->getAttribute('relationType'), $relatedIdentifier->textContent)] = true;
            }
        }

        $added = 0;
        foreach ($links as $link) {
            $relationType = self::RELATION_TYPES[$link['relation']];
            $key = self::relationKey($relationType, $link['identifier']);
            if (isset($existing[$key])) {
                continue;
            }
            $existing[$key] = true;

            if ($relatedIdentifiers === null) {
                $relatedIdentifiers = $doc->createElementNS($namespace, 'relatedIdentifiers');
                $resource->appendChild($relatedIdentifiers);
            }

            $relatedIdentifier = $doc->createElementNS($namespace, 'relatedIdentifier');
            $relatedIdentifier->appendChild($doc->createTextNode($link['identifier']));
            $relatedIdentifier->setAttribute('relatedIdentifierType', self::IDENTIFIER_TYPES[$link['type']]);
            $relatedIdentifier->setAttribute('relationType', $relationType);
            if ($link['relation'] === CodecheckDepositLinks::RELATION_REVIEW) {
                $relatedIdentifier->setAttribute('resourceTypeGeneral', 'Report');
            }
            $relatedIdentifiers->appendChild($relatedIdentifier);
            $added++;
        }

        return $added;
    }
}
