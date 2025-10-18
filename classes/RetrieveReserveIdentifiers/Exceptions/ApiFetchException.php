<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

class ApiFetchException extends \Exception
{
    public function __construct(
        string $message = "Error fetching the API data",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}