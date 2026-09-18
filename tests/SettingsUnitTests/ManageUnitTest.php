<?php

namespace APP\plugins\generic\codecheck\tests\SettingsUnitTests;

use APP\plugins\generic\codecheck\classes\Settings\Manage;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\core\Request;
use PKP\core\JSONMessage;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SettingsUnitTests/ManageUnitTest.php
 *
 * @class ManageUnitTest
 *
 * @brief Tests for the Settings Manage class
 *
 * Only the verb dispatch is covered here. The `settings` verb builds a real
 * SettingsForm, which resolves journal locales through a database-backed
 * facade, so exercising it means booting the application — and the end-to-end
 * suite already opens the settings form, changes a value and saves it. What
 * remains is the fall-through for verbs Manage does not handle, which e2e has
 * no straightforward way to trigger.
 */
class ManageUnitTest extends PKPTestCase
{
    private Manage $manage;
    private CodecheckPlugin $mockPlugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPlugin = $this->createMock(CodecheckPlugin::class);
        $this->manage = new Manage($this->mockPlugin);
    }

    private function requestWithVerb(?string $verb): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getUserVar')
            ->willReturnMap([
                ['verb', $verb],
                ['save', null]
            ]);

        return $request;
    }

    public function testExecuteReportsFailureForAnUnknownVerb()
    {
        $result = $this->manage->execute([], $this->requestWithVerb('invalid_verb'));

        $this->assertInstanceOf(JSONMessage::class, $result);
        $this->assertFalse($result->getStatus());
    }

    public function testExecuteReportsFailureWhenNoVerbIsGiven()
    {
        $result = $this->manage->execute([], $this->requestWithVerb(null));

        $this->assertInstanceOf(JSONMessage::class, $result);
        $this->assertFalse($result->getStatus());
    }
}
