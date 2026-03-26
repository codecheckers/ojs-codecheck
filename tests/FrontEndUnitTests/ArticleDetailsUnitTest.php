<?php

namespace APP\plugins\generic\codecheck\tests\FrontEndUnitTests;

use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleDetails;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\tests\PKPTestCase;

class ArticleDetailsUnitTest extends PKPTestCase
{
    private ArticleDetails $articleDetails;
    private CodecheckPlugin $mockPlugin;

    protected function setUp(): void
    {
        parent::setUp();
        
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
}