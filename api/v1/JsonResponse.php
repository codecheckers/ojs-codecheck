<?php

namespace APP\plugins\generic\codecheck\api\v1;

class JsonResponse
{
    private string $payload;
    private int $httpResponseCode;

    /**
     * @param array $json_array The array that will be json encoded into the response
     * @param int $httpResponseCode The HTTP Response Code
     */
    public function __construct(array $json_array = [], int $httpResponseCode = 200)
    {
        $this->payload = json_encode($json_array);
        $this->httpResponseCode = $httpResponseCode;
    }

    /**
     * Returns the Payload of the JSON Response
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Returns the HTTP Response Code of the JSON Response
     */
    public function getHttpResponseCode(): int
    {
        return $this->httpResponseCode;
    }

    /**
     * Echoes the response and exits.
     */
    public function constructResponse(): void
    {
        define('INDEX_FILE_STARTED', true);
        header('Content-Type: application/json');
        http_response_code($this->httpResponseCode);
        echo $this->payload;
        exit;
    }

    /**
     * Convenience instance method used by CodecheckApiHandler.
     * Immediately sends the response and exits.
     */
    public function response(array $json_array, int $httpResponseCode): void
    {
        header('Content-Type: application/json');
        http_response_code($httpResponseCode);
        echo json_encode($json_array);
        exit;
    }

    /**
     * Static helper to create and send a response immediately.
     */
    public static function staticResponse(array $json_array, int $httpResponseCode): void
    {
        $jsonResponse = new JsonResponse($json_array, $httpResponseCode);
        $jsonResponse->constructResponse();
    }
}