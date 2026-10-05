<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

/**
 * What an OSF address names, and the one place that decides what counts as
 * one: the `codecheck.yml` import and the register deposit both ask here (#36).
 */
final class OsfRepositoryAddress
{
    /**
     * Reads an OSF address into the project and, when it names one, the file.
     *
     * Takes a project (`osf.io/<project>`, its files tab included) and a file
     * page within it, as OSF shows it (`osf.io/<project>/files/osfstorage/<id>`)
     * or by the file's short identifier (`osf.io/<project>/files/<guid>`). Both
     * file identifiers are ones OSF's files API answers for.
     *
     * A bare `osf.io/<guid>` is read as a project, which is what the register's
     * `osf::` names; telling a file's short link apart from it takes a request.
     *
     * @return ?array{node: string, file: ?string} null when the address is not an OSF project address
     */
    public static function parse(string $url): ?array
    {
        $address = preg_replace('/[?#].*$/', '', trim($url));

        if (!preg_match('#^https://(?:www\.)?osf\.io/([A-Za-z0-9]{5})(?:/files(?:/osfstorage)?(?:/([A-Za-z0-9]+))?)?/?$#', $address, $matches)) {
            return null;
        }

        return [
            'node' => $matches[1],
            'file' => $matches[2] ?? null,
        ];
    }
}
