<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\plugins\GenericPlugin;
use PKP\tests\PKPTestCase;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldOptions;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckPluginUnitTest.php
 *
 * @class CodecheckPluginUnitTest
 *
 * @brief Tests for the CodecheckPlugin class
 */
class CodecheckPluginUnitTest extends PKPTestCase
{
    private CodecheckPlugin $plugin;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new CodecheckPlugin();
    }

    public function testPluginExtendsGenericPlugin()
    {
        $this->assertInstanceOf(GenericPlugin::class, $this->plugin);
    }

    public function testPluginHasRegisterMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'register'),
            'Plugin should have register() method'
        );
    }

    public function testRegisterMethodHasBooleanReturnType()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'register');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testRegisterMethodAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'register');
        $parameters = $reflection->getParameters();
        
        $this->assertGreaterThanOrEqual(2, count($parameters));
        $this->assertSame('category', $parameters[0]->getName());
        $this->assertSame('path', $parameters[1]->getName());
    }

    public function testPluginHasGetDisplayNameMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'getDisplayName'),
            'Plugin should have getDisplayName() method'
        );
    }

    public function testGetDisplayNameReturnsString()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'getDisplayName');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function testPluginHasGetDescriptionMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'getDescription'),
            'Plugin should have getDescription() method'
        );
    }

    public function testGetDescriptionReturnsString()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'getDescription');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function testPluginHasGetActionsMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'getActions'),
            'Plugin should have getActions() method'
        );
    }

    public function testGetActionsReturnsArray()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'getActions');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    public function testPluginHasSetEnabledMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'setEnabled'),
            'Plugin should have setEnabled() method'
        );
    }

    public function testPluginHasAddOptInToSchemaMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'addOptInToSchema'),
            'Plugin should have addOptInToSchema() method'
        );
    }

    public function testAddOptInToSchemaReturnsBool()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'addOptInToSchema');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testAddOptInToSchemaAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'addOptInToSchema');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('args', $parameters[1]->getName());
    }

    public function testPluginHasAddOptInCheckboxMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'addOptInCheckbox'),
            'Plugin should have addOptInCheckbox() method'
        );
    }

    public function testAddOptInCheckboxReturnsBool()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'addOptInCheckbox');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testAddOptInCheckboxAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'addOptInCheckbox');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('form', $parameters[1]->getName());
    }

    public function testPluginHasSaveOptInMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'saveOptIn'),
            'Plugin should have saveOptIn() method'
        );
    }

    public function testSaveOptInReturnsBool()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'saveOptIn');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testSaveOptInAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'saveOptIn');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('params', $parameters[1]->getName());
    }

    public function testPluginHasSaveWizardFieldsFromRequestMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'saveWizardFieldsFromRequest'),
            'Plugin should have saveWizardFieldsFromRequest() method'
        );
    }

    public function testSaveWizardFieldsFromRequestReturnsBool()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'saveWizardFieldsFromRequest');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testSaveWizardFieldsFromRequestAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'saveWizardFieldsFromRequest');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('params', $parameters[1]->getName());
    }

    public function testPluginHasCallbackTemplateManagerDisplayMethod()
    {
        $this->assertTrue(
            method_exists($this->plugin, 'callbackTemplateManagerDisplay'),
            'Plugin should have callbackTemplateManagerDisplay() method'
        );
    }

    public function testCallbackTemplateManagerDisplayReturnsBool()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'callbackTemplateManagerDisplay');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testCallbackTemplateManagerDisplayAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->plugin, 'callbackTemplateManagerDisplay');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('args', $parameters[1]->getName());
    }

    public function testPluginHasPrivateAddAssetsMethod()
    {
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('addAssets');
        
        $this->assertTrue($method->isPrivate());
    }

    public function testAddAssetsHasVoidReturnType()
    {
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('addAssets');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('void', $returnType->getName());
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

    public function testAddOptInCheckboxAddsFieldToSubmitStartForm()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');

        $mockForm = $this->createMock(FormComponent::class);
        $mockForm->id = 'submitStart';
        
        $mockForm->expects($this->once())
            ->method('addField')
            ->with($this->isInstanceOf(FieldOptions::class));
        
        $result = $this->plugin->addOptInCheckbox('test_hook', $mockForm);
        
        $this->assertFalse($result);
    }

    public function testAddOptInCheckboxAddsFieldToSubmissionStartForm()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');
        
        $mockForm = $this->createMock(FormComponent::class);
        $mockForm->id = 'submissionStart';
        
        $mockForm->expects($this->once())
            ->method('addField')
            ->with($this->isInstanceOf(FieldOptions::class));
        
        $result = $this->plugin->addOptInCheckbox('test_hook', $mockForm);
        
        $this->assertFalse($result);
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

    public function testPluginHasAllRequiredPublicMethods()
    {
        $requiredMethods = [
            'register',
            'getDisplayName',
            'getDescription',
            'getActions',
            'setEnabled',
            'addOptInToSchema',
            'addOptInCheckbox',
            'saveOptIn',
            'saveWizardFieldsFromRequest',
            'callbackTemplateManagerDisplay'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->plugin, $method),
                "Plugin should have public method: {$method}"
            );
        }
    }

    public function testPluginClassAliasExists()
    {
        // Test that the class alias is set up correctly for backwards compatibility
        if (!PKP_STRICT_MODE) {
            $this->assertTrue(
                class_exists('\CodecheckPlugin', false),
                'CodecheckPlugin class alias should exist'
            );
        } else {
            $this->assertTrue(true); // Skip in strict mode
        }
    }
}