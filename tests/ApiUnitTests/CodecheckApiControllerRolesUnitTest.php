<?php
/**
 * @file tests/ApiUnitTests/CodecheckApiControllerRolesUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckApiControllerRolesUnitTest
 * @brief The role sets the API controller installs.
 *
 * Replaces the half of CodecheckRoleArrayUnitTest that was about policy rather
 * than about the flattening mechanics of CodecheckRoleArray, which no longer
 * exists. The rules pinned here were decided in #173 and #50, and each has been
 * wrong at some point:
 *
 *   - a reviewer could write any submission in the journal (#173);
 *   - a self-registered reader could read the title and authors of an
 *     unpublished submission (#50).
 *
 * The per-submission half — that a reviewer reaches only the submission they
 * were assigned to — is PKP's SubmissionAccessPolicy and is covered by
 * reviewer-rights.cy.js against a running instance.
 */

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\api\v1\CodecheckApiController;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class CodecheckApiControllerRolesUnitTest extends PKPTestCase
{
    /** The controller's role lists are private; read them as the router would. */
    private function roles(string $name): array
    {
        // getValue() reads a private constant directly; there is no
        // setAccessible() on ReflectionClassConstant.
        return (new \ReflectionClassConstant(CodecheckApiController::class, $name))->getValue();
    }

    public function testNoRoleSetAdmitsAReader()
    {
        // ROLE_ID_READER is what a self-registration grants. The hand-rolled
        // handler admitted it to every read endpoint.
        foreach (['READ_ROLES', 'WRITE_ROLES', 'EDITOR_ROLES', 'ADMIN_ROLES'] as $set) {
            $this->assertNotContains(
                Role::ROLE_ID_READER,
                $this->roles($set),
                "{$set} admits a reader"
            );
        }
    }

    public function testAnAuthorMayReadButNotWrite()
    {
        $this->assertContains(Role::ROLE_ID_AUTHOR, $this->roles('READ_ROLES'));
        $this->assertNotContains(Role::ROLE_ID_AUTHOR, $this->roles('WRITE_ROLES'));
    }

    public function testAReviewerMayReadAndWrite()
    {
        // Writing is what makes someone the codechecker of a submission; which
        // submission is SubmissionAccessPolicy's business, not the role list's.
        $this->assertContains(Role::ROLE_ID_REVIEWER, $this->roles('READ_ROLES'));
        $this->assertContains(Role::ROLE_ID_REVIEWER, $this->roles('WRITE_ROLES'));
    }

    public function testTheRegisterIsForEditorsAndAdministratorsAlone()
    {
        // `identifier` and `issue` publish under the journal's name in the
        // public CODECHECK register (#173).
        $this->assertSame(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
            $this->roles('ADMIN_ROLES')
        );
    }

    public function testTheRoleSetsWidenFromAdminOutwards()
    {
        // Each set should contain the one above it: admin ⊂ editor ⊂ write ⊂ read.
        $this->assertEmpty(array_diff($this->roles('ADMIN_ROLES'), $this->roles('EDITOR_ROLES')));
        $this->assertEmpty(array_diff($this->roles('EDITOR_ROLES'), $this->roles('WRITE_ROLES')));
        $this->assertEmpty(array_diff($this->roles('WRITE_ROLES'), $this->roles('READ_ROLES')));
    }
}
