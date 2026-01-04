<?php

namespace APP\plugins\generic\codecheck\api\v1;

use CurlHandle;
<<<<<<< HEAD
use APP\plugins\generic\codecheck\api\v1\ApiClientInterface;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlInitException;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlReadException;
=======
use App\plugins\generic\codecheck\classes\Exceptions\CurlInitException;
use App\plugins\generic\codecheck\classes\Exceptions\CurlReadException;
>>>>>>> 3b70be7 (Finished reworking curl calls, now possible to mock them #36, #75)

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

<<<<<<< HEAD
    public function fetch(string $url): string
=======
    public function get($url): string
>>>>>>> 3b70be7 (Finished reworking curl calls, now possible to mock them #36, #75)
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