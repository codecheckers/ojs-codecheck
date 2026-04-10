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
 */
class ActionsUnitTest extends PKPTestCase
{
    private Actions $actions;
    private CodecheckPlugin $mockPlugin;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPlugin = $this->createMock(CodecheckPlugin::class);
        $this->actions = new Actions($this->mockPlugin);
    }

    public function testConstructorSetsPluginProperty()
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $actions = new Actions($plugin);
        
        $this->assertInstanceOf(Actions::class, $actions);
        $this->assertSame($plugin, $actions->plugin);
    }

    public function testExecuteReturnsParentActionsWhenPluginDisabled()
    {
        $this->mockPlugin->method('getEnabled')
            ->willReturn(false);

        $mockRequest = $this->createMock(Request::class);
        $parentActions = [
            $this->createMock(LinkAction::class),
            $this->createMock(LinkAction::class)
        ];

        $result = $this->actions->execute($mockRequest, [], $parentActions);

        $this->assertSame($parentActions, $result);
        $this->assertCount(2, $result);
    }

    public function testExecuteAddsSettingsActionWhenPluginEnabled()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');

        $this->mockPlugin->method('getEnabled')
            ->willReturn(true);

        $this->mockPlugin->method('getName')
            ->willReturn('codecheck');

        $this->mockPlugin->method('getDisplayName')
            ->willReturn('CODECHECK Plugin');

        $mockRouter = $this->createMock(PKPRouter::class);
        $mockRouter->method('url')
            ->willReturn('https://example.com/settings');

        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getRouter')
            ->willReturn($mockRouter);

        $parentActions = [];

        $result = $this->actions->execute($mockRequest, [], $parentActions);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(LinkAction::class, $result[0]);
    }

    public function testExecutePrependsSettingsActionToExistingActions()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');

        $this->mockPlugin->method('getEnabled')
            ->willReturn(true);

        $this->mockPlugin->method('getName')
            ->willReturn('codecheck');

        $this->mockPlugin->method('getDisplayName')
            ->willReturn('CODECHECK Plugin');

        $mockRouter = $this->createMock(PKPRouter::class);
        $mockRouter->method('url')
            ->willReturn('https://example.com/settings');

        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getRouter')
            ->willReturn($mockRouter);

        $existingAction = $this->createMock(LinkAction::class);
        $parentActions = [$existingAction];

        $result = $this->actions->execute($mockRequest, [], $parentActions);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(LinkAction::class, $result[0]);
        $this->assertSame($existingAction, $result[1]);
    }

    public function testExecuteCreatesLinkActionWithCorrectId()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');

        $this->mockPlugin->method('getEnabled')
            ->willReturn(true);

        $this->mockPlugin->method('getName')
            ->willReturn('codecheck');

        $this->mockPlugin->method('getDisplayName')
            ->willReturn('CODECHECK Plugin');

        $mockRouter = $this->createMock(PKPRouter::class);
        $mockRouter->method('url')
            ->willReturn('https://example.com/settings');

        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getRouter')
            ->willReturn($mockRouter);

        $result = $this->actions->execute($mockRequest, [], []);

        $this->assertCount(1, $result);
        $linkAction = $result[0];
        $this->assertSame('settings', $linkAction->getId());
    }

    public function testExecuteBuildsCorrectUrlParameters()
    {
        $this->markTestSkipped('Requires full OJS environment with translator');
        
        $this->mockPlugin->method('getEnabled')
            ->willReturn(true);

        $this->mockPlugin->method('getName')
            ->willReturn('codecheck');

        $this->mockPlugin->method('getDisplayName')
            ->willReturn('CODECHECK Plugin');

        $mockRouter = $this->createMock(PKPRouter::class);
        $mockRouter->expects($this->once())
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

        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getRouter')
            ->willReturn($mockRouter);

        $this->actions->execute($mockRequest, [], []);
    }
}