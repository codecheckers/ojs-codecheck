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
}
