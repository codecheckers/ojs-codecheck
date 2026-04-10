<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions;

class CurlInitException extends \Exception
{
    public function __construct(
        string $message = "Error while creating data with the API",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}