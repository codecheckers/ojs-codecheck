<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

use APP\plugins\generic\codecheck\classes\Exceptions\ApiFetchException;

class JsonApiCaller
{
    private $url;
    private $jsonData = [];

    function __construct(string $url)
    {
        $this->url = $url;
    }

    public function fetch()
    {
        // Fetch JSON from API
        $response = file_get_contents($this->url);

        // throw error if no data was fetched from API
        if ($response === FALSE) {
            throw new ApiFetchException("Error fetching the Codecheck API data");
        }

        // Decode JSON into PHP array
        $this->jsonData = json_decode($response, true);
    }

    public function getData(): array
    {
        return $this->jsonData;
    }
}