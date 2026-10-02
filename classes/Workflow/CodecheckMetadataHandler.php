<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';

use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\CodecheckGithubRegisterApiClient;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use Github\Client;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class CodecheckMetadataHandler
{
    private mixed $submissionId;
    private Client $client;
    private CurlApiClient $curlApiClient;

    /**
     * `CodecheckMetadataHandler`
     *
     * @param \APP\core\Request $request The API Request
     */
    public function __construct(Request $request, Client $client = new Client(), CurlApiClient $curlApiClient = new CurlApiClient())
    {
        $this->client = $client;
        $this->submissionId = $request->getUserVar('submissionId');
        $this->curlApiClient = $curlApiClient;

        // Load Composer dependencies if not already loaded
        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once($autoloadPath);
            }
        }
    }

    /**
     * Get the submission ID
     *
     * @return mixed Returns the Submission ID for the Request that was passed in the constructor
     */
    public function getSubmissionId(): mixed
    {
        return $this->submissionId;
    }

    /**
     * Without `$revealAuthors` — a viewer who may not know the authors (#28) —
     * the authors and the contact are left out and `authorsWithheld` says so.
     * There is no default: whoever calls this says whether the answer goes to
     * such a viewer, and the journal's own checks pass `true`.
     */
    public function getMetadata($request, $submissionId, bool $revealAuthors): array
    {
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();

        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        $response = [
            'submissionId' => $submissionId,
            'submission' => [
                'id' => $submission->getId(),
                'title' => $publication ? $publication->getLocalizedTitle() : '',
                'authors' => $revealAuthors ? $this->getAuthors($publication) : [],
                'contact' => $revealAuthors ? $this->getContact($publication) : null,
                'authorsWithheld' => !$revealAuthors,
                'doi' => $publication ? $publication->getStoredPubId('doi') : null,
                // The statement lives on the publication (see Submission/Schema.php),
                // which is also where the wizard writes it and where the article page reads it.
                'dataAvailabilityStatement' => $publication ? $publication->getData('dataAvailabilityStatement') : null,
            ],
            'codecheck' => $metadata ? [
                // The wire key stays `version`: the Vue form and the e2e
                // specs speak it, and only the column was renamed (#93).
                'version' => Constants::resolveConfigVersion($metadata->spec_version ?? null),
                'publicationType' => $metadata->publication_type ?? 'doi',
                'manifest' => json_decode($metadata->manifest ?? '[]', true),
                'repository' => json_decode($metadata->repository ?? '{"repositories":null}', true),
                'codecheckers' => json_decode($metadata->codecheckers ?? '[]', true),
                'source' => $metadata->source,
                'certificate' => $metadata->certificate,
                'issue' => json_decode($metadata->issue ?? '[]', true),
                'check_time' => $metadata->check_time,
                'summary' => $metadata->summary,
                'report' => $metadata->report,
                'additionalContent' => $metadata->additional_content,
            ] : null
        ];

        return $response;
    }

    public function saveMetadata($request, $submissionId): array
    {
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return ['success' => false, 'error' => 'Submission not found', 'status' => 404];
        }

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        // Every field below falls back to a default when its key is missing, so
        // a body that did not parse — truncated, or not an object — would
        // overwrite the certificate, the GitHub issue, the codecheckers, the
        // summary and the rest with blanks, and answer "saved successfully".
        // There is nothing to save in that case, so it is refused.
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'The request body is not a CODECHECK metadata object.',
                'status' => 400,
            ];
        }

        // A version the plugin does not implement cannot be recorded. Reading
        // it as the default would store a different version than the one sent
        // and answer success; an absent `version` is not this, and keeps the
        // stored one below.
        if (array_key_exists('version', $data) && !Constants::isKnownConfigVersion($data['version'])) {
            return [
                'success' => false,
                'error' => __('plugins.generic.codecheck.configVersion.unknown', [
                    // Shortened: the client chose it, and it is quoted back in a message.
                    'version' => is_scalar($data['version']) ? mb_substr((string) $data['version'], 0, 50) : gettype($data['version']),
                ]),
                'status' => 400,
            ];
        }

        $nullIfEmpty = function ($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };
        $trim = fn ($value) => is_string($value) ? trim($value) : $value;

        $stored = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        // Refuse addresses that cannot be a repository link rather than storing
        // them and guarding every place they are published (Issue #154) — but
        // only the ones this payload introduces. Refusing the whole record for
        // an address already in it turned away saves that changed something
        // else entirely, which is how an author's `github.com/me/project` could
        // lock an editor out of the form (issue #170).
        $unusable = CodecheckRepositories::newUnusableUrls(
            $data['repository'] ?? null,
            $stored->repository ?? null
        );
        if ($unusable !== []) {
            return [
                'success' => false,
                'error' => __('plugins.generic.codecheck.repositories.invalidUrl', [
                    'repository' => implode(', ', $unusable),
                ]),
                'status' => 400,
            ];
        }

        // The same rule, at the same boundary, for the ORCID iDs: they reach the
        // generated `codecheck.yml`, the article page and the public register,
        // and were stored exactly as typed. Only what this save introduces is
        // judged, for the reason above.
        $unusableOrcids = CodecheckCodecheckers::newUnusableOrcids(
            $data['codecheckers'] ?? null,
            $stored->codecheckers ?? null
        );
        if ($unusableOrcids !== []) {
            return [
                'success' => false,
                'error' => __('plugins.generic.codecheck.codecheckers.invalidOrcid', [
                    'orcid' => implode(', ', $unusableOrcids),
                ]),
                'status' => 400,
            ];
        }

        $metadataData = [
            'submission_id' => $submissionId,
            // As with `repository` below, a payload with no `version` leaves the
            // stored one alone. A stored version the plugin does not know reads
            // as the default; one in the payload was refused above.
            'spec_version' => Constants::resolveConfigVersion($data['version'] ?? $stored->spec_version ?? null),
            'publication_type' => $data['publication_type'] ?? 'doi',
            'manifest' => json_encode($data['manifest'] ?? []),
            // A payload with no `repository` key leaves the stored list alone. It
            // used to be overwritten with `{"repositories":null}`, so a partial
            // save — or a body that failed to parse — erased every repository,
            // the flagged one included, and still answered success. An empty
            // list is a different thing and still means "remove them all".
            'repository' => array_key_exists('repository', $data)
                ? json_encode(CodecheckRepositories::withOneMarked($data['repository'] ?? ['repositories' => null]))
                : ($stored->repository ?? json_encode(['repositories' => null])),
            'source' => $nullIfEmpty($data['source'] ?? null),
            'codecheckers' => json_encode(CodecheckCodecheckers::withNormalizedOrcids($data['codecheckers'] ?? [])),
            // Trimmed so that what is stored is what the generated codecheck.yml
            // carries, and what the form judges against the specification.
            'certificate' => $nullIfEmpty($trim($data['certificate'] ?? null)),
            'issue' => json_encode($data['issue'] ?? ['url' => null, 'number' => null, 'labelsSelected' => []]),
            'check_time' => $nullIfEmpty($data['check_time'] ?? null),
            'summary' => $nullIfEmpty($data['summary'] ?? null),
            'report' => $nullIfEmpty($data['report'] ?? null),
            'additional_content' => $nullIfEmpty($data['additional_content'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($stored) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($metadataData);
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
        }

        return [
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully'
        ];
    }

    /**
     * The generated `codecheck.yml`. Without `$revealAuthors`, the paper's
     * authors are left out — see getMetadata().
     */
    public function generateYaml($request, $submissionId, bool $revealAuthors): array
    {
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return ['error' => 'Submission not found'];
        }

        $publication = $submission->getCurrentPublication();

        $metadata = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        if (!$metadata) {
            return ['error' => 'No CODECHECK metadata found'];
        }

        $yaml = $this->buildYaml($publication, $metadata, $revealAuthors);

        return [
            'yaml' => $yaml,
            'filename' => 'codecheck.yml'
        ];
    }

    public function buildYaml($publication, $metadata, bool $revealAuthors = true): string
    {
        $manifest = json_decode($metadata->manifest ?? '[]', true);
        $codecheckers = json_decode($metadata->codecheckers ?? '[]', true);
        $repository = json_decode($metadata->repository ?? '{"repositories":null}', true);

        // Build YAML data structure. The version follows the one recorded for
        // this check rather than a fixed one, so the file declares the
        // specification the codechecker actually filled the form in against.
        $data = [
            'version' => Constants::getConfigSpecUrl(Constants::resolveConfigVersion($metadata->spec_version ?? null))
        ];

        // Add source if present
        if ($metadata->source) {
            $data['source'] = $metadata->source;
        }

        // Paper section
        //
        // The author's ORCID is normalised for the same reason the
        // codechecker's below is: OJS stores an author's as the full
        // `https://orcid.org/…` URI — its own templates use the stored value as
        // an `href` — while a codechecker's is bare, so the generated file
        // carried both forms at once, in two neighbouring sections.
        $paperData = ['title' => $publication->getLocalizedTitle()];
        if ($revealAuthors) {
            $paperData['authors'] = [];
            foreach ($this->getAuthors($publication) as $author) {
                $authorData = ['name' => $author['name']];
                $orcid = CodecheckCodecheckers::normalizeOrcid($author['orcid'] ?? '');
                if ($orcid !== '') {
                    $authorData['ORCID'] = $orcid;
                }
                $paperData['authors'][] = $authorData;
            }
        }

        $doi = $publication->getStoredPubId('doi');
        if ($doi) {
            $paperData['reference'] = 'https://doi.org/' . $doi;
        }

        $data['paper'] = $paperData;

        // Manifest section
        $manifestData = [];
        foreach ($manifest as $file) {
            $fileData = ['file' => $file['file'] ?? ''];
            if (!empty($file['comment'])) {
                $fileData['comment'] = $file['comment'];
            }
            $manifestData[] = $fileData;
        }
        $data['manifest'] = $manifestData;

        // Codechecker section
        $codecheckerData = [];
        foreach ($codecheckers as $checker) {
            $checkerData = ['name' => $checker['name'] ?? ''];
            // Normalised on read as well as on save, because a record written
            // before the rule existed is never rewritten.
            $orcid = CodecheckCodecheckers::normalizeOrcid($checker['orcid'] ?? '');
            if ($orcid !== '') {
                $checkerData['ORCID'] = $orcid;
            }
            $codecheckerData[] = $checkerData;
        }
        $data['codechecker'] = $codecheckerData;

        // Summary
        if ($metadata->summary) {
            $data['summary'] = $metadata->summary;
        }

        // Repository — hidden entries are left out, by the same rule the article
        // page applies, so the two cannot come to disagree about what is public.
        $publicUrls = array_column(CodecheckRepositories::publicEntries($repository), 'url');

        $stored = is_array($repository['repositories'] ?? null) ? $repository['repositories'] : [];
        $withheld = count($stored) - count($publicUrls);
        if ($withheld > 0) {
            CodecheckLogger::debug("Left {$withheld} of " . count($stored) . ' repositories out of the codecheck.yml: hidden, or with no address.');
        }

        // The specification takes "a URL or a list of URLs", so several
        // repositories are a YAML sequence. They used to be joined with commas
        // into one scalar, which no consumer of the file could resolve (#154).
        if ($publicUrls !== []) {
            $data['repository'] = count($publicUrls) === 1 ? $publicUrls[0] : $publicUrls;
        }

        // Check time
        if ($metadata->check_time) {
            $data['check_time'] = date('Y-m-d H:i:s', strtotime($metadata->check_time));
        }

        // Certificate
        if ($metadata->certificate) {
            $data['certificate'] = $metadata->certificate;
        }

        // Report
        if ($metadata->report) {
            $data['report'] = $metadata->report;
        }

        // Generate YAML
        $yaml = "---\n" . Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Post-process to match original format
        $yaml = $this->normalizeYamlOutput($yaml);

        // Add custom additional content at the end if present
        if ($metadata->additional_content) {
            $yaml .= "\n" . trim($metadata->additional_content) . "\n";
        }

        return $yaml;
    }

    /**
     * Get the Authors for a specific publication
     *
     * @param mixed $publication The publication data
     *
     * @return array The Authors with Name and ORCID (if isset) in an Array
     */
    public function getAuthors($publication): array
    {
        if (!$publication) {
            return [];
        }

        $authors = [];
        foreach ($publication->getData('authors') as $author) {
            $authors[] = [
                'name' => $this->getAuthorName($author),
                'orcid' => $author->getOrcid()
            ];
        }
        return $authors;
    }

    /**
     * Whom a codechecker asks about the check: the publication's primary
     * contact, which the author chooses among the contributors (#28).
     *
     * No fallback to the first author: that would name someone nobody chose.
     * The email is deliberately not in getAuthors(), which feeds the
     * `codecheck.yml`.
     *
     * @return array{name: string, email: string}|null
     */
    public function getContact($publication): ?array
    {
        $author = $publication?->getPrimaryAuthor();
        if (!$author) {
            return null;
        }

        return [
            'name' => $this->getAuthorName($author),
            'email' => (string) $author->getEmail(),
        ];
    }

    /** An author's name as the form and the `codecheck.yml` show it. */
    private function getAuthorName($author): string
    {
        $locale = $author->getDefaultLocale();
        $givenName = $author->getGivenName($locale) ?? '';
        $familyName = $author->getFamilyName($locale) ?? '';

        return trim($givenName . ' ' . $familyName);
    }

    /**
     * Cosmetic tidying of the dumped YAML.
     *
     * Symfony quotes a value when quoting changes nothing about how it parses,
     * and unquoting an address it round-trips is safe. Unquoting *any* simple
     * scalar is not: the rule that used to sit here,
     * `preg_replace("/'([^':\n]+)'/", '$1', $yaml)`, stripped the quotes from
     * every scalar without a colon, which changes the value's YAML type. A paper
     * title of `[a, b]` became a sequence, a summary of `yes` became a boolean,
     * `*x` became an alias and stopped parsing at all, and `it''s fine` lost the
     * doubled quote and came out as `its fine`. Since the file is validated at
     * publication and deposited in the public register, an author could make
     * their own submission unpublishable by choosing a title.
     */
    private function normalizeYamlOutput(string $yaml): string
    {
        // An http(s) address parses the same quoted or not.
        $yaml = preg_replace("/'(https?:\/\/[^']+)'/", '$1', $yaml);

        // Whitespace only: put a list item's first key on the dash line.
        $yaml = preg_replace('/^(\s+)-\n\s+(\w+):/m', '$1- $2:', $yaml);

        return $yaml;
    }

    /**
     * The import behind the editorial form: the repository's `codecheck.yml`, but
     * only when it is for this submission's paper (#28). The form shows the title
     * as read-only submission data, so a file naming another paper — a wrong
     * repository, or a DOI that resolves elsewhere — is refused rather than
     * having its other values filled in. A file with no paper title cannot be
     * tied to the paper either, so it is refused the same way.
     */
    public function importMetadataForSubmission(string $repository, string $submissionTitle): JsonResponse
    {
        $response = $this->importMetadataFromRepository($repository);
        if (!$response->isSuccess()) {
            return $response;
        }

        $payload = $response->getPayloadArray();
        if (!self::titlesMatch($payload['metadata']['paper']['title'] ?? null, $submissionTitle)) {
            return new JsonResponse([
                'success' => false,
                'error' => __('plugins.generic.codecheck.repositories.titleMismatch'),
                'repository' => $repository,
            ], 422);
        }

        return $response;
    }

    /**
     * Whether two paper titles name the same paper: whitespace runs (including
     * non-breaking spaces and line breaks a YAML scalar may carry) count as one
     * space, and capitals do not matter. Anything that is not a string never matches.
     */
    public static function titlesMatch(mixed $first, mixed $second): bool
    {
        if (!is_string($first) || !is_string($second)) {
            return false;
        }

        $normalise = fn (string $title): string => mb_strtolower(
            trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $title) ?? $title)
        );

        $normalisedFirst = $normalise($first);

        return $normalisedFirst !== '' && $normalisedFirst === $normalise($second);
    }

    public function importMetadataFromRepository(string $repository): JsonResponse
    {
        // Deliberately *not* guarded with `Constants::isWebUrl()`: this is an
        // import, not a write, and a bare DOI ("10.5281/zenodo.1234567")
        // resolves here — `resolveDoi()` takes the scheme and host as optional.
        // The branches below refuse anything they do not recognise, and the
        // write boundaries are where the rule belongs (#170).
        //
        // Resolve DOI links (e.g. https://doi.org/10.5281/zenodo.1234567) to their
        // final destination, in case Zenodo or OSF repositories are provided as a DOI.
        $repository = $this->curlApiClient->resolveDoi($repository);

        // Check if the repository is a Zenodo Repository
        if (preg_match('#^https://zenodo\.org/records/\d{8}/?$#', $repository)) {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            return $this->importMetadataFromZenodo($repository);
        }
        // Check if the Repository is a GitHub Repository
        elseif (preg_match('#^https://github\.com/codecheckers/#', $repository)) {
            return $this->importMetadataFromGitHub($repository);
        }
        // Check if the Repository is an OSF Repository
        elseif (preg_match('#^https://osf\.io/([A-Za-z0-9]{5})/?$#', $repository, $matches)) {
            $osf_node_id = $matches[1];
            return $this->importMetadataFromOSF($osf_node_id);
        }
        // Check if the Repository is a GitLab Repository
        elseif (preg_match('#^https://gitlab\.com/cdchck/community-codechecks/([^/]+)/?$#', $repository)) {
            // Remove trailing / if it exists
            $repository = rtrim($repository, '/');
            return $this->importMetadataFromGitLab($repository);
        } else {
            return new JsonResponse([
                'success' => false,
                'repository' => $repository,
                'error' => 'The repository (' . $repository . ") URL isn't of the required format.",
            ], 400);
        }
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitHub Repository
     *
     * @param string $repository The GitHub Repository
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromGitHub(string $repository): JsonResponse
    {
        $githubUrlParts = CodecheckGithubRegisterApiClient::parseGithubUrl($repository);
        $filename = 'codecheck.yml';

        // AUTO-DETECT DEFAULT BRANCH if path is root
        if ($githubUrlParts['path'] === '') {
            try {
                $repoData = $this->client->api('repo')->show($githubUrlParts['owner'], $githubUrlParts['repo']);
                $githubUrlParts['ref'] = $repoData['default_branch'];
            } catch (\Exception $e) {
                // fallback stays 'main'
            }
        }

        // Retrieve folder contents
        try {
            $contents = $this->client->api('repo')->contents()->show(
                $githubUrlParts['owner'],
                $githubUrlParts['repo'],
                $githubUrlParts['path'],
                $githubUrlParts['ref']
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => "There is no '{$filename}' file in this repository.",
                'repository' => $repository,
            ], 404);
        }

        // A path that is not a directory listing comes back as null or a single
        // file rather than a list, which is not something to iterate over.
        if (!is_iterable($contents)) {
            return new JsonResponse([
                'success' => false,
                'repository' => $repository,
                'error' => "{$filename} not found",
            ], 404);
        }

        // Find codecheck.yml
        foreach ($contents as $item) {
            if ($item['type'] === 'file' && $item['name'] === $filename) {

                // Fetch the raw content of the codecheck.yml file
                $file = $this->client->api('repo')->contents()->show(
                    $githubUrlParts['owner'],
                    $githubUrlParts['repo'],
                    $item['path'],
                    $githubUrlParts['ref']
                );

                $metadata = Yaml::parse(base64_decode($file['content']));

                return new JsonResponse([
                    'success' => true,
                    'repository' => $repository,
                    'metadata' => $metadata,
                ], 200);
            }
        }

        return new JsonResponse([
            'success' => false,
            'repository' => $repository,
            'error' => "{$filename} not found",
        ], 404);
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK Zenodo Repository
     *
     * @param string $repository The Zenodo Repository
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromZenodo(string $repository): JsonResponse
    {
        $filename = 'codecheck.yml';
        $pathToCodecheckYaml = $repository . '/files/' . $filename . '?download=1';

        return $this->readYamlContent($pathToCodecheckYaml, $repository);
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK OSF Repository
     *
     * @param string $osf_node_id The node_id of the OSF Repository for the OSF API
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromOSF(string $osf_node_id): JsonResponse
    {
        $filename = 'codecheck.yml';
        $repository = "https://osf.io/{$osf_node_id}/";
        $apiUrl = 'https://api.osf.io/v2/nodes/' . $osf_node_id . '/files/osfstorage/';

        // Get YAML Contents
        try {
            $api_response = $this->curlApiClient->fetch($apiUrl);
            $data = json_decode($api_response, true);

            if (!$data || !isset($data['data'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid OSF API response',
                    'repository' => $repository
                ], 500);
            }

            // Search for the codecheck.yml and get the guid of the codecheck.yml
            $guid = null;

            foreach ($data['data'] as $item) {
                $attributes = $item['attributes'];

                if (isset($attributes['name']) && $attributes['name'] === $filename) {
                    $guid = $attributes['guid'];   // This is the OSF file GUID
                    break;
                }
            }

            if ($guid) {
                $pathToCodecheckYaml = 'https://osf.io/download/' . $guid . '/';
                $repository = 'https://osf.io/' . $osf_node_id . '/';
                return $this->readYamlContent($pathToCodecheckYaml, $repository);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => "{$filename} not found",
                    'repository' => $repository
                ], 404);
            }
        }
        // Check if cURL went wrong
        catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'repository' => $repository
            ], JsonResponse::errorStatus($e));
        }
    }

    /**
     * Import the codecheck metadata from an existing `codecheck.yml` from the CODECHECK GitLab Repository
     *
     * @param string $repository The GitLab Repository
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromGitLab(string $repository): JsonResponse
    {
        $filename = 'codecheck.yml';
        $pathToCodecheckYaml = $repository . '/-/raw/main/' . $filename . '?ref_type=heads&inline=false';

        return $this->readYamlContent($pathToCodecheckYaml, $repository);
    }

    /**
     * Read the yaml data and return an API response array with the content of the yaml file
     *
     * @param string $pathToYamlContent The exact path to the download of the yaml file
     * @param string $repository The exact path to the code repository
     *
     * @return JsonResponse The API Response with the repository and the yaml content array
     */
    private function readYamlContent(string $pathToYamlContent, string $repository): JsonResponse
    {
        // Get YAML Contents
        try {
            $yamlContent = $this->curlApiClient->fetch($pathToYamlContent);

            $metadata = Yaml::parse($yamlContent);

            return new JsonResponse([
                'success' => true,
                'repository' => $repository,
                'metadata' => $metadata,
            ], 200);
        }
        // Check if something went wrong
        catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'repository' => $repository,
            ], JsonResponse::errorStatus($e));
        }
    }
}
