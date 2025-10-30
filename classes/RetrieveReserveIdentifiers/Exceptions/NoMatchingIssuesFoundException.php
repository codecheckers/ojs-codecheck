<?php

namespace APP\plugins\generic\codecheck\classes\RetrieveReserveIdentifiers;

class NoMatchingIssuesFoundException extends \Exception
{
    public function __construct(
        string $message = "No more issues available from GitHub API",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}