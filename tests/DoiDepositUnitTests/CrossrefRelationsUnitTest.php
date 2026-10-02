<?php

/**
 * @file tests/DoiDepositUnitTests/CrossrefRelationsUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #19 — the CODECHECK links as relations in a Crossref record.
 *        The fixture has the shape OJS 3.5's Crossref plugin writes, and both
 *        it and the result were checked against crossref5.4.0.xsd; that check
 *        is not repeated here, because the schema is fetched over the network.
 */

namespace APP\plugins\generic\codecheck\tests\DoiDepositUnitTests;

use APP\plugins\generic\codecheck\classes\DoiDeposit\CrossrefRelations;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PKP\tests\PKPTestCase;

class CrossrefRelationsUnitTest extends PKPTestCase
{
    private const LINKS = [
        ['relation' => 'review', 'type' => 'doi', 'identifier' => '10.5281/zenodo.1234567'],
        ['relation' => 'supplement', 'type' => 'url', 'identifier' => 'https://github.com/example/code'],
    ];

    private DOMDocument $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doc = new DOMDocument();
        $this->doc->load(__DIR__ . '/fixtures/crossref-article.xml');
    }

    private function article(): DOMElement
    {
        return $this->doc->getElementsByTagNameNS('http://www.crossref.org/schema/5.4.0', 'journal_article')->item(0);
    }

    private function xpath(): DOMXPath
    {
        $xpath = new DOMXPath($this->doc);
        $xpath->registerNamespace('cr', 'http://www.crossref.org/schema/5.4.0');
        $xpath->registerNamespace('rel', CrossrefRelations::NAMESPACE);
        return $xpath;
    }

    public function testArticleDoi()
    {
        $this->assertSame('10.99999/ccdj.5', CrossrefRelations::articleDoi($this->article()));
    }

    public function testTheLinksAreOneProgramRightBeforeDoiData()
    {
        $this->assertSame(2, CrossrefRelations::addToArticle($this->article(), self::LINKS));

        $xpath = $this->xpath();
        $this->assertSame(1, $xpath->query('//rel:program')->length);
        $this->assertSame('relations', $xpath->query('//rel:program')->item(0)->getAttribute('name'));
        $this->assertSame('doi_data', $xpath->query('//rel:program/following-sibling::*[1]')->item(0)->localName);
        $this->assertSame('program', $xpath->query('//cr:doi_data/preceding-sibling::*[1]')->item(0)->localName);

        $relations = $xpath->query('//rel:program/rel:related_item/rel:inter_work_relation');
        $this->assertSame(
            [
                ['hasReview', 'doi', '10.5281/zenodo.1234567'],
                ['isSupplementedBy', 'uri', 'https://github.com/example/code'],
            ],
            array_map(
                fn (DOMElement $relation) => [$relation->getAttribute('relationship-type'), $relation->getAttribute('identifier-type'), $relation->textContent],
                iterator_to_array($relations)
            )
        );
    }

    /** Another plugin's program is added to: the schema allows one. */
    public function testAnExistingProgramIsAddedToAndARelationAlreadyThereIsNotRepeated()
    {
        $program = $this->doc->createElementNS(CrossrefRelations::NAMESPACE, 'rel:program');
        $item = $this->doc->createElementNS(CrossrefRelations::NAMESPACE, 'rel:related_item');
        $relation = $this->doc->createElementNS(CrossrefRelations::NAMESPACE, 'rel:inter_work_relation', '10.5281/ZENODO.1234567');
        $relation->setAttribute('relationship-type', 'hasReview');
        $relation->setAttribute('identifier-type', 'doi');
        $item->appendChild($relation);
        $program->appendChild($item);
        $doiData = $this->doc->getElementsByTagNameNS('http://www.crossref.org/schema/5.4.0', 'doi_data')->item(0);
        $this->article()->insertBefore($program, $doiData);

        $this->assertSame(1, CrossrefRelations::addToArticle($this->article(), self::LINKS));

        $xpath = $this->xpath();
        $this->assertSame(1, $xpath->query('//rel:program')->length);
        $this->assertSame(2, $xpath->query('//rel:program/rel:related_item')->length);
    }

    public function testAddingTheSameLinksTwiceChangesNothingTheSecondTime()
    {
        CrossrefRelations::addToArticle($this->article(), self::LINKS);
        $once = $this->doc->saveXML();

        $this->assertSame(0, CrossrefRelations::addToArticle($this->article(), self::LINKS));
        $this->assertSame($once, $this->doc->saveXML());
    }

    public function testNoLinksLeaveTheArticleAlone()
    {
        $before = $this->doc->saveXML();

        $this->assertSame(0, CrossrefRelations::addToArticle($this->article(), []));
        $this->assertSame($before, $this->doc->saveXML());
    }

    /** The schema puts these after the program as well, so it goes before the first. */
    public function testTheProgramGoesBeforeArchiveLocationsWhenThereAreAny()
    {
        $archive = $this->doc->createElementNS('http://www.crossref.org/schema/5.4.0', 'archive_locations');
        $doiData = $this->doc->getElementsByTagNameNS('http://www.crossref.org/schema/5.4.0', 'doi_data')->item(0);
        $this->article()->insertBefore($archive, $doiData);

        CrossrefRelations::addToArticle($this->article(), self::LINKS);

        $this->assertSame('archive_locations', $this->xpath()->query('//rel:program/following-sibling::*[1]')->item(0)->localName);
    }
}
