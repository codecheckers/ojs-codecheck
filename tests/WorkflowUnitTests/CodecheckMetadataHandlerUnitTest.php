<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;
use PKP\tests\PKPTestCase;

class CodecheckMetadataHandlerUnitTest extends PKPTestCase
{
    private CodecheckMetadataHandler $handler;
    private $mockRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRequest = $this->createMock(\APP\core\Request::class);
        $this->mockRequest->method('getUserVar')
            ->with('submissionId')
            ->willReturn(1);
        
        $this->handler = new CodecheckMetadataHandler($this->mockRequest);
    }

    public function testConstructorSetsSubmissionId()
    {
        $mockRequest = $this->createMock(\APP\core\Request::class);
        $mockRequest->method('getUserVar')
            ->with('submissionId')
            ->willReturn(123);
        
        $handler = new CodecheckMetadataHandler($mockRequest);
        
        $this->assertInstanceOf(CodecheckMetadataHandler::class, $handler);
        $this->assertSame(123, $handler->getSubmissionId());
    }

    public function testGetSubmissionIdReturnsCorrectValue()
    {
        $this->assertSame(1, $this->handler->getSubmissionId());
    }

    public function testGetAuthorsReturnsEmptyArrayForNullPublication()
    {
        $result = $this->handler->getAuthors(null);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}