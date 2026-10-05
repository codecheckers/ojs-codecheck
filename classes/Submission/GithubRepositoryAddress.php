<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

/**
 * What a GitHub repository address names, and the one place that decides what
 * counts as one: the `codecheck.yml` import and the register deposit both ask
 * here, so they cannot come to disagree about a host or a form (#36).
 */
final class GithubRepositoryAddress
{
    /**
     * Reads a GitHub address into owner, repository, ref, folder and file.
     *
     * Takes the repository root, a `tree/`, `blob/` or `raw/` address of a
     * folder or file on github.com, and a raw.githubusercontent.com file address, as
     * an editor copies them from the browser. A ref is taken as one path
     * segment, or as `refs/heads/<name>` / `refs/tags/<name>`, so a branch name
     * containing `/` is read as a branch and a folder.
     *
     * `path` is always the folder, which is what the register's
     * `github::owner/repo|path` names; `file` is set only when the address
     * names a YAML file, and `ref` is null when the address names none.
     *
     * @return ?array{owner: string, repo: string, ref: ?string, path: string, file: ?string}
     *                null when the address is not a GitHub repository address
     */
    public static function parse(string $url): ?array
    {
        $address = preg_replace('/[?#].*$/', '', trim($url));

        if (preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(.+)$#', $address, $matches)) {
            $rest = $matches[3];
        } elseif (preg_match('#^https://(?:www\.)?github\.com/([^/]+)/([^/]+?)(?:\.git)?(?:/(tree|blob|raw)/(.+))?/?$#', $address, $matches)) {
            $rest = $matches[4] ?? '';
        } else {
            return null;
        }

        $parts = [
            'owner' => $matches[1],
            'repo' => $matches[2],
            'ref' => null,
            'path' => '',
            'file' => null,
        ];
        if ($rest === '') {
            return $parts;
        }

        $segments = explode('/', trim($rest, '/'));
        $refLength = (count($segments) > 2 && $segments[0] === 'refs' && in_array($segments[1], ['heads', 'tags'], true)) ? 3 : 1;
        $parts['ref'] = implode('/', array_slice($segments, 0, $refLength));
        $pathSegments = array_slice($segments, $refLength);

        // A link to the file names its folder as well; anything that is not a
        // YAML file is taken as a folder to look for the `codecheck.yml` in.
        // A `tree/` link to a file is one GitHub redirects, so it counts too.
        if ($pathSegments !== [] && preg_match('/\.ya?ml$/i', end($pathSegments))) {
            $parts['file'] = array_pop($pathSegments);
        }
        $parts['path'] = implode('/', $pathSegments);

        return $parts;
    }
}
