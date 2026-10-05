<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

/**
 * What a Zenodo address names, and the one place that decides what counts as
 * one: the `codecheck.yml` import and the register both ask here (#36).
 */
final class ZenodoRepositoryAddress
{
    /**
     * Reads a Zenodo address into the record and, when it names one, the file.
     *
     * Takes a record (`zenodo.org/records/<id>`, or the older `record/<id>`)
     * and a file in it (`…/files/<name>`, with `?download=1` or `/content`),
     * on zenodo.org or on the sandbox, which the register names apart.
     *
     * @return ?array{record: string, file: ?string, sandbox: bool} null when the address is not a Zenodo record address
     */
    public static function parse(string $url): ?array
    {
        $address = preg_replace('/[?#].*$/', '', trim($url));

        if (!preg_match('#^https://(sandbox\.)?zenodo\.org/records?/(\d+)(?:/files/([^/]+?)(?:/content)?)?/?$#', $address, $matches)) {
            return null;
        }

        return [
            'record' => $matches[2],
            'file' => isset($matches[3]) ? rawurldecode($matches[3]) : null,
            'sandbox' => $matches[1] !== '',
        ];
    }

    /** Where Zenodo serves the file the address names, or else the record's `codecheck.yml`. */
    public static function downloadUrl(array $address): string
    {
        return 'https://' . ($address['sandbox'] ? 'sandbox.' : '') . 'zenodo.org/records/'
            . $address['record'] . '/files/' . rawurlencode($address['file'] ?? 'codecheck.yml') . '?download=1';
    }
}
