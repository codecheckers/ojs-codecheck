<?php

namespace APP\plugins\generic\codecheck\tests\LogUnitTests;

use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/LogUnitTests/CodecheckLoggerUnitTest.php
 *
 * @class CodecheckLoggerUnitTest
 *
 * @brief Tests for the CodecheckLogger class
 */
class CodecheckLoggerUnitTest extends PKPTestCase
{
    private string $logFile;
    private string|false $previousErrorLog;

    /**
     * Redirect error_log() into a temporary file so the emitted lines can be
     * asserted on instead of ending up in the system log.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = tempnam(sys_get_temp_dir(), 'codecheck-log-');
        $this->previousErrorLog = ini_get('error_log');
        ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    /**
     * Every level the plugin calls must exist. `warning()` was missing while
     * CodecheckSubmission::getRepositories() already called it, which made that
     * branch fatal with "Call to undefined method".
     */
    public function testProvidesEveryLogLevelUsedInThePlugin(): void
    {
        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            $this->assertTrue(
                method_exists(CodecheckLogger::class, $level),
                "CodecheckLogger is missing the '{$level}' level"
            );
        }
    }

    /**
     * @dataProvider logLevelProvider
     */
    public function testWritesPrefixedMessageForLevel(string $level, string $expectedPrefix): void
    {
        CodecheckLogger::$level('a message');

        $this->assertStringContainsString($expectedPrefix . ' a message', file_get_contents($this->logFile));
    }

    public static function logLevelProvider(): array
    {
        return [
            'debug'   => ['debug', '[codecheck][debug]'],
            'info'    => ['info', '[codecheck][info]'],
            'warning' => ['warning', '[codecheck][warning]'],
            'error'   => ['error', '[codecheck][error]'],
        ];
    }
}
