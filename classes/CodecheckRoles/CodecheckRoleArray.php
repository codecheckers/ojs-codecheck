<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRoles;

use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;

class CodecheckRoleArray
{
    private UniqueArray $roles;

    /**
     * This takes an array of PKP Roles and / or CodecheckRoleArrays -> so Roles can be defined dependant on each other
     * 
     * @param array $roles The array of the PKPRoles which are allowed to access this Codecheck Resource
     */
    public function __construct(array $roles)
    {
        $this->roles = new UniqueArray();
        $this->addRoles($roles);
    }

    private function addRoles(mixed $roles) {
        foreach ($roles as $role) {
            if($role instanceof CodecheckRoleArray) {
                $this->addRoles($role->getRoles());
            } elseif (is_array($role)) {
                $this->addRoles($role);
            } else {
                $this->roles->add($role);
            }
        }
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