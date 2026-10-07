<?php

/**
 * @file tests/CodecheckPluginHooksUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckPluginHooksUnitTest
 *
 * @brief Every hook the plugin registers points at a method that exists.
 *
 * `Hook::add()` takes a callable, so a callback naming a method that is not
 * there is a TypeError thrown from `register()` — which takes the whole plugin
 * down on every request, not just the feature that hook serves.
 *
 * This is not hypothetical. Deleting the hand-rolled API router (#50) removed
 * `registerApiControllers()` along with it, because it sat between two methods
 * that were being deleted. PHPUnit stayed green — nothing here calls
 * `register()` — and the breakage only showed up as 45 failing e2e tests across
 * ten of eleven specs, which reads like an environment problem rather than one
 * missing method.
 *
 * Reading the source rather than calling `register()` is deliberate: registering
 * for real needs a booted application, and this needs to run without one.
 */

namespace APP\plugins\generic\codecheck\tests;

use PKP\tests\PKPTestCase;

class CodecheckPluginHooksUnitTest extends PKPTestCase
{
    public function testEveryRegisteredHookNamesAMethodThatExists()
    {
        $source = file_get_contents(dirname(__DIR__) . '/CodecheckPlugin.php');

        preg_match_all('/function ([a-zA-Z_]\w*)\s*\(/', $source, $found);
        $methods = array_flip($found[1]);

        preg_match_all("/Hook::add\(\s*'([^']+)'\s*,\s*(.+?)\);/s", $source, $hooks, PREG_SET_ORDER);
        $this->assertNotEmpty($hooks, 'no Hook::add calls found — has the file moved?');

        foreach ($hooks as [$whole, $hookName, $callback]) {
            $callback = trim($callback);
            $method = null;

            // [$this, 'name'] or $this->name(...)
            if (preg_match("/\[\\\$this,\s*'(\w+)'\]/", $callback, $m)) {
                $method = $m[1];
            } elseif (preg_match('/\$this->(\w+)\(\.\.\.\)/', $callback, $m)) {
                $method = $m[1];
            }

            if ($method === null) {
                // A closure or a call on another object; nothing to resolve.
                continue;
            }

            $this->assertArrayHasKey(
                $method,
                $methods,
                "{$hookName} is registered against {$method}(), which does not exist"
            );
        }
    }

    /** A PHP file's code with every comment taken out, so a comment can neither satisfy nor break a check. */
    private static function codeOf(string $file): string
    {
        $code = '';
        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        return $code;
    }

    /**
     * Hooks that must run for every journal are added before the enabled
     * check in `register()`. On the command line, where the scheduled task
     * publishes, that check asks the site rather than the article's journal
     * (#188). Each callback checks the article's own journal itself.
     */
    public function testHooksForEveryJournalAreAddedBeforeTheEnabledCheck()
    {
        $source = self::codeOf(dirname(__DIR__) . '/CodecheckPlugin.php');
        $this->assertSame(1, preg_match('/if\s*\(\s*\$success\s*&&\s*\$this->getEnabled\(\)\s*\)/', $source, $m, PREG_OFFSET_CAPTURE), 'the enabled check in register() was not found — has it changed?');
        $enabledBlock = $m[0][1];

        foreach ([
            'Publication::publish' => '\$this->depositToRegister\(\.\.\.\)',
            'Publication::publish::before' => '',
            'articlecrossrefxmlfilter::execute' => '',
            'datacitexmlfilter::execute' => '',
            'Context::add' => '',
        ] as $hook => $callback) {
            $pattern = "/Hook::add\\(\\s*'" . preg_quote($hook, '/') . "'\\s*,\\s*" . $callback . '/';
            $this->assertSame(1, preg_match_all($pattern, $source, $all, PREG_OFFSET_CAPTURE), "{$hook} is not registered exactly once");
            $this->assertLessThan($enabledBlock, $all[0][0][1], "{$hook} is inside the enabled check");
        }
    }

    /**
     * The email to an editor assigned to a submission without a codechecker
     * (#31) listens to OJS's stage assignments for every journal: OJS assigns
     * editors on submission, possibly in a queued job.
     */
    public function testTheCodecheckerNeededEmailListensBeforeTheEnabledCheck()
    {
        $source = self::codeOf(dirname(__DIR__) . '/CodecheckPlugin.php');
        $this->assertSame(1, preg_match('/if\s*\(\s*\$success\s*&&\s*\$this->getEnabled\(\)\s*\)/', $source, $m, PREG_OFFSET_CAPTURE));
        $this->assertSame(1, preg_match_all('/StageAssignment::created\(/', $source, $all, PREG_OFFSET_CAPTURE), 'the stage assignment listener is not registered exactly once');
        $this->assertLessThan($m[0][1], $all[0][0][1], 'the stage assignment listener is inside the enabled check');
    }

    /**
     * The defect #188 was: the deposit took its journal from the request, which
     * the scheduled task's command line does not have. Neither the hook nor the
     * service may ask the request for a journal again.
     */
    public function testTheRegisterDepositNeverAsksTheRequestForItsJournal()
    {
        $plugin = self::codeOf(dirname(__DIR__) . '/CodecheckPlugin.php');
        $this->assertSame(1, preg_match('/function depositToRegister\(.*?\n    \}\n/s', $plugin, $hook), 'depositToRegister() was not found');
        $this->assertStringNotContainsString('getRequest()', $hook[0]);

        $service = self::codeOf(dirname(__DIR__) . '/classes/Workflow/CodecheckRegisterDepositService.php');
        $this->assertStringNotContainsString('getContext()', $service);
    }

    /**
     * The scheduled refresh of the CODECHECK lists (#65) is registered by OJS
     * calling `registerSchedules()` on every *loaded* plugin, and on the
     * command line OJS loads a lazy-load plugin only where it is enabled for
     * the whole site, which a per-journal install is not. The plugin declares
     * no `lazy-load`, so it is always loaded and the task always registered;
     * declaring it would stop the refresh without a sign.
     */
    public function testTheScheduledRefreshIsRegisteredOnEveryInstall()
    {
        $this->assertContains(
            \PKP\plugins\interfaces\HasTaskScheduler::class,
            class_implements(\APP\plugins\generic\codecheck\CodecheckPlugin::class)
        );

        $version = simplexml_load_file(dirname(__DIR__) . '/version.xml');
        $this->assertNotSame('1', trim((string) $version->{'lazy-load'}));
    }
}
