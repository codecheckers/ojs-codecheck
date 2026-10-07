<?php

/**
 * @file tests/CodecheckersUnitTests/CodecheckerNeededNoticeUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerNeededNoticeUnitTest
 *
 * @brief When an editor assigned to a submission is told it needs a
 *   codechecker (#31). Sending the email needs a booted application.
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckersUnitTests;

use APP\plugins\generic\codecheck\classes\Codecheckers\CodecheckerNeededNotice;
use APP\plugins\generic\codecheck\classes\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class CodecheckerNeededNoticeUnitTest extends PKPTestCase
{
    public static function statusProvider(): array
    {
        return [
            'pending' => [Constants::CODECHECK_STATUS_PENDING, true],
            'needs codechecker' => [Constants::CODECHECK_STATUS_NEEDS_CODECHECKER, true],
            'codechecker assigned' => [Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER, false],
            'stalled' => [Constants::CODECHECK_STATUS_STALLED_CODECHECKER, false],
            'completed' => [Constants::CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION, false],
            'unknown' => ['something else', false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testOnlyACheckNotYetUnderWayNeedsACodechecker(string $status, bool $expected): void
    {
        $this->assertSame($expected, CodecheckerNeededNotice::needsCodechecker($status, []));
    }

    public function testNoStatusRecordedIsNotYetUnderWay(): void
    {
        $this->assertTrue(CodecheckerNeededNotice::needsCodechecker(null, []));
    }

    public function testAnyRecordedCodecheckerIsEnough(): void
    {
        $unlinked = ['name' => 'Ann Example', 'orcid' => '', 'github' => ''];
        $this->assertFalse(CodecheckerNeededNotice::needsCodechecker(Constants::CODECHECK_STATUS_NEEDS_CODECHECKER, [$unlinked]));
        $this->assertFalse(CodecheckerNeededNotice::needsCodechecker(null, [['userId' => 7] + $unlinked]));
    }
}
