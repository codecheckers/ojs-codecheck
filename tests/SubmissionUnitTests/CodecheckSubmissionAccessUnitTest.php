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
 *
 * Issue #28 — who may know the authors of a submission. The rule is pinned
 * through authorsVisible(); the lookups feeding it read the database.
 */

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
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
            'site admin' => [Role::ROLE_ID_SITE_ADMIN],
            'section editor' => [Role::ROLE_ID_SUB_EDITOR],
            'assistant' => [Role::ROLE_ID_ASSISTANT],
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
            'author' => [Role::ROLE_ID_AUTHOR],
            'reader' => [Role::ROLE_ID_READER],
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

    #[DataProvider('authorVisibilityProvider')]
    public function testWhoMayKnowTheAuthors(bool $hasStanding, ?int $reviewMethod, bool $expected)
    {
        $this->assertSame($expected, CodecheckSubmissionAccess::authorsVisible($hasStanding, $reviewMethod));
    }

    /** OJS hides the authors from a reviewer in double-anonymous review only. */
    public static function authorVisibilityProvider(): array
    {
        $open = ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN;
        $anonymous = ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS;
        $double = ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;

        return [
            'an editor or author of the submission' => [true, null, true],
            'one who is also a double-anonymous reviewer' => [true, $double, true],
            'a reviewer on an open assignment' => [false, $open, true],
            'a reviewer on an anonymous-reviewer, disclosed-author assignment' => [false, $anonymous, true],
            'a reviewer on a double-anonymous assignment' => [false, $double, false],
            'someone with no standing and no assignment' => [false, null, false],
        ];
    }

    /** No user, or no submission, is refused before any lookup. */
    public function testNobodyMayKnowTheAuthors()
    {
        $this->assertFalse(CodecheckSubmissionAccess::mayKnowAuthors(null, 42, 1));
        $this->assertFalse(CodecheckSubmissionAccess::mayKnowAuthors(
            $this->userWithRoles([Role::ROLE_ID_MANAGER]),
            0,
            1
        ));
    }

    /** A manager is decided from the role alone, before any lookup. */
    public function testAManagerMayKnowTheAuthorsWithoutALookup()
    {
        $manager = $this->userWithRoles([Role::ROLE_ID_MANAGER]);

        $this->assertTrue(CodecheckSubmissionAccess::mayKnowAuthors($manager, 42, 1));
    }

    /**
     * A site administrator holds no journal role, so the lookup has to ask for
     * that group in the site context — as OJS's own `has.roles` does.
     */
    public function testASiteAdministratorWithoutAJournalRoleMayKnowTheAuthors()
    {
        $siteAdmin = $this->createMock(User::class);
        $siteAdmin->method('hasRole')->willReturnCallback(
            fn (array $asked, $contextId) => $contextId === null && in_array(Role::ROLE_ID_SITE_ADMIN, $asked, true)
        );

        $this->assertTrue(CodecheckSubmissionAccess::mayKnowAuthors($siteAdmin, 42, 1));
    }

    /** The identifier is a journal manager's or a site administrator's alone (#65). */
    public function testOnlyAManagerOrASiteAdministratorMayManageTheIdentifier()
    {
        $this->assertTrue(CodecheckSubmissionAccess::mayManageIdentifier($this->userWithRoles([Role::ROLE_ID_MANAGER]), 1));

        $siteAdmin = $this->createMock(User::class);
        $siteAdmin->method('hasRole')->willReturnCallback(
            fn (array $asked, $contextId) => $contextId === null && in_array(Role::ROLE_ID_SITE_ADMIN, $asked, true)
        );
        $this->assertTrue(CodecheckSubmissionAccess::mayManageIdentifier($siteAdmin, 1));

        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR] as $role) {
            $this->assertFalse(CodecheckSubmissionAccess::mayManageIdentifier($this->userWithRoles([$role]), 1));
        }
        $this->assertFalse(CodecheckSubmissionAccess::mayManageIdentifier(null, 1));
    }
}
