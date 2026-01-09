<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;
use APP\plugins\generic\codecheck\classes\Exceptions\NoMatchingIssuesFoundException;
use APP\plugins\generic\codecheck\classes\DataStructures\UniqueArray;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CertificateIdentifier;

class CertificateIdentifierList
{
    private UniqueArray $uniqueArray;

    /**
     * This initializes a new Certificate Identifier List with a new `UniqueArray`
     * 
     * @return void
     */
    function __construct()
    {
        $this->uniqueArray = new UniqueArray();   
    }

    /**
     * Factory Method to create a new CertificateIdentifierList from a GitHub API fetch
     * 
     * @param CodecheckGithubRegisterApiClient $codecheckGithubRegisterApiClient The APIParser for the GitHub Issues
     * @return CertificateIdentifierList Returns a new List containing all fetched Certificate Identifiers from GitHub
     */
    static function fromApi(
        CodecheckGithubRegisterApiClient $codecheckGithubRegisterApiClient
    ): CertificateIdentifierList {
        $newCertificateIdentifierList = new CertificateIdentifierList();

        // fetch API
        try {
            $codecheckGithubRegisterApiClient->fetchIssues();
        } catch (ApiFetchException $ae) {
            throw $ae;
            error_log($ae);
            return $newCertificateIdentifierList;
        } catch (NoMatchingIssuesFoundException $me) {
            throw $me;
            error_log($me);
            return $newCertificateIdentifierList;
        }

        foreach ($codecheckGithubRegisterApiClient->getIssues() as $issue) {
            // raw identifier (can still have ranges of identifiers);
            $rawIdentifier = CertificateIdentifierList::getRawIdentifier($issue['title']);
            
            // check if the identifier is empty (either empty string or null) and not set
            // -> if so skip this identifier and move onto the next issue
            if(empty($rawIdentifier)) {
                continue;
            }

            // append to all identifiers in new Register
            $newCertificateIdentifierList->appendToCertificateIdList($rawIdentifier);
        }

        // return the new Register
        return $newCertificateIdentifierList;
    }

    /**
     * Get the Certificate Identifier from the GitHub Issue Title
     * 
     * @param string $title The Title of the GitHub Issue
     * @return ?string Either it returns a new Certificate identifier raw string (if the title matches the required form), or it returns null otherwise
     */
    public static function getRawIdentifier(string $title): ?string
    {
        // convert whole title to lowercase
        $title = strtolower($title);

        $rawIdentifier = null;

        if (strpos($title, '|') !== false) {
            // split the title into sub-strings at separator letter: '|'
            // store those sub-strings in the $matches array
            preg_match('/[^|]+$/', $title, $matches);

            // $matches[0] is the last sub-string so here the Certificate Identifier
            // when no '|' would exist then it would be the whole string -> but this case is excluded because of the if statement in line 17
            $rawIdentifier = preg_replace('/[\s]+/', '', $matches[0] ?? '');
        
            // Check if the $rawIdentifier has the form 'year-number' or 'year-number/year-number'
            // If it has another form like 'year-number - year-number' it will be set back to null
            if (!preg_match('/^\d{4}-\d+(?:\/\d{4}-\d+)?$/', $rawIdentifier)) {
                $rawIdentifier = null;
            }
        }

        return $rawIdentifier;
    }

    /**
     * Appends a raw Identifier to the list of Certificate Identifiers
     * 
     * @param string $rawidentifier The raw Identifier to be appended
     * @return void
     */
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

    /**
     * Sorts the Certificate Identifier List ascending
     */
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

    /**
     * Sorts the Certificate Identifier List descending
     */
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

    /**
     * Returns the count of all Certificate Identifiers that are inside the Certificate Identifier List
     * 
     * @return int The count of all Certificate Identifiers
     */
    public function getNumberOfIdentifiers(): int
    {
        return $this->uniqueArray->count();
    }

    /**
     * Get the latest/ newest Certificate Identifier
     * 
     * @return CertificateIdentifier Returns the newest Certificate Identifier
     */
    public function getNewestIdentifier(): CertificateIdentifier
    {
        $this->sortDesc();
        // get first element of sort descending -> newest element
        return $this->uniqueArray->at(0);
    }

    /**
     * Converts the Certificate Identifier List to a string that is good for print debugging
     * 
     * @return string The List of the Certificate Identifiers as a string
     */
    public function toStr(): string
    {
        $return_str = "Certificate Identifiers:\n";
        foreach ($this->uniqueArray as $identifier) {
            $return_str .= $identifier->toStr() . "\n";
        }
        return $return_str;
    }
}