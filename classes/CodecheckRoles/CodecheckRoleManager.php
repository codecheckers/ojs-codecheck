<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRoles;

use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;

class CodecheckRoleManager
{
    public function __construct(
        private readonly CodecheckRoleArray $readMetadata,
        private readonly CodecheckRoleArray $editMetadata,
        private readonly CodecheckRoleArray $admin,
    ) {}

    public function readMetadata(): CodecheckRoleArray  { return $this->readMetadata; }
    public function editMetadata(): CodecheckRoleArray { return $this->editMetadata; }
    public function admin(): CodecheckRoleArray { return $this->admin; }
}