<?php

namespace APP\plugins\generic\codecheck\api\v1;

class JsonResponse
{
    private string $payload;
    private int $httpResponseCode;

    /**
     * The JSON Response with an array as payload and a HTTP Response Code
     * 
     * @param $json_array The array that will be json encoded into the response
     * @param $responseState The HTTP Response Code that will be set accordingly
     * @return void
     */
    public function __construct(array $json_array, int $httpResponseCode)
    {
        $this->payload = json_encode($json_array);
        $this->httpResponseCode = $httpResponseCode;
    }

    /**
     * This function returns the Payload of the JSON Response
     * 
     * @return string The Payload of the JSON Response
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * This function returns the HTTP Response Code of the JSON Response
     * 
     * @return int The HTTP Response Code of the JSON Response
     */
    public function getHttpResponseCode(): int
    {
        return $this->httpResponseCode;
    }

    /**
     * This function creates a new JSON Response, echoes it to the HTML page it was calles upon and sets the according HTTP Response Code
     * 
     * @param $json_array The array that will be json encoded into the response
     * @param $responseState The HTTP Response Code that will be set accordingly
     * @return void
     */
    public function constructResponse(): void
    {
        // header for AJAX calls
        define('INDEX_FILE_STARTED', true);
        header('Content-Type: application/json');
        http_response_code($this->httpResponseCode);
        echo $this->payload;
        exit;
    }

    /**
     * This function creates a new JSON Response, echoes it to the HTML page it was calles upon and sets the according HTTP Response Code
     * 
     * @param $json_array The array that will be json encoded into the response
     * @param $responseState The HTTP Response Code that will be set accordingly
     * @return void
     */
    public static function staticResponse(array $json_array, int $httpResponseCode): void
    {
        $jsonResponse = new JsonResponse($json_array, $httpResponseCode);
        $jsonResponse->constructResponse();
    }
}
