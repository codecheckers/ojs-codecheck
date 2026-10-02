<?php

/**
 * @file tests/DoiDepositUnitTests/DataciteRelationsUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #19 — the CODECHECK links as related identifiers in a DataCite
 *        record. The fixture has the shape OJS 3.5's DataCite plugin writes, and
 *        both it and the result were checked against the kernel-4 schema; that
 *        check is not repeated here, because the schema is fetched over the
 *        network.
 */

namespace APP\plugins\generic\codecheck\tests\DoiDepositUnitTests;

use APP\plugins\generic\codecheck\classes\DoiDeposit\DataciteRelations;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PKP\tests\PKPTestCase;

class DataciteRelationsUnitTest extends PKPTestCase
{
    private const NS = 'http://datacite.org/schema/kernel-4';

    private const LINKS = [
        ['relation' => 'review', 'type' => 'doi', 'identifier' => '10.5281/zenodo.1234567'],
        ['relation' => 'supplement', 'type' => 'url', 'identifier' => 'https://github.com/example/code'],
    ];

    private DOMDocument $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doc = new DOMDocument();
        $this->doc->load(__DIR__ . '/fixtures/datacite-article.xml');
    }

    private function relatedIdentifiers(): array
    {
        $xpath = new DOMXPath($this->doc);
        $xpath->registerNamespace('d', self::NS);

        return array_map(
            fn (DOMElement $element) => [
                $element->getAttribute('relationType'),
                $element->getAttribute('relatedIdentifierType'),
                $element->getAttribute('resourceTypeGeneral'),
                $element->textContent,
            ],
            iterator_to_array($xpath->query('/d:resource/d:relatedIdentifiers/d:relatedIdentifier'))
        );
    }

    private function setPublisherId(string $id, string $resourceTypeGeneral = 'JournalArticle'): void
    {
        $this->doc->getElementsByTagNameNS(self::NS, 'alternateIdentifier')->item(0)->textContent = $id;
        $this->doc->getElementsByTagNameNS(self::NS, 'resourceType')->item(0)->setAttribute('resourceTypeGeneral', $resourceTypeGeneral);
    }

    public function testAnArticleIsFoundByItsPublisherIdNotItsDoi()
    {
        $this->assertSame(['contextId' => 1, 'submissionId' => 5], DataciteRelations::articleIds($this->doc));

        // An article outside an issue has no issue part.
        $this->setPublisherId('1-5');
        $this->assertSame(['contextId' => 1, 'submissionId' => 5], DataciteRelations::articleIds($this->doc));
    }

    /** The same filter writes issues and galleys, which get no links. */
    public function testAnIssueOrAGalleyIsNotAnArticle()
    {
        $this->setPublisherId('1-2-5-g7');
        $this->assertNull(DataciteRelations::articleIds($this->doc));

        $this->setPublisherId('1-2', 'Text');
        $this->assertNull(DataciteRelations::articleIds($this->doc));

        $this->setPublisherId('1-2-5', 'Dataset');
        $this->assertNull(DataciteRelations::articleIds($this->doc));
    }

    /** OJS writes `relatedIdentifiers` only when there is an issue or galley DOI. */
    public function testTheListIsCreatedWhenAbsent()
    {
        $this->assertSame(2, DataciteRelations::addToResource($this->doc, self::LINKS));

        $this->assertSame([
            ['IsReviewedBy', 'DOI', 'Report', '10.5281/zenodo.1234567'],
            ['IsSupplementedBy', 'URL', '', 'https://github.com/example/code'],
        ], $this->relatedIdentifiers());
    }

    /** The schema allows one list, so OJS's own is added to. */
    public function testOjssListIsAddedToAndARelationAlreadyThereIsNotRepeated()
    {
        $list = $this->doc->createElementNS(self::NS, 'relatedIdentifiers');
        $partOf = $this->doc->createElementNS(self::NS, 'relatedIdentifier', '10.99999/ccdj.i2');
        $partOf->setAttribute('relatedIdentifierType', 'DOI');
        $partOf->setAttribute('relationType', 'IsPartOf');
        $list->appendChild($partOf);
        $review = $this->doc->createElementNS(self::NS, 'relatedIdentifier', '10.5281/zenodo.1234567');
        $review->setAttribute('relatedIdentifierType', 'DOI');
        $review->setAttribute('relationType', 'IsReviewedBy');
        $list->appendChild($review);
        $this->doc->documentElement->appendChild($list);

        $this->assertSame(1, DataciteRelations::addToResource($this->doc, self::LINKS));

        $this->assertSame(1, $this->doc->getElementsByTagNameNS(self::NS, 'relatedIdentifiers')->length);
        $this->assertSame(
            ['IsPartOf', 'IsReviewedBy', 'IsSupplementedBy'],
            array_column($this->relatedIdentifiers(), 0)
        );
    }

    public function testNoLinksLeaveTheRecordAlone()
    {
        $before = $this->doc->saveXML();

        $this->assertSame(0, DataciteRelations::addToResource($this->doc, []));
        $this->assertSame($before, $this->doc->saveXML());
    }
}
