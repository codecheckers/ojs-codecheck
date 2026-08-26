<?php

namespace APP\plugins\generic\codecheck\tests\FrontEndUnitTests;

use APP\core\Application;
use APP\core\Request;
use APP\journal\Journal;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\FrontEnd\IssueTOC;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;

/**
 * The badge in an issue's table of contents is decided before anything is
 * rendered: the journal's setting, then the article's opt-in, then whether the
 * check is finished. Getting that wrong is visible to readers on a public
 * listing — either a badge on an article that was never checked, or none on one
 * that was.
 *
 * The gates up to the opt-in are covered here. The completed-check gate needs
 * `CodecheckSubmissionDAO`, which queries the database, so it belongs to the
 * e2e suite (`cypress/tests/e2e/issue-toc-badge.cy.js`).
 *
 * `IssueTOC` reaches the request through `Application::get()`, which reads
 * `Registry`, so a stub application is put there for the duration of each test
 * rather than booting OJS.
 */
class IssueTOCUnitTest extends PKPTestCase
{
    private const CONTEXT_ID = 1;

    /** Whatever was in the registry before the test replaced it. */
    private mixed $previousApplication = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApplication = Registry::get('application');
    }

    protected function tearDown(): void
    {
        // Leave the registry as it was found: it is static and shared by every
        // test in the process.
        Registry::set('application', $this->previousApplication);
        parent::tearDown();
    }

    /** Puts an application in the registry whose request has a context. */
    private function stubApplication(): void
    {
        $journal = $this->createMock(Journal::class);
        $journal->method('getId')->willReturn(self::CONTEXT_ID);

        $request = $this->createMock(Request::class);
        $request->method('getContext')->willReturn($journal);

        $application = $this->createMock(Application::class);
        $application->method('getRequest')->willReturn($request);

        Registry::set('application', $application);
    }

    /** An IssueTOC whose plugin answers $showInTOC for the TOC setting. */
    private function issueTOC(mixed $showInTOC): IssueTOC
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $plugin->method('getSetting')->willReturnCallback(
            fn ($contextId, $name) => $name === Constants::CODECHECK_SHOW_IN_TOC ? $showInTOC : null
        );

        $this->stubApplication();

        return new IssueTOC($plugin);
    }

    /**
     * Runs the hook and returns what it appended to the output.
     *
     * @param mixed $article what the template offers as the current article
     */
    private function runHook(IssueTOC $issueTOC, FakeTemplateManager $templateMgr, mixed &$returned = null): string
    {
        $output = 'existing output';
        $params = [[], $templateMgr, &$output];

        $returned = $issueTOC->addCodecheckBadge('Templates::Issue::Issue::Article', $params);

        return substr($output, strlen('existing output'));
    }

    /**
     * A stand-in for the template manager offering $article.
     *
     * Not a PHPUnit mock: TemplateManager extends Smarty, whose class body
     * requires plugin files off a relative include path that only resolves
     * inside a booted OJS. The hook does not type-hint the manager, so a plain
     * object with the one method it calls is enough — and it can count the
     * calls, which is how the tests below tell "stopped at the gate" from
     * "carried on".
     */
    private function templateManagerOffering(mixed $article): FakeTemplateManager
    {
        return new FakeTemplateManager($article);
    }

    public function testTheSettingSwitchedOffStopsBeforeTheArticleIsEvenLookedAt()
    {
        // Nothing about the article matters once the journal has said no, so
        // the template is never asked for one.
        $templateMgr = $this->templateManagerOffering(null);

        $appended = $this->runHook($this->issueTOC(false), $templateMgr, $returned);

        $this->assertSame(0, $templateMgr->articleLookups, 'the article must not even be looked up');
        $this->assertSame('', $appended);
        $this->assertFalse($returned, 'the hook must return false so other plugins still run');
    }

    public function testAnUnsetSettingIsTreatedAsOnAndCarriesOn()
    {
        // The badge predates the setting: a journal that never configured it
        // keeps what it had. Reaching the article lookup is what shows the gate
        // let this through.
        $templateMgr = $this->templateManagerOffering(null);

        $appended = $this->runHook($this->issueTOC(null), $templateMgr, $returned);

        $this->assertSame(1, $templateMgr->articleLookups, 'the gate must have let this through');
        $this->assertSame('', $appended);
        $this->assertFalse($returned);
    }

    public function testAnArticleThatIsNotOptedInGetsNoBadge()
    {
        $article = $this->createMock(\APP\submission\Submission::class);
        $article->method('getData')->with('codecheckOptIn')->willReturn(null);

        $appended = $this->runHook($this->issueTOC(true), $this->templateManagerOffering($article), $returned);

        $this->assertSame('', $appended);
        $this->assertFalse($returned);
    }

    public function testNoArticleInTheTemplateGetsNoBadge()
    {
        // The hook also fires in listings that are not article summaries.
        $appended = $this->runHook($this->issueTOC(true), $this->templateManagerOffering(null), $returned);

        $this->assertSame('', $appended);
        $this->assertFalse($returned);
    }
}

/**
 * The little of the template manager that IssueTOC actually uses.
 */
class FakeTemplateManager
{
    public int $articleLookups = 0;

    public function __construct(private mixed $article) {}

    public function getTemplateVars(string $name): mixed
    {
        if ($name === 'article') {
            $this->articleLookups++;
        }

        return $this->article;
    }
}
