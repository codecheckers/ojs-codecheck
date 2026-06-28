<?php
namespace APP\plugins\generic\codecheck\classes\DataStructures;

class UniqueIdentifierArray extends UniqueArray
{
    /**
     * This function checks if a specific Certificate Identifier exists inside the `UniqueArray`
     * 
     * @return bool Returns `true` if the identifier exists inside the `UniqueArray` and `false` if otherwise
     */
    public function contains(mixed $element): bool
    {
        foreach ($this->array as $uniqueArrayElement) {
            if($element["identifier"]->toStr() == $uniqueArrayElement['identifier']->toStr()){
                return true;
            }
        }
        return false;
    }
}