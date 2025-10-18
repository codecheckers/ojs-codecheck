<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

class CodecheckVenue
{
    private $venueName;
    private $venueType;

    public function setVenueName(string $venueName)
    {
        $this->venueName = str_replace(["\r", "\n"], "", $venueName);
    }

    public function setVenueType(string $venueType)
    {
        $this->venueType = str_replace(["\r", "\n"], "", $venueType);
    }

    public function getVenueName(): string
    {
        return $this->venueName;
    }

    public function getVenueType(): string
    {
        return $this->venueType;
    }
}