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

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Codecheck/1.0; +https://codecheck.org.uk)', // Set the User Agent
            CURLOPT_HTTPHEADER => ['Accept: */*'],
        ]);

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

    /**
     * If the given URL is a DOI link (doi.org or dx.doi.org), resolve it to its
     * final destination URL by following redirects. Non-DOI URLs are returned unchanged.
     * If resolution fails for any reason, the original URL is returned.
     */
    public function resolveDoi(string $possibleDoiUrl): string
    {
        if (!preg_match('#^(?:(?:https?://)?(?:dx\.)?doi\.org/)?10\.\d{4,9}/.+$#i', $possibleDoiUrl)) {
            return $possibleDoiUrl;
        }

        // Normalize into a fully-qualified URL for cURL, regardless of what
        // scheme/host form the input came in as (bare DOI, doi.org/..., etc.)
        $doi = preg_replace('#^(?:https?://)?(?:dx\.)?(?:doi\.org/)?#i', '', $possibleDoiUrl);
        $url = 'https://doi.org/' . $doi;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Codecheck/1.0; +https://codecheck.org.uk)',
        ]);

        curl_exec($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);
        curl_close($ch);

        if ($error || !$effectiveUrl) {
            return $possibleDoiUrl;
        }

        return $effectiveUrl;
    }
}