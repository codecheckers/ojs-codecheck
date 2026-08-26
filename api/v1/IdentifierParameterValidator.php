<?php

namespace APP\plugins\generic\codecheck\api\v1;

/**
 * Checks the POST body of the two endpoints that write to the CODECHECK
 * register: reserving a certificate identifier, and updating the register
 * issue on GitHub.
 *
 * Both build a GitHub issue out of the body, so a body of the wrong shape has
 * to be refused before anything is sent to the register rather than half-way
 * through writing to it.
 *
 * Every guard reads its key with `?? null`, so a body that simply omits a key
 * is reported as the bad request it is instead of raising "Undefined array
 * key" on the way to a 500.
 *
 * Each method returns the message for the first failed guard, or null when the
 * body is acceptable.
 */
class IdentifierParameterValidator
{
    /** The parameters both endpoints require. */
    private static function common(array $postParams): ?string
    {
        if (!is_array($postParams['issue'] ?? null)) {
            return "The parameter 'issue' must be an array!";
        }
        if (!is_array($postParams['issue']['labelsSelected'] ?? null)) {
            return "The parameter 'issue.labelsSelected' must be an array!";
        }
        if (!is_array($postParams['submission'] ?? null)) {
            return "Parameter 'submission' must be an array.";
        }
        if (!is_string($postParams['submission']['title'] ?? null)) {
            return "Parameter 'submission.title' must be a string.";
        }
        if (!is_string($postParams['submission']['authorString'] ?? null)) {
            return "Parameter 'submission.authorString' must be a string.";
        }
        if (!is_array($postParams['repositories'] ?? null)) {
            return "Parameter 'repositories' must be an array.";
        }
        if (!is_array($postParams['codecheckers'] ?? null)) {
            return "Parameter 'codecheckers' must be an array.";
        }

        return null;
    }

    /**
     * POST identifier — reserving or linking a certificate identifier.
     */
    public static function forReserveIdentifier(array $postParams): ?string
    {
        $error = self::common($postParams);
        if (!is_null($error)) {
            return $error;
        }
        if (!is_string($postParams['reserveIdentifierMode'] ?? null)) {
            return "No Reserve Identifier Mode was specified.";
        }
        if ($postParams['reserveIdentifierMode'] === 'linkExistingIdentifier' && !is_string($postParams['identifier'] ?? null)) {
            return "Parameter 'identifier' must be a string when using mode 'linkExistingIdentifier'.";
        }

        return null;
    }

    /**
     * POST issue — updating the register issue, which must already exist and so
     * must be identified by number and URL.
     */
    public static function forGithubIssueUpdate(array $postParams): ?string
    {
        $error = self::common($postParams);
        if (!is_null($error)) {
            return $error;
        }
        if (!is_int($postParams['issue']['number'] ?? null)) {
            return "The parameter 'issue.number' must be an integer!";
        }
        if (!is_string($postParams['issue']['url'] ?? null)) {
            return "The parameter 'issue.url' must be a string!";
        }

        return null;
    }
}
