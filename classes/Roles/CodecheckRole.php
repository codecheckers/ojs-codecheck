<?php

namespace APP\plugins\generic\codecheck\classes\Roles;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;

abstract class CodecheckRole
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
    abstract public function getRoles(): array;
}