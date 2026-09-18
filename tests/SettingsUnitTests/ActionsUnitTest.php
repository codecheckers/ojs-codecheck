<?php

namespace APP\plugins\generic\codecheck\tests\SettingsUnitTests;

use APP\plugins\generic\codecheck\classes\Settings\Actions;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\core\Request;
use PKP\core\PKPRouter;
use PKP\linkAction\LinkAction;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/SettingsUnitTests/ActionsUnitTest.php
 *
 * @class ActionsUnitTest
 *
 * @brief Tests for the Settings Actions class
 *
 * Actions::execute() decides whether the plugin list shows a "Settings" link
 * and what that link points at. The end-to-end suite proves the link works —
 * cy.setCodecheckSetting() finds it by href and opens the form — so these
 * cover what e2e cannot reach: the disabled branch, the ordering of the link
 * among existing actions, and the exact URL parameters, which e2e would only
 * report as a missing element.
 */
class ActionsUnitTest extends PKPTestCase
{
    private Actions $actions;
    private CodecheckPlugin $mockPlugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPlugin = $this->createMock(CodecheckPlugin::class);
        $this->actions = new Actions($this->mockPlugin);
    }

    /**
     * Build a request whose router records the URL parameters it is handed.
     */
    private function mockEnabledPluginRequest(?PKPRouter $router = null): Request
    {
        $this->mockPlugin->method('getEnabled')->willReturn(true);
        $this->mockPlugin->method('getName')->willReturn('codecheck');
        $this->mockPlugin->method('getDisplayName')->willReturn('CODECHECK Plugin');

        if ($router === null) {
            $router = $this->createMock(PKPRouter::class);
            $router->method('url')->willReturn('https://example.com/settings');
        }

        $request = $this->createMock(Request::class);
        $request->method('getRouter')->willReturn($router);

        return $request;
    }

    public function testExecuteReturnsParentActionsWhenPluginDisabled()
    {
        $this->mockPlugin->method('getEnabled')
            ->willReturn(false);

        $parentActions = [
            $this->createMock(LinkAction::class),
            $this->createMock(LinkAction::class)
        ];

        $result = $this->actions->execute($this->createMock(Request::class), [], $parentActions);

        $this->assertSame($parentActions, $result);
    }

    public function testExecuteAddsASettingsLinkPointingAtThePluginManageRoute()
    {
        $router = $this->createMock(PKPRouter::class);
        $router->expects($this->once())
            ->method('url')
            ->with(
                $this->anything(),
                null,
                null,
                'manage',
                null,
                $this->callback(function ($params) {
                    return $params['verb'] === 'settings'
                        && $params['plugin'] === 'codecheck'
                        && $params['category'] === 'generic';
                })
            )
            ->willReturn('https://example.com/settings');

        $result = $this->actions->execute($this->mockEnabledPluginRequest($router), [], []);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(LinkAction::class, $result[0]);
        $this->assertSame('settings', $result[0]->getId());
    }

    public function testExecutePrependsSettingsActionToExistingActions()
    {
        $existingAction = $this->createMock(LinkAction::class);

        $result = $this->actions->execute($this->mockEnabledPluginRequest(), [], [$existingAction]);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(LinkAction::class, $result[0]);
        $this->assertSame($existingAction, $result[1]);
    }
}
