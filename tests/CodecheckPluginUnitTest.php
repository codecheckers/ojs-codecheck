<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\plugins\GenericPlugin;
use PKP\tests\PKPTestCase;
use PKP\components\forms\FormComponent;

class CodecheckPluginUnitTest extends PKPTestCase
{
    private CodecheckPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new CodecheckPlugin();
    }

    public function testPluginExtendsGenericPlugin()
    {
        $this->assertInstanceOf(GenericPlugin::class, $this->plugin);
    }

    public function testAddOptInToSchemaAddsCodecheckOptInProperty()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $result = $this->plugin->addOptInToSchema('test_hook', $args);
        
        $this->assertFalse($result);
        $this->assertObjectHasProperty('codecheckOptIn', $mockSchema->properties);
        $this->assertSame('boolean', $mockSchema->properties->codecheckOptIn->type);
        $this->assertTrue($mockSchema->properties->codecheckOptIn->apiSummary);
    }

    public function testAddOptInToSchemaAddsRetrieveReserveCertificateIdentifier()
    {
        $mockSchema = (object)['properties' => (object)[]];
        $args = [&$mockSchema];
        
        $this->plugin->addOptInToSchema('test_hook', $args);
        
        $this->assertObjectHasProperty('retrieveReserveCertificateIdentifier', $mockSchema->properties);
        $this->assertSame('string', $mockSchema->properties->retrieveReserveCertificateIdentifier->type);
        $this->assertTrue($mockSchema->properties->retrieveReserveCertificateIdentifier->apiSummary);
    }

    public function testAddOptInCheckboxDoesNotAddFieldToOtherForms()
    {
        $mockForm = $this->createMock(FormComponent::class);
        $mockForm->id = 'someOtherForm';
        
        $mockForm->expects($this->never())
            ->method('addField');
        
        $result = $this->plugin->addOptInCheckbox('test_hook', $mockForm);
        
        $this->assertFalse($result);
    }

    public function testSaveOptInReturnsFalseWhenNoOptInData()
    {
        $mockSubmission = $this->createMock(\APP\submission\Submission::class);
        $mockSubmission->expects($this->never())
            ->method('setData');
        
        $params = [$mockSubmission, null, []];
        
        $result = $this->plugin->saveOptIn('test_hook', $params);
        
        $this->assertFalse($result);
    }

    public function testSaveOptInSavesDataWhenPresent()
    {
        $mockSubmission = $this->createMock(\APP\submission\Submission::class);
        $mockSubmission->expects($this->once())
            ->method('setData')
            ->with('codecheckOptIn', true);
        
        $params = [$mockSubmission, null, ['codecheckOptIn' => true]];
        
        $result = $this->plugin->saveOptIn('test_hook', $params);
        
        $this->assertFalse($result);
    }

    public function testSaveWizardFieldsFromRequestReturnsFalseWhenNoSubmission()
    {
        $params = [null, null];
        
        $result = $this->plugin->saveWizardFieldsFromRequest('test_hook', $params);
        
        $this->assertFalse($result);
    }
}