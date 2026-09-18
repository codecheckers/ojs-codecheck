<?php

/**
 * @file tests/SubmissionUnitTests/CodecheckSubmissionAccessUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #173 — who may write CODECHECK data for one submission.
 *
 * The assigned-reviewer path reads `review_assignments` and is covered by the
 * e2e suite. What is pinned here is the part that decides without the database:
 * which roles count as editorial, and that nothing is granted to nobody.
 */

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\security\Role;
use PKP\tests\PKPTestCase;
use PKP\user\User;

class CodecheckSubmissionAccessUnitTest extends PKPTestCase
{
    private function userWithRoles(array $roles): User
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturnCallback(
            fn (array $asked, $contextId) => (bool) array_intersect($asked, $roles)
        );

        return $user;
    }

    #[DataProvider('editorialRoleProvider')]
    public function testEditorialRolesAreEditors(int $role)
    {
        $this->assertTrue(CodecheckSubmissionAccess::isEditor($this->userWithRoles([$role]), 1));
    }

    public static function editorialRoleProvider(): array
    {
        return [
            'journal manager' => [Role::ROLE_ID_MANAGER],
            'site admin'      => [Role::ROLE_ID_SITE_ADMIN],
            'section editor'  => [Role::ROLE_ID_SUB_EDITOR],
            'assistant'       => [Role::ROLE_ID_ASSISTANT],
        ];
    }

    /**
     * A reviewer is not an editor. Register entries publish under the journal's
     * name, so they stay with the editors — not with the codechecker either.
     */
    #[DataProvider('nonEditorialRoleProvider')]
    public function testEveryoneElseIsNotAnEditor(int $role)
    {
        $this->assertFalse(CodecheckSubmissionAccess::isEditor($this->userWithRoles([$role]), 1));
    }

    public static function nonEditorialRoleProvider(): array
    {
        return [
            'reviewer' => [Role::ROLE_ID_REVIEWER],
            'author'   => [Role::ROLE_ID_AUTHOR],
            'reader'   => [Role::ROLE_ID_READER],
        ];
    }

    public function testAnEditorMayWriteAnySubmissionInTheirJournal()
    {
        $editor = $this->userWithRoles([Role::ROLE_ID_SUB_EDITOR]);

        $this->assertTrue(CodecheckSubmissionAccess::canWriteMetadata($editor, 42, 1));
    }

    /** No user, no access — and no database query to find that out. */
    public function testNobodyIsNotAnEditorAndMayNotWrite()
    {
        $this->assertFalse(CodecheckSubmissionAccess::isEditor(null, 1));
        $this->assertFalse(CodecheckSubmissionAccess::canWriteMetadata(null, 42, 1));
        $this->assertFalse(CodecheckSubmissionAccess::isAssignedReviewer(null, 42));
    }

    /** A submission id that cannot exist is refused before any lookup. */
    public function testAnImpossibleSubmissionIdIsNotAnAssignment()
    {
        $reviewer = $this->userWithRoles([Role::ROLE_ID_REVIEWER]);

        $this->assertFalse(CodecheckSubmissionAccess::isAssignedReviewer($reviewer, 0));
        $this->assertFalse(CodecheckSubmissionAccess::isAssignedReviewer($reviewer, -1));
    }
}
