<?php

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\core\Application;
use APP\core\Request;
use APP\journal\Journal;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckPublicationValidator;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\submission\Submission;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;

/**
 * The validator can stop a publication, so what it does before it starts
 * checking matters as much as the checks themselves.
 *
 * Covered here: that it only acts on an opted-in submission, and that it
 * survives having no submission to look at. The checks themselves each read the
 * database or the network in their first line and are covered by
 * `cypress/tests/e2e/publication-validation.cy.js`.
 *
 * The class reaches the request through `Application::get()`, so a stub
 * application goes into `Registry` for the duration of each test.
 */
class CodecheckPublicationValidatorUnitTest extends PKPTestCase
{
    private mixed $previousApplication = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApplication = Registry::get('application');
        $this->stubApplication();
    }

    protected function tearDown(): void
    {
        Registry::set('application', $this->previousApplication);
        parent::tearDown();
    }

    /**
     * An application whose request has a context and a router with no handler.
     *
     * That is what the API router looks like from here, and publishing happens
     * through the API — see testAMissingSubmissionIsNotFatal().
     */
    private function stubApplication(): void
    {
        $journal = $this->createMock(Journal::class);
        $journal->method('getId')->willReturn(1);

        $router = $this->createMock(\PKP\core\PKPRouter::class);
        $router->method('getHandler')->willReturn(null);

        $request = $this->createMock(Request::class);
        $request->method('getContext')->willReturn($journal);
        $request->method('getRouter')->willReturn($router);
        $request->method('getUserVar')->willReturn(null);

        $application = $this->createMock(Application::class);
        $application->method('getRequest')->willReturn($request);

        Registry::set('application', $application);
    }

    private function submissionOptedIn(bool $optedIn): Submission
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getData')->with('codecheckOptIn')->willReturn($optedIn ? 1 : null);
        $submission->method('getId')->willReturn(7);

        return $submission;
    }

    private function validatorFor(mixed $submission): CodecheckPublicationValidator
    {
        return new CodecheckPublicationValidator($this->createMock(CodecheckPlugin::class), $submission);
    }

    public function testASubmissionThatIsNotOptedInIsNeverBlocked()
    {
        // Journals run CODECHECK on some submissions, not all; the rest must
        // publish exactly as they would without the plugin installed.
        $validator = $this->validatorFor($this->submissionOptedIn(false));

        $this->assertTrue($validator->validatePublication());
        $this->assertSame([], $validator->getErrors());
    }

    public function testAMissingSubmissionIsNotFatal()
    {
        // Regression: the validator used to ask the router's handler for the
        // authorized submission. Under the API router — which is how OJS
        // publishes — there is no handler, so every publish attempt threw
        // "Call to a member function getAuthorizedContextObject() on null".
        // PKP caught it, logged "failed to handle the hook" and carried on, so
        // publication validation silently did nothing at all.
        $validator = $this->validatorFor(null);

        $this->assertTrue($validator->validatePublication());
        $this->assertSame([], $validator->getErrors());
    }

    public function testErrorsStartEmpty()
    {
        $this->assertSame([], $this->validatorFor($this->submissionOptedIn(true))->getErrors());
    }
}
