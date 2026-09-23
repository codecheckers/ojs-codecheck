<?php

/**
 * @file tests/ApiUnitTests/CodecheckApiControllerRoutesUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckApiControllerRoutesUnitTest
 *
 * @brief What the CODECHECK API does and does not expose.
 *
 * The routes themselves are registered through Laravel's Route facade, which
 * needs a booted application, so they cannot be enumerated here. What can be
 * checked is that the handler methods behind the removed endpoints have not
 * come back — which is how they would return.
 */

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\api\v1\CodecheckApiController;
use PKP\tests\PKPTestCase;

class CodecheckApiControllerRoutesUnitTest extends PKPTestCase
{
    /**
     * `GET download` and `POST upload` were removed for #50.
     *
     * Nothing ever called them: the manifest records bare filenames that
     * `handleFileUpload()` reads from the browser's file picker without
     * uploading anything, and the path `upload` returned was read by no one.
     *
     * They are asserted absent rather than merely deleted because of what they
     * did. `download` resolved a user-supplied path against the OJS web root and
     * passed it to `readfile()` if the string contained "codecheck" anywhere,
     * which served `config.inc.php` to any account holding a read role. `upload`
     * wrote an attacker-named file, extension included, under the document root.
     *
     * If manifest files ever need storing, use OJS's own file services and
     * `SubmissionFileAccessPolicy`, not a path built from `Core::getBaseDir()`.
     */
    public function testTheFileEndpointsAreNotServed()
    {
        $this->assertFalse(
            method_exists(CodecheckApiController::class, 'downloadFile'),
            'downloadFile() is back'
        );
        $this->assertFalse(
            method_exists(CodecheckApiController::class, 'uploadFile'),
            'uploadFile() is back'
        );

        $source = file_get_contents(
            dirname(__DIR__, 2) . '/api/v1/CodecheckApiController.php'
        );

        $this->assertStringNotContainsString("Route::get('download'", $source);
        $this->assertStringNotContainsString("Route::post('upload'", $source);
    }

    /**
     * Every route names a handler that exists, and carries a role tier.
     *
     * The routes are registered through Laravel's `Route` facade inside
     * `getGroupRoutes()`, which needs a booted application, so they cannot be
     * enumerated by calling it. They are read out of the source instead —
     * unusual, but the alternative is no coverage at all, which is what the
     * move to a PKP controller left behind: the endpoint table it replaced had
     * eight tests of its own.
     *
     * A handler named with `$this->method(...)` only fails when that line runs,
     * which in production is the first API request the process serves.
     */
    public function testEveryRouteNamesAHandlerAndARoleTier()
    {
        $routes = self::declaredRoutes();

        $this->assertNotEmpty($routes, 'no routes were found in the controller source');

        foreach ($routes as [$verb, $path, $handler, $tier]) {
            $this->assertTrue(
                method_exists(CodecheckApiController::class, $handler),
                strtoupper($verb) . " {$path} names {$handler}(), which does not exist"
            );

            $this->assertContains(
                $tier,
                ['read', 'write', 'editor', 'admin'],
                strtoupper($verb) . " {$path} carries an unknown role tier: {$tier}"
            );
        }
    }

    /**
     * Which tier each route sits in, pinned.
     *
     * This is the rule from issue #173 written down: the register endpoints are
     * for editors and administrators alone — not a reviewer, and not the
     * codechecker — while the record and its status are open to a reviewer,
     * whom OJS's own submission policy then holds to the submission they were
     * assigned to.
     *
     * Moving a route between tiers is a permission change, and should have to be
     * made here on purpose.
     */
    public function testTheRegisterEndpointsStayWithEditorsAndAdministrators()
    {
        $tiers = [];
        foreach (self::declaredRoutes() as [$verb, $path, $handler, $tier]) {
            $tiers[strtoupper($verb) . ' ' . $path] = $tier;
        }

        // Anything that writes to the public CODECHECK register.
        $this->assertSame('admin', $tiers['POST identifier'] ?? null);
        $this->assertSame('admin', $tiers['POST issue'] ?? null);
        $this->assertSame('admin', $tiers['GET orcid-test'] ?? null);

        // The record, its status and the ORCID deposit: a reviewer may reach
        // these, scoped to their own submission by the policy.
        $this->assertSame('write', $tiers['POST metadata'] ?? null);
        $this->assertSame('write', $tiers['POST status/update'] ?? null);
        $this->assertSame('write', $tiers['POST repository'] ?? null);
        $this->assertSame('write', $tiers['POST orcid-deposit'] ?? null);

        // Reads.
        $this->assertSame('read', $tiers['GET metadata'] ?? null);
        $this->assertSame('read', $tiers['GET status'] ?? null);
        $this->assertSame('read', $tiers['GET yaml'] ?? null);
    }

    /**
     * The routes as the controller source declares them.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function declaredRoutes(): array
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/api/v1/CodecheckApiController.php'
        );

        $pattern = '#Route::(get|post)\(\s*.([a-zA-Z/-]+).\s*,\s*\$this->(\w+)\(\.\.\.\)'
            . '[^;]*?->middleware\(\$(\w+)\)#s';

        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [$m[1], $m[2], $m[3], $m[4]], $matches);
    }
}
