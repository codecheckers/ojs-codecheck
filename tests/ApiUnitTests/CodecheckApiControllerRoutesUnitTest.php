<?php
/**
 * @file tests/ApiUnitTests/CodecheckApiControllerRoutesUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckApiControllerRoutesUnitTest
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
}
