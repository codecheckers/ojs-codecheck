<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions\RoleExceptions;

class RoleNotFoundException extends \Exception
{
    public function __construct(
        string $message = "The CODECHECK Role was not found in the array of roles.",
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 404, $previous);
    }
}