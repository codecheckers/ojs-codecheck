<?php

/**
 * @file classes/DoiDeposit/CrossrefRelations.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CrossrefRelations
 *
 * @brief Writes CODECHECK links into a Crossref `journal_article` as relations
 *   (#19): the certificate as `hasReview`, a repository as `isSupplementedBy`.
 *
 * Schema 5.4.0, which OJS 3.5 deposits, allows one `rel:program` per article,
 * after the `crossmark | (fr, ai, ct)` choice and before `archive_locations`,
 * `scn_policies`, `version_info` and `doi_data`. One already there — another
 * plugin's — is added to rather than duplicated. DOM only, no OJS, so it is
 * unit tested on fixture XML.
 */

namespace APP\plugins\generic\codecheck\classes\DoiDeposit;

use DOMElement;

class CrossrefRelations
{
    use DomChildren;

    /**
     * The relations namespace. The `http` form is the one the schema declares;
     * `https` fails validation.
     */
    public const NAMESPACE = 'http://www.crossref.org/relations.xsd';

    /** What follows `rel:program` in a `journal_article`, in schema order. */
    private const FOLLOWING = ['archive_locations', 'scn_policies', 'version_info', 'doi_data'];

    private const RELATIONSHIP_TYPES = [
        CodecheckDepositLinks::RELATION_REVIEW => 'hasReview',
        CodecheckDepositLinks::RELATION_SUPPLEMENT => 'isSupplementedBy',
    ];

    private const IDENTIFIER_TYPES = [
        CodecheckDepositLinks::TYPE_DOI => 'doi',
        CodecheckDepositLinks::TYPE_URL => 'uri',
    ];

    /**
     * The article's own DOI, as its `doi_data` gives it.
     */
    public static function articleDoi(DOMElement $journalArticle): ?string
    {
        $doi = self::children(self::children($journalArticle, 'doi_data')[0] ?? null, 'doi')[0] ?? null;
        $value = trim($doi?->textContent ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Add the links to the article. A relation the article already carries is
     * not added a second time.
     *
     * @param array<int, array{relation: string, type: string, identifier: string}> $links
     *
     * @return int How many relations were added.
     */
    public static function addToArticle(DOMElement $journalArticle, array $links): int
    {
        $doc = $journalArticle->ownerDocument;
        $program = self::program($journalArticle);
        $existing = $program === null ? [] : self::relationsIn($program);

        $added = 0;
        foreach ($links as $link) {
            $relationshipType = self::RELATIONSHIP_TYPES[$link['relation']];
            $key = self::relationKey($relationshipType, $link['identifier']);
            if (isset($existing[$key])) {
                continue;
            }
            $existing[$key] = true;

            if ($program === null) {
                $program = $doc->createElementNS(self::NAMESPACE, 'rel:program');
                $program->setAttribute('name', 'relations');
                $journalArticle->insertBefore($program, self::firstFollowing($journalArticle));
            }

            $relation = $doc->createElementNS(self::NAMESPACE, 'rel:inter_work_relation');
            $relation->setAttribute('relationship-type', $relationshipType);
            $relation->setAttribute('identifier-type', self::IDENTIFIER_TYPES[$link['type']]);
            $relation->appendChild($doc->createTextNode($link['identifier']));

            $item = $doc->createElementNS(self::NAMESPACE, 'rel:related_item');
            $item->appendChild($relation);
            $program->appendChild($item);
            $added++;
        }

        return $added;
    }

    /** The article's own `rel:program`, not one inside a `crossmark`. */
    private static function program(DOMElement $journalArticle): ?DOMElement
    {
        foreach ($journalArticle->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::NAMESPACE && $child->localName === 'program') {
                return $child;
            }
        }

        return null;
    }

    /**
     * The relations a program already holds, keyed as `addToArticle()` keys
     * the new ones.
     *
     * @return array<string, true>
     */
    private static function relationsIn(DOMElement $program): array
    {
        $relations = [];
        foreach ($program->getElementsByTagNameNS(self::NAMESPACE, 'inter_work_relation') as $relation) {
            $relations[self::relationKey($relation->getAttribute('relationship-type'), $relation->textContent)] = true;
        }

        return $relations;
    }

    /** The element a new `rel:program` goes before; `null` appends it. */
    private static function firstFollowing(DOMElement $journalArticle): ?DOMElement
    {
        foreach ($journalArticle->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $journalArticle->namespaceURI && in_array($child->localName, self::FOLLOWING, true)) {
                return $child;
            }
        }

        return null;
    }
}
