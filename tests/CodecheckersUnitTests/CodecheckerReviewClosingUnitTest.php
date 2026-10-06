<?php

/**
 * @file tests/CodecheckersUnitTests/CodecheckerReviewClosingUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerReviewClosingUnitTest
 *
 * @brief When a codechecker's review can be closed, and the comment closing it
 *   leaves (#13). Closing itself writes to the database and is covered by e2e.
 */

namespace APP\plugins\generic\codecheck\tests\CodecheckersUnitTests;

use APP\plugins\generic\codecheck\classes\Codecheckers\CodecheckerReviewClosing;
use APP\plugins\generic\codecheck\classes\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class CodecheckerReviewClosingUnitTest extends PKPTestCase
{
    public static function statusProvider(): array
    {
        return [
            'pending' => [Constants::CODECHECK_STATUS_PENDING, false],
            'needs codechecker' => [Constants::CODECHECK_STATUS_NEEDS_CODECHECKER, false],
            'codechecker assigned' => [Constants::CODECHECK_STATUS_ASSIGNED_CODECHECKER, false],
            'stalled' => [Constants::CODECHECK_STATUS_STALLED_CODECHECKER, false],
            'completed, unsuccessful' => [Constants::CODECHECK_STATUS_COMPLETED_UNSUCCESSFUL, true],
            'completed' => [Constants::CODECHECK_STATUS_COMPLETED_FULL_REPRODUCTION, true],
            'published certificate' => [Constants::CODECHECK_STATUS_PUBLISHED_PARTIAL_REPRODUCTION, true],
            'unknown' => ['something else', false],
            'none' => [null, false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testAReviewCanBeClosedFromCompletedOn(?string $status, bool $expected): void
    {
        $this->assertSame($expected, CodecheckerReviewClosing::isClosableStatus($status));
    }

    /** The test translator answers keys, so what is pinned is which paragraphs there are. */
    public function testTheCommentNamesWhatTheRecordHas(): void
    {
        $this->assertSame(
            '<p>plugins.generic.codecheck.closeReview.comment.done</p>'
            . '<p>plugins.generic.codecheck.closeReview.comment.certificate</p>'
            . '<p>plugins.generic.codecheck.closeReview.comment.register</p>',
            CodecheckerReviewClosing::commentText('2026-001', 'https://doi.org/10.5281/zenodo.1', 'https://github.com/codecheckers/register/issues/1')
        );
        $this->assertSame(
            '<p>plugins.generic.codecheck.closeReview.comment.done</p>',
            CodecheckerReviewClosing::commentText(null, null, null)
        );
    }

    /** A register entry that is not a web address is left out rather than linked. */
    public function testARegisterEntryThatIsNotAWebAddressIsLeftOut(): void
    {
        $this->assertStringNotContainsString(
            'comment.register',
            CodecheckerReviewClosing::commentText(null, null, 'javascript:alert(1)')
        );
    }
}
