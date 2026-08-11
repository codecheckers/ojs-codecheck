<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions;

class CurlHttpException extends \Exception
{
    public function __construct(
        string $message = "Error in the Curl session",
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}