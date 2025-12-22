<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

class CodecheckVenue
{
    private $venueName;
    private $venueType;

    /**
     * The Venue of the Codecheck
     * 
     * @param string $venueType The new Venue Type to be set
     * @param string $venueName The new Venue Name to be set
     */
    public function __construct(string $venueType, string $venueName)
    {
        $this->setVenueType($venueType);
        $this->setVenueName($venueName);
    }

    /**
     * Sets the name of the CODECHECK Venue
     * 
     * @param string $venueName The new Venue Name to be set
     */
    public function setVenueName(string $venueName)
    {
        $this->venueName = str_replace(["\r", "\n"], "", $venueName);
    }

    /**
     * Sets the type of the CODECHECK Venue
     * 
     * @param string $venueType The new Venue Type to be set
     */
    public function setVenueType(string $venueType)
    {
        $this->venueType = str_replace(["\r", "\n"], "", $venueType);
    }

    /**
     * Gets the name of the CODECHECK Venue
     * 
     * @return string Name of the CODECHECK Venue
     */
    public function getVenueName(): string
    {
        return $this->venueName;
    }

    /**
     * Gets the type of the CODECHECK Venue
     * 
     * @return string Type of the CODECHECK Venue
     */
    public function getVenueType(): string
    {
        return $this->venueType;
    }
}