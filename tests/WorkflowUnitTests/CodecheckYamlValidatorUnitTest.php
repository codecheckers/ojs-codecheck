<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\Workflow\CodecheckYamlValidator;
use Symfony\Component\Yaml\Exception\ParseException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/WorkflowUnitTests/CodecheckYamlValidatorUnitTest.php
 *
 * @class CodecheckYamlValidatorUnitTest
 *
 * @brief Tests for the CodecheckYamlValidator class
 */
class CodecheckYamlValidatorUnitTest extends PKPTestCase
{
    private CodecheckYamlValidator $codecheckYamlValidator;
    /**
     * Set up the test environment
     */
    protected function setUp(): void
	{
		parent::setUp();

        $this->codecheckYamlValidator = new CodecheckYamlValidator("example: {yaml: true}");
	}

    public function testYamlValidatorStructurallyValidYaml()
    {
        $validYamlContent = "this:
    is:
        valid: |
            yaml
            content.
";
        $this->codecheckYamlValidator = new CodecheckYamlValidator($validYamlContent);
        $this->codecheckYamlValidator->validateYaml();
        $this->expectNotToPerformAssertions();
    }

    public function testYamlValidatorStructurallyInvalidYaml()
    {
        $invalidYamlContent = "this:
   >is:
       invalid: |
            yaml
            content.
";
        $expectedExceptionMessage = 'The reserved indicator ">" cannot start a plain scalar; you need to quote the scalar at line 2 (near ">is:").';
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->codecheckYamlValidator = new CodecheckYamlValidator($invalidYamlContent);
        $this->codecheckYamlValidator->validateYaml();
    }
}