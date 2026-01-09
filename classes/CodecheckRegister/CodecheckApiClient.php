<?php

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\plugins\generic\codecheck\api\v1\CurlApiClient;

class CodecheckApiClient extends CurlApiClient
{
    private $jsonData = [];

    /**
     * This function fetches all the data from the given URL
     * 
     * @param string $url The Url the `CodecheckApiClient` is calling
     * @return string `$response` The response is the json string from the CODECHECK API
     */
    public function fetch(string $url): string
    {
        // Fetch JSON from API
        $response = parent::fetch($url);

        // Decode JSON into PHP array
        $this->jsonData = json_decode($response, true);

        return $response;
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