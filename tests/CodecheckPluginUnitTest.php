<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\plugins\generic\codecheck\controllers\page\CodecheckPageHandler;
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

    /**
     * OJS calls this hook as
     *   Hook::call('LoadHandler', [&$page, &$op, &$sourceFile, &$handler])
     * so the argument array carries references and the handler writes back
     * through them. Reproduce that shape — a plain array would not show the
     * write-back at all.
     *
     * @return array{0: array, 1: callable} the hook args and a reader for the
     *         current [$page, $op, $handler] values
     */
    private function buildLoadHandlerArgs(string $page, string $op): array
    {
        $sourceFile = [];
        $handler = null;

        $args = [&$page, &$op, &$sourceFile, &$handler];

        $read = function () use (&$page, &$op, &$handler) {
            return [$page, $op, $handler];
        };

        return [$args, $read];
    }

    public function testSetCodecheckPageHandlerClaimsTheCodecheckInfoPage()
    {
        [$args, $read] = $this->buildLoadHandlerArgs('codecheck', 'info');

        $result = $this->plugin->setCodecheckPageHandler('LoadHandler', $args);
        [$page, $op, $handler] = $read();

        $this->assertTrue($result);
        $this->assertSame('pages', $page);
        $this->assertSame('view', $op);
        $this->assertInstanceOf(CodecheckPageHandler::class, $handler);
    }

    /**
     * Regression test: the condition used to be written with `=` instead of
     * `===`, and because `&&` binds tighter than `=` it evaluated as
     * `$page = ('codecheck' && $op == 'info')`. That claimed *every* page
     * whose operation was `info`, no matter which page was requested.
     */
    public function testSetCodecheckPageHandlerDoesNotClaimOtherPagesWithInfoOperation()
    {
        [$args, $read] = $this->buildLoadHandlerArgs('about', 'info');

        $result = $this->plugin->setCodecheckPageHandler('LoadHandler', $args);
        [$page, $op, $handler] = $read();

        $this->assertFalse($result);
        $this->assertSame('about', $page);
        $this->assertSame('info', $op);
        $this->assertNull($handler);
    }

    /**
     * Regression test: `$page` is a reference into the hook arguments, so the
     * accidental assignment also overwrote the requested page name with a
     * boolean on every request that did not match — corrupting routing for
     * pages this plugin has nothing to do with.
     */
    public function testSetCodecheckPageHandlerLeavesHookArgumentsUntouchedWhenNotMatching()
    {
        [$args, $read] = $this->buildLoadHandlerArgs('index', 'index');

        $result = $this->plugin->setCodecheckPageHandler('LoadHandler', $args);
        [$page, $op, $handler] = $read();

        $this->assertFalse($result);
        $this->assertSame('index', $page);
        $this->assertSame('index', $op);
        $this->assertNull($handler);
    }

    public function testSetCodecheckPageHandlerDoesNotClaimCodecheckPageWithOtherOperation()
    {
        [$args, $read] = $this->buildLoadHandlerArgs('codecheck', 'index');

        $result = $this->plugin->setCodecheckPageHandler('LoadHandler', $args);
        [$page, $op, $handler] = $read();

        $this->assertFalse($result);
        $this->assertSame('codecheck', $page);
        $this->assertSame('index', $op);
        $this->assertNull($handler);
    }
}