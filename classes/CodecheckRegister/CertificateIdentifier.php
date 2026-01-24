<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifierList;

class CertificateIdentifier
{
    private $year;
    private $number;

    /**
     * This initializes a Certificate Identifier with year and running number
     * 
     * @param int $year The Year of the Certificate Identifier
     * @param int $number The running number of the Certificate Identifier
     * @return void
     */
    function __construct(int $year, int $number)
    {
        $this->year = $year;
        $this->number = $number;
    }

    /**
     * This sets the year of the Certificate Identifier
     * 
     * @param int $year The new year of the Certificate Identifier
     * @return void
     */
    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    /**
     * This sets the running Number of the Certificate Identifier
     * 
     * @param int $number The new running number of the Certificate Identifier
     * @return void
     */
    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    /**
     * This gets the current year of the Certificate Identifier
     * 
     * @return int Returns the year of the Certificate Identifier
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * This gets the current running number of the Certificate Identifier
     * 
     * @return int Returns the running number of the Certificate Identifier
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * Factory Method for Certificate Identifier from an identifier string
     * 
     * @param string $identifier_str The raw Identifier as a type string
     * @return CertificateIdentifier Returns a new Certificate Identifier
     */
    static function fromStr(string $identifier_str): CertificateIdentifier
    {
        // split Identifier String at '-'
        list($year, $number) = explode('-', $identifier_str);
        // create new instance of $certificateIdentifier (cast to int from str)
        $certificateIdentifier = new CertificateIdentifier((int) $year, (int) $number);
        // return new instance of $certificateIdentifier
        return $certificateIdentifier;
    }

    /**
     * Factory Method for a new unique Identifier
     * 
     * @param CertificateIdentifierList $certificateIdentifierList The list of all Certificate Identifiers
     * @return CertificateIdentifier A new unique Certificate Identifier
     */
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

    /**
     * This function converts a Certificate Identifier to a string
     * 
     * @return string The Certificate Identifer as a String in the Form: `year-number`
     */
    public function toStr(): string
    {
        // pad with leading zeros (3 digits) in case number doesn't have 3 digits already
        return $this->year . '-' . str_pad($this->number, 3, '0', STR_PAD_LEFT);;
    }
}