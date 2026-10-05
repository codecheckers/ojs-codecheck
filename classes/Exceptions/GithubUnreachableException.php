<?php

namespace APP\plugins\generic\codecheck\classes\Exceptions;

/**
 * GitHub did not answer, as opposed to answering that something is not there
 * (#36): a check that must not stop on GitHub's availability accepts the first.
 */
class GithubUnreachableException extends \UnexpectedValueException
{
}
