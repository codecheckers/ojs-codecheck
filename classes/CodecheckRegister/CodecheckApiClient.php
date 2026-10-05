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
     *
     * @throws \UnexpectedValueException when the answer is not JSON
     *
     * @return string `$response` The response is the json string from the CODECHECK API
     */
    public function fetch(string $url): string
    {
        // Fetch JSON from API
        $response = parent::fetch($url);

        // Anything but a JSON list or object is refused here, before a caller
        // reads it as one: a proxy's error page used to end as a type error.
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \UnexpectedValueException("The answer from {$url} is not JSON.");
        }
        $this->jsonData = $data;

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
