<?php

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleManager;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/ApiUnitTests/CodecheckRoleArrayUnitTest.php
 *
 * @class CodecheckRoleArrayUnitTest
 *
 * @brief Tests for the CODECHECK API role sets
 *
 * CodecheckApiHandler builds its role sets by nesting: edit includes admin,
 * read includes edit. Every API request is authorised against the flattened
 * result, so a mistake here either locks out legitimate users or widens access
 * — worth pinning down separately from the handler.
 */
class CodecheckRoleArrayUnitTest extends PKPTestCase
{
    public function testFlattensAPlainListOfRoles()
    {
        $roles = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);

        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            $roles->getRoles()
        );
    }

    public function testAbsorbsTheRolesOfANestedRoleArray()
    {
        $admin = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
        $edit = new CodecheckRoleArray([$admin, Role::ROLE_ID_SUB_EDITOR]);

        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            $edit->getRoles()
        );
    }

    public function testNestsToArbitraryDepth()
    {
        $admin = new CodecheckRoleArray([Role::ROLE_ID_SITE_ADMIN]);
        $edit = new CodecheckRoleArray([$admin, Role::ROLE_ID_SUB_EDITOR]);
        $read = new CodecheckRoleArray([$edit, Role::ROLE_ID_READER]);

        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_READER],
            $read->getRoles()
        );
    }

    /**
     * The handler's own sets overlap: admin appears in edit, which appears in
     * read, and MANAGER is listed twice in edit. Duplicates must collapse.
     */
    public function testDropsDuplicatesIntroducedByNesting()
    {
        $admin = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
        $edit = new CodecheckRoleArray([$admin, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER]);

        $flattened = $edit->getRoles();

        $this->assertSame(count($flattened), count(array_unique($flattened)));
        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            $flattened
        );
    }

    public function testFlattensAPlainNestedArray()
    {
        $roles = new CodecheckRoleArray([[Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR]]);

        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR],
            $roles->getRoles()
        );
    }

    public function testAnEmptyRoleSetGrantsNothing()
    {
        $this->assertSame([], (new CodecheckRoleArray([]))->getRoles());
    }

    /**
     * Reproduces the arrangement CodecheckApiHandler::setupAPIHandler() builds,
     * so a change to that nesting shows up as a change in who can read.
     */
    public function testTheHandlersOwnNestingWidensFromAdminToRead()
    {
        $admin = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
        $edit = new CodecheckRoleArray([$admin, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_MANAGER]);
        $read = new CodecheckRoleArray([$edit, Role::ROLE_ID_READER, Role::ROLE_ID_AUTHOR]);

        $manager = new CodecheckRoleManager(readMetadata: $read, editMetadata: $edit, admin: $admin);

        $this->assertCount(2, $manager->admin()->getRoles());
        $this->assertCount(4, $manager->editMetadata()->getRoles());
        $this->assertCount(6, $manager->readMetadata()->getRoles());

        // Every editor can read, and every admin can edit.
        foreach ($manager->editMetadata()->getRoles() as $role) {
            $this->assertContains($role, $manager->readMetadata()->getRoles());
        }
        foreach ($manager->admin()->getRoles() as $role) {
            $this->assertContains($role, $manager->editMetadata()->getRoles());
        }

        // A reviewer is not granted access by any of them.
        $this->assertNotContains(Role::ROLE_ID_REVIEWER, $manager->readMetadata()->getRoles());
    }
}
