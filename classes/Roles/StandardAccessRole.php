<?php

namespace APP\plugins\generic\codecheck\classes\Roles;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;

class StandardAccessRole extends CodecheckRole
{
    private UniqueArray $roles;

    public function __construct(array $roles)
    {
        $this->roles = UniqueArray::from($roles);
    }

    /**
     * Get the PKP roles
     * 
     * @return array The array containing all PKP roles of this `CodecheckRole`
     */
    public function getRoles(): array
    {
        return $this->roles->toArray();
    }
}