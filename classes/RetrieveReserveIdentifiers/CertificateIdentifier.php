<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifierList;

class CertificateIdentifier
{
    private $year;
    private $number;

    function __construct(int $year, int $number)
    {
        $this->year = $year;
        $this->number = $number;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    // Factory Method for Certificate Identifier
    static function fromStr(string $identifier_str): CertificateIdentifier
    {
        // split Identifier String at '-'
        list($year, $number) = explode('-', $identifier_str);
        // create new instance of $certificateIdentifier (cast to int from str)
        $certificateIdentifier = new CertificateIdentifier((int) $year, (int) $number);
        // return new instance of $certificateIdentifier
        return $certificateIdentifier;
    }

    // Factory Method for new unique Identifier
    static function newUniqueIdentifier(CertificateIdentifierList $certificateIdentifierList): CertificateIdentifier
    {
        $latest_identifier = $certificateIdentifierList->getNewestIdentifier();
        $current_year = (int) date("Y");

        // different year, so this is the first CODECHECK certificate of the year -> id 001
        if($current_year != $latest_identifier->getYear()) {
            // configure new Identifier which is the first identifier of a new year
            $new_identifier = new CertificateIdentifier((int) $current_year, 1);
            return $new_identifier;
        }

        // get the latest id
        $latest_id = (int) $latest_identifier->getNumber();
        // increment the latest id by one to get a new unique one
        $latest_id++;
        // create new Identifier
        $new_identifier = new CertificateIdentifier($latest_identifier->getYear(), $latest_id);
        return $new_identifier;
    }

    public function toStr(): string
    {
        // pad with leading zeros (3 digits) in case number doesn't have 3 digits already
        return $this->year . '-' . str_pad($this->number, 3, '0', STR_PAD_LEFT);;
    }
}