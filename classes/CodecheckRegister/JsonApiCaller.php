<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;

class JsonApiCaller
{
    private $url;
    private $jsonData = [];

    /**
     * Initializes the Caller for the CODECHECK JSON Api
     * 
     * @param string $url The URL the Api Caller should fetch from
     */
    function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * This function fetches all the data from the given URL
     */
    public function fetch()
    {
        // Fetch JSON from API
        $response = file_get_contents($this->url);

        // throw error if no data was fetched from API
        if ($response === FALSE) {
            throw new ApiFetchException("Error fetching the Codecheck API data. URL: " . $this->url);
        }

        // Decode JSON into PHP array
        $this->jsonData = json_decode($response, true);
    }

    /**
     * Gets the fetched JSON Data
     * 
     * @return array Returns the fetched and json decoded data from the API
     */
    public function getData(): array
    {
        return $this->jsonData;
    }
}