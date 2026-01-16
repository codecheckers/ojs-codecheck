<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Submission\Schema;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SchemaUnitTest.php
 *
 * @class SchemaUnitTest
 *
 * @brief Tests for the Schema class
 */
class SchemaUnitTest extends PKPTestCase
{
    private Schema $schema;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = new Schema();
    }

    public function testAddToSchemaPublicationReturnsBoolean()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $result = $this->schema->addToSchemaPublication('test_hook', $args);
        
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testAddToSchemaPublicationAddsCodeRepositoryField()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $this->assertObjectHasProperty('codeRepository', $mockSchema->properties);
        $this->assertSame('string', $mockSchema->properties->codeRepository->type);
        $this->assertFalse($mockSchema->properties->codeRepository->multilingual);
        $this->assertTrue($mockSchema->properties->codeRepository->apiSummary);
        $this->assertSame(['nullable'], $mockSchema->properties->codeRepository->validation);
    }

    public function testAddToSchemaPublicationAddsDataRepositoryField()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $this->assertObjectHasProperty('dataRepository', $mockSchema->properties);
        $this->assertSame('string', $mockSchema->properties->dataRepository->type);
        $this->assertFalse($mockSchema->properties->dataRepository->multilingual);
        $this->assertTrue($mockSchema->properties->dataRepository->apiSummary);
        $this->assertSame(['nullable'], $mockSchema->properties->dataRepository->validation);
    }

    public function testAddToSchemaPublicationAddsManifestFilesField()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $this->assertObjectHasProperty('manifestFiles', $mockSchema->properties);
        $this->assertSame('string', $mockSchema->properties->manifestFiles->type);
        $this->assertFalse($mockSchema->properties->manifestFiles->multilingual);
        $this->assertTrue($mockSchema->properties->manifestFiles->apiSummary);
        $this->assertSame(['nullable'], $mockSchema->properties->manifestFiles->validation);
    }

    public function testAddToSchemaPublicationAddsDataAvailabilityStatementField()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $this->assertObjectHasProperty('dataAvailabilityStatement', $mockSchema->properties);
        $this->assertSame('string', $mockSchema->properties->dataAvailabilityStatement->type);
        $this->assertFalse($mockSchema->properties->dataAvailabilityStatement->multilingual);
        $this->assertTrue($mockSchema->properties->dataAvailabilityStatement->apiSummary);
        $this->assertSame(['nullable'], $mockSchema->properties->dataAvailabilityStatement->validation);
    }

    public function testAddToSchemaPublicationAddsAllExpectedFields()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $expectedFields = [
            'codeRepository',
            'dataRepository',
            'manifestFiles',
            'dataAvailabilityStatement'
        ];
        
        foreach ($expectedFields as $field) {
            $this->assertObjectHasProperty($field, $mockSchema->properties);
        }
    }

    public function testAddToSchemaPublicationModifiesSchemaByReference()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $initialPropertyCount = count((array)$mockSchema->properties);
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $finalPropertyCount = count((array)$mockSchema->properties);
        
        $this->assertSame($initialPropertyCount + 4, $finalPropertyCount);
    }

    public function testAllAddedFieldsHaveConsistentStructure()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->schema->addToSchemaPublication('test_hook', $args);
        
        $fields = ['codeRepository', 'dataRepository', 'manifestFiles', 'dataAvailabilityStatement'];
        
        foreach ($fields as $field) {
            $property = $mockSchema->properties->{$field};
            
            $this->assertObjectHasProperty('type', $property);
            $this->assertObjectHasProperty('multilingual', $property);
            $this->assertObjectHasProperty('apiSummary', $property);
            $this->assertObjectHasProperty('validation', $property);
            
            $this->assertSame('string', $property->type);
            $this->assertFalse($property->multilingual);
            $this->assertTrue($property->apiSummary);
            $this->assertIsArray($property->validation);
        }
    }
}