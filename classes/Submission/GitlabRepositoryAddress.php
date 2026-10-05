<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

/**
 * What a GitLab address names, and the one place that decides what counts as
 * one: the `codecheck.yml` import and the register both ask here (#36).
 */
final class GitlabRepositoryAddress
{
    /** The branch the register reads a GitLab project's `codecheck.yml` from. */
    public const REGISTER_BRANCH = 'main';

    /**
     * Reads a gitlab.com address into project, ref, folder and file.
     *
     * Takes a project (`gitlab.com/<group>/<project>`, subgroups and a `.git`
     * suffix included) and a `-/tree/`, `-/blob/` or `-/raw/` address of a
     * folder or file in it. As for GitHub, a ref is one path segment, and
     * `path` is the folder; `file` is set only when the address names a YAML file.
     *
     * @return ?array{project: string, ref: ?string, path: string, file: ?string}
     *                null when the address is not a GitLab project address
     */
    public static function parse(string $url): ?array
    {
        $address = preg_replace('/[?#].*$/', '', trim($url));

        // A project's path is at least a group and a name, which GitLab keeps to
        // letters, digits, `_`, `-` and `.`; it is what `register.csv` holds.
        $segment = '[A-Za-z0-9_][A-Za-z0-9_.-]*';
        // GitLab's own pages share the address space; these are the ones an
        // editor might paste.
        if (!preg_match("#^https://(?:www\\.)?gitlab\\.com/(?!(?:users|groups|explore|dashboard|search|help)/)((?:{$segment}/)+{$segment})(?:/-/(?:tree|blob|raw)/(.+))?/?$#", $address, $matches)) {
            return null;
        }

        $project = preg_replace('/\.git$/', '', $matches[1]);
        // GitLab's own older addresses for a file or an issue, without `/-/`:
        // the keyword follows a group and a project and is followed by a ref
        // or a number. Anywhere else it is a project's or subgroup's name.
        if (preg_match('#^[^/]+/.+/(?:blob|tree|raw|issues|merge_requests|commits?|wikis)/[^/]+#', $project)) {
            return null;
        }
        $parts = ['project' => $project, 'ref' => null, 'path' => '', 'file' => null];
        if (($matches[2] ?? '') === '') {
            return $parts;
        }

        $segments = array_map('rawurldecode', explode('/', trim($matches[2], '/')));
        $parts['ref'] = array_shift($segments);
        if ($segments !== [] && preg_match('/\.ya?ml$/i', end($segments))) {
            $parts['file'] = array_pop($segments);
        }
        $parts['path'] = implode('/', $segments);

        return $parts;
    }

    /**
     * Where GitLab serves the file the address names, or else the
     * `codecheck.yml` in the folder it names, on its ref or the register's branch.
     */
    public static function downloadUrl(array $address): string
    {
        $path = ltrim($address['path'] . '/' . ($address['file'] ?? 'codecheck.yml'), '/');

        return 'https://gitlab.com/' . $address['project'] . '/-/raw/'
            . rawurlencode($address['ref'] ?? self::REGISTER_BRANCH) . '/'
            . implode('/', array_map('rawurlencode', explode('/', $path))) . '?inline=false';
    }
}
