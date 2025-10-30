<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\UniqueArray;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CodecheckRegisterGithubIssuesApiParser;
use APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers\CertificateIdentifier;

// get the certificate ID from the issue description
function getRawIdentifier(string $title): string
{
    $title = strtolower($title); // convert whole title to lowercase

    //$title = "Arabsheibani, Winter, Tomko | 2025-026/2025-029";

    if (strpos($title, '|') !== false) {
        // find the last "|"
        $seperator = strrpos($title, '|');
        // move one position forwards (so we get character after '|')
        $seperator++;

        // Find where the next line break occurs after "certificate"
        $rawIdentifier = substr($title, $seperator);
        // remove white spaces
        $rawIdentifier = preg_replace('/[\s]+/', '', $rawIdentifier);
    }

    return $rawIdentifier;
}

class CertificateIdentifierList
{
    private UniqueArray $uniqueArray;

    function __construct()
    {
        $this->uniqueArray = new UniqueArray();   
    }

    // Factory Method to create a new CertificateIdentifierList from a GitHub API fetch
    static function fromApi(
        CodecheckRegisterGithubIssuesApiParser $apiParser
    ): CertificateIdentifierList {
        $newCertificateIdentifierList = new CertificateIdentifierList();

        // fetch API
        $apiParser->fetchIssues();

        foreach ($apiParser->getIssues() as $issue) {
            // raw identifier (can still have ranges of identifiers);
            $rawIdentifier = getRawIdentifier($issue['title']);
            
            // append to all identifiers in new Register
            $newCertificateIdentifierList->appendToCertificateIdList($rawIdentifier);
        }

        // return the new Register
        return $newCertificateIdentifierList;
    }

    public function appendToCertificateIdList(string $rawIdentifier): void
    {
        // list of certificate identifiers in range
        $idRange = [];

        // if it is a range
        if(strpos($rawIdentifier, '/')) {
            // split into "fromIdStr" and "toIdStr"
            list($fromIdStr, $toIdStr) = explode('/', $rawIdentifier);

            $from_identifier = CertificateIdentifier::fromStr($fromIdStr);
            $to_identifier = CertificateIdentifier::fromStr($toIdStr);

            // append to $idRange list
            for ($id_count = $from_identifier->getNumber(); $id_count <= $to_identifier->getNumber(); $id_count++) {
                $new_identifier = new CertificateIdentifier($from_identifier->getYear(), $id_count);
                // append new identifier
                $idRange[] = $new_identifier;
            }
        }
        // if it isn't a list then just append on identifier
        else {
            $new_identifier = CertificateIdentifier::fromStr($rawIdentifier);
            $idRange[] = $new_identifier;
        }

        // append to all certificate identifiers
        foreach ($idRange as $identifier) {
            if (!$this->uniqueArray->contains($identifier)) {
                $this->uniqueArray->add($identifier);
            }
        }
    }

    // sort ascending Certificate Identifiers
    public function sortAsc(): void
    {
        $this->uniqueArray->sort(function($a, $b) {
            // First, compare year
            if ($a->getYear() !== $b->getYear()) {
                return $a->getYear() <=> $b->getYear();
            }
            // If years are equal, compare ID
            return $a->getNumber() <=> $b->getNumber();
        });
    }

    public function sortDesc(): void
    {
        $this->uniqueArray->sort(function($a, $b) {
            // First, compare year descending
            if ($a->getYear() !== $b->getYear()) {
                return $b->getYear() <=> $a->getYear();
            }
            // If years are equal, compare ID descending
            return $b->getNumber() <=> $a->getNumber();
        });
    }

    public function getNumberOfIdentifiers(): int
    {
        return $this->uniqueArray->count();
    }

    // return the latest identifier
    public function getNewestIdentifier(): CertificateIdentifier
    {
        $this->sortDesc();
        // get first element of sort descending -> newest element
        return $this->uniqueArray->at(0);
    }

    public function toStr(): string
    {
        $return_str = "Certificate Identifiers:\n";
        foreach ($this->uniqueArray as $identifier) {
            $return_str .= $identifier->toStr() . "\n";
        }
        return $return_str;
    }
}