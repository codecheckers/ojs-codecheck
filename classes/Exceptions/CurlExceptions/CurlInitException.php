<?php

<<<<<<< HEAD
<<<<<<< HEAD
namespace APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions;
=======
namespace APP\plugins\generic\codecheck\classes\Exceptions;
>>>>>>> 3b70be7 (Finished reworking curl calls, now possible to mock them #36, #75)
=======
namespace APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions;
>>>>>>> 70e54c2 (Added full test coverage for imports from github and OSF #36, 74, #76)

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