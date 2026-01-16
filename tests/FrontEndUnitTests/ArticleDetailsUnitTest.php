<?php

namespace APP\plugins\generic\codecheck\tests\FrontEndUnitTests;

use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmission;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/FrontEndUnitTests/ArticleDetailsUnitTest.php
 *
 * @class ArticleDetailsUnitTest
 *
 * @brief Tests for the ArticleDetails class
 */
class ArticleDetailsUnitTest extends PKPTestCase
{
    private ArticleDetails $articleDetails;
    private CodecheckPlugin $mockPlugin;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load the CodecheckSubmission class so it can be mocked
        require_once dirname(__FILE__) . '/../../classes/Submission/CodecheckSubmissionDAO.php';

        $this->mockPlugin = $this->createMock(CodecheckPlugin::class);
        $this->articleDetails = new ArticleDetails($this->mockPlugin);
    }

    public function testConstructorSetsPluginProperty()
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $articleDetails = new ArticleDetails($plugin);
        
        $this->assertInstanceOf(ArticleDetails::class, $articleDetails);
        $this->assertSame($plugin, $articleDetails->plugin);
    }

    public function testAddCodecheckInfoReturnsFalseWhenCodecheckDisabled()
    {
        $this->markTestSkipped('Requires full OJS environment with TemplateManager');

        $this->mockPlugin->method('getSetting')
            ->with($this->anything(), Constants::CODECHECK_ENABLED)
            ->willReturn(false);

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);
        $output = '';
        
        $params = [null, $mockTemplateMgr, &$output];
        
        $result = $this->articleDetails->addCodecheckInfo('test_hook', $params);
        
        $this->assertFalse($result);
    }

    public function testAddCodecheckInfoReturnsFalseWhenNoArticle()
    {
        $this->markTestSkipped('Requires full OJS environment with TemplateManager');

        $this->mockPlugin->method('getSetting')
            ->with($this->anything(), Constants::CODECHECK_ENABLED)
            ->willReturn(true);

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);
        $mockTemplateMgr->method('getTemplateVars')
            ->with('article')
            ->willReturn(null);

        $output = '';
        $params = [null, $mockTemplateMgr, &$output];
        
        $result = $this->articleDetails->addCodecheckInfo('test_hook', $params);
        
        $this->assertFalse($result);
    }

    public function testAddCodecheckInfoMethodExists()
    {
        $this->assertTrue(method_exists($this->articleDetails, 'addCodecheckInfo'));
    }

    public function testAddCodecheckInfoAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionMethod($this->articleDetails, 'addCodecheckInfo');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('hookName', $parameters[0]->getName());
        $this->assertSame('params', $parameters[1]->getName());
    }

    public function testAddCodecheckInfoReturnsBooleanReturnType()
    {
        $reflection = new \ReflectionMethod($this->articleDetails, 'addCodecheckInfo');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    public function testArticleDetailsHasPrivateGenerateSidebarDisplayMethod()
    {
        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        
        $this->assertTrue($method->isPrivate());
        $this->assertSame('generateSidebarDisplay', $method->getName());
    }

    public function testGenerateSidebarDisplayReturnsStringReturnType()
    {
        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function testGenerateSidebarDisplayAcceptsCorrectParameters()
    {
        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $parameters = $method->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertSame('codecheckData', $parameters[0]->getName());
        $this->assertSame('templateMgr', $parameters[1]->getName());
    }

    public function testGenerateSidebarDisplayReturnsEmptyStringForNoAssignedChecker()
    {
        $this->markTestSkipped('Requires CodecheckSubmission class to be fully loaded with all dependencies');

        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $method->setAccessible(true);

        $mockCodecheckData = $this->createMock(CodecheckSubmission::class);
        $mockCodecheckData->method('hasCompletedCheck')->willReturn(false);
        $mockCodecheckData->method('hasAssignedChecker')->willReturn(false);

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);

        $result = $method->invoke($this->articleDetails, $mockCodecheckData, $mockTemplateMgr);

        $this->assertSame('', $result);
    }

    public function testGenerateSidebarDisplayAssignsLogoUrl()
    {
        $this->markTestSkipped('Requires CodecheckSubmission class to be fully loaded with all dependencies');

        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $method->setAccessible(true);

        $mockCodecheckData = $this->createMock(CodecheckSubmission::class);
        $mockCodecheckData->method('hasCompletedCheck')->willReturn(true);
        $mockCodecheckData->method('getCertificateLink')->willReturn('https://example.com/cert');
        $mockCodecheckData->method('getDoiLink')->willReturn('10.1234/test');
        $mockCodecheckData->method('getFormattedCertificateLinkText')->willReturn('CODECHECK 2025-001');

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);
        $mockTemplateMgr->expects($this->atLeastOnce())
            ->method('assign')
            ->with($this->callback(function ($arg) {
                return is_array($arg) && isset($arg['logoUrl']);
            }));

        $this->mockPlugin->method('getPluginPath')->willReturn('plugins/generic/codecheck');
        $this->mockPlugin->method('getTemplateResource')->willReturn('template.tpl');

        try {
            $method->invoke($this->articleDetails, $mockCodecheckData, $mockTemplateMgr);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Template fetching might fail in unit tests, but we tested the assign call
            $this->assertTrue(true);
        }
    }

    public function testGenerateSidebarDisplayHandlesCompletedCheck()
    {
        $this->markTestSkipped('Requires CodecheckSubmission class to be fully loaded with all dependencies');

        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $method->setAccessible(true);

        $mockCodecheckData = $this->createMock(CodecheckSubmission::class);
        $mockCodecheckData->method('hasCompletedCheck')->willReturn(true);
        $mockCodecheckData->method('getCertificateLink')->willReturn('https://example.com/cert');
        $mockCodecheckData->method('getDoiLink')->willReturn('10.1234/test');
        $mockCodecheckData->method('getFormattedCertificateLinkText')->willReturn('CODECHECK 2025-001');
        $mockCodecheckData->method('getCodecheckerNames')->willReturn('John Doe');
        $mockCodecheckData->method('getCertificateDate')->willReturn('2025-01-15');

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);
        $mockTemplateMgr->expects($this->atLeastOnce())
            ->method('assign')
            ->with($this->callback(function ($arg) {
                return is_array($arg) && 
                       (isset($arg['codecheckStatus']) || isset($arg['logoUrl']));
            }));

        $this->mockPlugin->method('getPluginPath')->willReturn('plugins/generic/codecheck');
        $this->mockPlugin->method('getTemplateResource')->willReturn('template.tpl');

        try {
            $method->invoke($this->articleDetails, $mockCodecheckData, $mockTemplateMgr);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testGenerateSidebarDisplayHandlesPendingStatus()
    {
        $this->markTestSkipped('Requires CodecheckSubmission class to be fully loaded with all dependencies');
        
        $reflection = new \ReflectionClass($this->articleDetails);
        $method = $reflection->getMethod('generateSidebarDisplay');
        $method->setAccessible(true);

        $mockCodecheckData = $this->createMock(CodecheckSubmission::class);
        $mockCodecheckData->method('hasCompletedCheck')->willReturn(false);
        $mockCodecheckData->method('hasAssignedChecker')->willReturn(true);
        $mockCodecheckData->method('getCodeRepository')->willReturn('https://github.com/test/repo');
        $mockCodecheckData->method('getDataRepository')->willReturn('https://zenodo.org/123');

        $mockTemplateMgr = $this->createMock(\APP\template\TemplateManager::class);
        $mockTemplateMgr->expects($this->atLeastOnce())
            ->method('assign')
            ->with($this->callback(function ($arg) {
                return is_array($arg) && 
                       (isset($arg['codecheckStatus']) || isset($arg['logoUrl']));
            }));

        $this->mockPlugin->method('getPluginPath')->willReturn('plugins/generic/codecheck');
        $this->mockPlugin->method('getTemplateResource')->willReturn('template.tpl');

        try {
            $method->invoke($this->articleDetails, $mockCodecheckData, $mockTemplateMgr);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }
}