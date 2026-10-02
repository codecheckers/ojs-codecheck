<?php

/**
 * @file tests/DoiDepositUnitTests/DepositSchemaUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #19 — what the plugin adds to a deposit is valid against the
 *        published schemas. This is the check the deposit itself does not make:
 *        both schemas take any text as the related identifier and everything
 *        else written is fixed, so it cannot come out differently per deposit.
 *        Copies of the schemas are in `schemas/`; see the README there.
 */

namespace APP\plugins\generic\codecheck\tests\DoiDepositUnitTests;

use APP\plugins\generic\codecheck\classes\DoiDeposit\CodecheckDepositLinks;
use APP\plugins\generic\codecheck\classes\DoiDeposit\CrossrefRelations;
use APP\plugins\generic\codecheck\classes\DoiDeposit\DataciteRelations;
use DOMDocument;
use PKP\tests\PKPTestCase;

class DepositSchemaUnitTest extends PKPTestCase
{
    /** Every relation and identifier type the plugin writes. */
    private const LINKS = [
        ['relation' => CodecheckDepositLinks::RELATION_REVIEW, 'type' => CodecheckDepositLinks::TYPE_DOI, 'identifier' => '10.5281/zenodo.1234567'],
        ['relation' => CodecheckDepositLinks::RELATION_SUPPLEMENT, 'type' => CodecheckDepositLinks::TYPE_URL, 'identifier' => 'https://github.com/example/code'],
        ['relation' => CodecheckDepositLinks::RELATION_SUPPLEMENT, 'type' => CodecheckDepositLinks::TYPE_DOI, 'identifier' => '10.5281/zenodo.7654321'],
    ];

    private bool $useInternalErrors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
    }

    protected function tearDown(): void
    {
        libxml_clear_errors();
        libxml_use_internal_errors($this->useInternalErrors);
        parent::tearDown();
    }

    private function assertValid(DOMDocument $doc, string $schema): void
    {
        $valid = $doc->schemaValidate(__DIR__ . '/schemas/' . $schema);
        $this->assertTrue($valid, implode("\n", array_map(fn ($error) => trim($error->message), libxml_get_errors())));
    }

    /**
     * The Crossref schema itself imports MathML and JATS from the network, so
     * the program is checked on its own against `relations.xsd`; where it sits
     * in the article is pinned by `CrossrefRelationsUnitTest`.
     */
    public function testTheCrossrefProgramIsValid()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/fixtures/crossref-article.xml');
        $article = $doc->getElementsByTagNameNS('http://www.crossref.org/schema/5.4.0', 'journal_article')->item(0);
        CrossrefRelations::addToArticle($article, self::LINKS);

        $program = new DOMDocument();
        $program->appendChild($program->importNode($doc->getElementsByTagNameNS(CrossrefRelations::NAMESPACE, 'program')->item(0), true));

        $this->assertValid($program, 'crossref/relations.xsd');
    }

    /** The whole DataCite record, both with OJS's own list and without one. */
    public function testTheDataciteRecordIsValid()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/fixtures/datacite-article.xml');
        $this->assertValid($doc, 'datacite-kernel-4/metadata.xsd');

        DataciteRelations::addToResource($doc, self::LINKS);
        $this->assertValid($doc, 'datacite-kernel-4/metadata.xsd');

        DataciteRelations::addToResource($doc, [['relation' => CodecheckDepositLinks::RELATION_SUPPLEMENT, 'type' => CodecheckDepositLinks::TYPE_URL, 'identifier' => 'https://gitlab.com/example/data']]);
        $this->assertValid($doc, 'datacite-kernel-4/metadata.xsd');
    }
}
