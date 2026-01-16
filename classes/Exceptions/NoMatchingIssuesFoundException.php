<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions;

class NoMatchingIssuesFoundException extends \Exception
{
    public function __construct(
        string $message = "No matching issues found in the configured repository",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
