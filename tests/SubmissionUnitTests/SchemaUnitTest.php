<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\Schema;
use PKP\tests\PKPTestCase;

/**
 * The publication schema extension is what makes `dataAvailabilityStatement`
 * storable at all: without it the wizard's value is dropped on save and the
 * article page has nothing to show. The hook writes through a reference in the
 * argument array, so the test has to model that the way the plugin's own hook
 * tests do — see CodecheckPluginUnitTest::buildLoadHandlerArgs().
 */
class SchemaUnitTest extends PKPTestCase
{
    private function schemaObject(): object
    {
        return (object) ['properties' => (object) ['title' => (object) ['type' => 'string']]];
    }

    public function testAddsTheAvailabilityStatementToThePublicationSchema()
    {
        $schema = $this->schemaObject();
        $args = [&$schema];

        (new Schema())->addToSchemaPublication('Schema::get::publication', $args);

        $this->assertObjectHasProperty('dataAvailabilityStatement', $schema->properties);
        $this->assertSame('string', $schema->properties->dataAvailabilityStatement->type);
        $this->assertFalse($schema->properties->dataAvailabilityStatement->multilingual);
        $this->assertTrue($schema->properties->dataAvailabilityStatement->apiSummary);
        $this->assertSame(['nullable'], $schema->properties->dataAvailabilityStatement->validation);
    }

    public function testLeavesThePropertiesItDoesNotOwnAlone()
    {
        $schema = $this->schemaObject();
        $args = [&$schema];

        (new Schema())->addToSchemaPublication('Schema::get::publication', $args);

        $this->assertObjectHasProperty('title', $schema->properties);
        $this->assertSame('string', $schema->properties->title->type);
    }

    public function testTheRepositoryAndManifestFieldsAreNoLongerPublicationMetadata()
    {
        // They moved into codecheck_metadata with the single-store change; a
        // schema entry reappearing here would resurrect the second store.
        $schema = $this->schemaObject();
        $args = [&$schema];

        (new Schema())->addToSchemaPublication('Schema::get::publication', $args);

        foreach (['codeRepository', 'dataRepository', 'manifestFiles'] as $gone) {
            $this->assertObjectNotHasProperty($gone, $schema->properties);
        }
    }

    public function testReturnsFalseSoOtherPluginsStillRun()
    {
        $schema = $this->schemaObject();
        $args = [&$schema];

        $this->assertFalse((new Schema())->addToSchemaPublication('Schema::get::publication', $args));
    }
}
