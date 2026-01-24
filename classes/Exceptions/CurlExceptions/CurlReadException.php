<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions;

use CurlHandle;

class CurlReadException extends \Exception
{
    public function __construct(
        CurlHandle $curlHandle,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            curl_error($curlHandle),
            curl_errno($curlHandle),
            $previous
        );
    }
}