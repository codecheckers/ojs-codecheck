<?php
namespace APP\plugins\generic\codecheck\classes\DataStructures;

class UniqueArray
{
    private $array = [];

    /**
     * Factory method that creates a new UniqueArray from a normal array
     * 
     * @param array $arr The Array from which a new `UniqueArray` should be created from
     * @return UniqueArray The newly created unique array
     */
    public static function from(array $arr): UniqueArray
    {
        $uniqueArray = new UniqueArray();
        foreach ($arr as $element) {
            $uniqueArray->add($element);
        }

        return $uniqueArray;
    }

    /**
     * This function adds a new element to the `UniqueArray`
     * 
     * @param mixed $element The new Element that is being added to the `UniqueArray` (of any Type)
     */
    public function add($element): void
    {
        if(!$this->contains($element)) {
            $this->array[] = $element;
        }
        $this->array = array_values(array_unique($this->array, SORT_REGULAR));
    }

    /**
     * This function removes the element at a specific index from the `UniqueArray`
     * 
     * @param int $index The index at which the desired Element should be removed at
     */
    public function remove(int $index): void
    {
        unset($this->array[$index]);
        $this->array = array_values($this->array);
    }

    /**
     * This function gets an element inside the `UniqueArray` at a sepecific index
     * 
     * @return mixed This function either returns an element of any Type at the index from the `UniqueArray` or `null` if no element was found at that index
     */
    public function at(int $index): mixed
    {
        return $this->array[$index] ?? null;
    }

    /**
     * This function checks if a specific Element exists inside the `UniqueArray`
     * 
     * @return bool Returns `true` if the element exists inside the `UniqueArray` and `false` if otherwise
     */
    public function contains($searchElement): bool
    {
        foreach ($this->array as $arrayElement) {
            if($arrayElement == $searchElement) {
                return true;
            }
        }

        return false;
    }

    /**
     * This function converts the `UniqueArray` to a normal array and returns this
     * 
     * @return array Returns the normal array to which the `UniqueArray` was converted
     */
    public function toArray(): array
    {
        return $this->array;
    }

    /**
     * Sorts the `UniqueArray` using a user-defined comparison function.
     *
     * @param callable $comparator A comparison closure: fn($a, $b): int
     * @return void
     */
    public function sort(callable $comparator): void
    {
        usort($this->array, $comparator);
    }

    /**
     * This function gets the count of elements inside the `UniqueArray`
     * 
     * @return int Returns the count of elements inside the `UniqueArray`
     */
    public function count(): int
    {
        return count($this->array);
    }
}