<?php

namespace APP\plugins\generic\codecheck\api\v1;

use CurlHandle;
use APP\plugins\generic\codecheck\api\v1\ApiClientInterface;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;

class CurlApiClient implements ApiClientInterface
{
    private function initialize(string $url): CurlHandle
    {
        $curl_handle = curl_init($url);
        if($curl_handle === false) {
            throw new CurlInitException("Error initializing cURL Session", 500);
        }
        return $curl_handle;
    }

    public function fetch(string $url): string
    {
        $curlHandle = $this->initialize($url);

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        // follow redirects
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($curlHandle);
        if($response === false) {
            throw new CurlReadException($curlHandle);
        }

        $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new CurlHttpException(
                "Request to $url failed with HTTP status $httpCode. " . curl_error($curlHandle),
                $httpCode
            );
        }
        return $response;
    }
}