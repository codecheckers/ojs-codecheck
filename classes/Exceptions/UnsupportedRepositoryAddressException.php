<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions;

/**
 * An address of no kind the CODECHECK Register names at all, as opposed to one
 * of a kind it names that it would read elsewhere (#36): publication validation
 * leaves the first to the deposit, as it was before the register was asked.
 */
class UnsupportedRepositoryAddressException extends \UnexpectedValueException
{
}
