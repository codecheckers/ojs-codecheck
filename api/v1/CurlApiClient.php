<?php

namespace APP\plugins\generic\codecheck\api\v1;

use CurlHandle;
use APP\plugins\generic\codecheck\api\v1\ApiClientInterface;
use App\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use App\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;

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
        return $response;
    }
}