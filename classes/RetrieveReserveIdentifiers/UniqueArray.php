<?php
namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

class UniqueArray
{
    private $array = [];

    public static function from(array $arr): UniqueArray
    {
        $uniqueArray = new UniqueArray();
        foreach ($arr as $element) {
            $uniqueArray->add($element);
        }

        return $uniqueArray;
    }

    public function add($element): void
    {
        if(!$this->contains($element)) {
            $this->array[] = $element;
        }
        $this->array = array_values(array_unique($this->array, SORT_REGULAR));
    }

    public function remove(int $index): void
    {
        unset($this->array[$index]);
        $this->array = array_values($this->array);
    }

    public function at(int $index)
    {
        return $this->array[$index] ?? null;;
    }

    public function contains($searchElement): bool
    {
        foreach ($this->array as $arrayElement) {
            if($arrayElement == $searchElement) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return $this->array;
    }

    /**
     * Sorts the unique array using a user-defined comparison function.
     *
     * @param callable $comparator A comparison closure: fn($a, $b): int
     * @return void
     */
    public function sort(callable $comparator): void
    {
        usort($this->array, $comparator);
    }

    public function count(): int
    {
        return count($this->array);
    }
}