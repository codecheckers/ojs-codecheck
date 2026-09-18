<?php

namespace APP\plugins\generic\codecheck\tests\FrontEndUnitTests;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\FrontEnd\ArticleAvailability;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\tests\PKPTestCase;

/**
 * The availability section's three settings each treat "unset" differently, and
 * none of them can be read off the stored value alone: showing defaults to on,
 * hiding an empty statement defaults to off, and an empty heading falls back to
 * a localised one. Those defaults are the behaviour a journal gets before it has
 * touched the settings form at all, so they are pinned here.
 *
 * `__()` returns the locale key in these tests (see tests/FakeTranslator.php),
 * so the assertions are on keys rather than on English text.
 */
class ArticleAvailabilityUnitTest extends PKPTestCase
{
    private const CONTEXT_ID = 1;

    /** Builds the class with a plugin whose settings answer from $settings. */
    private function availabilityWithSettings(array $settings): ArticleAvailability
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $plugin->method('getSetting')->willReturnCallback(
            fn ($contextId, $name) => $settings[$name] ?? null
        );

        return new ArticleAvailability($plugin);
    }

    public function testStatementIsRenderedAsGiven()
    {
        $availability = $this->availabilityWithSettings([]);

        $this->assertSame(
            'Code available at https://example.org/repo',
            $availability->resolveStatement('Code available at https://example.org/repo', 'Heading', false)
        );
    }

    public function testEmptyStatementIsReplacedByAMessageNamingTheHeading()
    {
        $availability = $this->availabilityWithSettings([]);

        // A whitespace-only statement is as empty as a missing one.
        foreach ([null, '', '   ', "\n"] as $stored) {
            $this->assertSame(
                'plugins.generic.codecheck.availabilityStatement.none',
                $availability->resolveStatement($stored, 'Heading', false)
            );
        }
    }

    public function testEmptyStatementOmitsTheSectionWhenTheJournalHidesIt()
    {
        $availability = $this->availabilityWithSettings([]);

        $this->assertNull($availability->resolveStatement(null, 'Heading', true));

        // Hiding applies to the empty case only; a real statement still shows.
        $this->assertSame(
            'A statement',
            $availability->resolveStatement('A statement', 'Heading', true)
        );
    }

    public function testHidingAnEmptyStatementIsOffUntilTheJournalAsksForIt()
    {
        $this->assertFalse(
            $this->availabilityWithSettings([])->hidesEmptyStatement(self::CONTEXT_ID)
        );

        $this->assertTrue(
            $this->availabilityWithSettings([
                Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT => true,
            ])->hidesEmptyStatement(self::CONTEXT_ID)
        );
    }

    public function testTheSectionShowsUntilTheJournalSwitchesItOff()
    {
        $this->assertTrue(
            $this->availabilityWithSettings([])->isEnabled(self::CONTEXT_ID)
        );

        $this->assertFalse(
            $this->availabilityWithSettings([
                Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT => false,
            ])->isEnabled(self::CONTEXT_ID)
        );
    }

    public function testHeadingFallsBackToTheLocalisedDefaultWhenCleared()
    {
        foreach ([[], [Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING => '  ']] as $settings) {
            $this->assertSame(
                'plugins.generic.codecheck.dataSoftwareAvailability',
                $this->availabilityWithSettings($settings)->getHeading(self::CONTEXT_ID)
            );
        }

        $this->assertSame(
            'Data availability',
            $this->availabilityWithSettings([
                Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING => 'Data availability',
            ])->getHeading(self::CONTEXT_ID)
        );
    }
}
