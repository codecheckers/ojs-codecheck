<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';

use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\RegisterCodecheckers;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\DoiDeposit\CodecheckDoiDeposit;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckerDirectory;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckRepositories;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use APP\plugins\generic\codecheck\classes\Submission\GithubRepositoryAddress;
use APP\plugins\generic\codecheck\classes\Submission\OsfRepositoryAddress;
use APP\plugins\generic\codecheck\classes\Submission\ZenodoRepositoryAddress;
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
    public function __construct(Request $request, ?Client $client = null, CurlApiClient $curlApiClient = new CurlApiClient())
    {
        $this->client = $client ?? GithubHttp::client();
        $this->submissionId = $request->getUserVar('submissionId');
        $this->curlApiClient = $curlApiClient;
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

    /**
     * @param array $enabledVersions The config versions the journal offers.
     *        Required, so a caller cannot leave the check off by forgetting it
     */
    public function saveMetadata($request, $submissionId, array $enabledVersions): array
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

        $nullIfEmpty = function ($value) {
            return (is_string($value) && trim($value) === '') ? null : $value;
        };
        $trim = fn ($value) => is_string($value) ? trim($value) : $value;

        $stored = DB::table('codecheck_metadata')
            ->where('submission_id', $submissionId)
            ->first();

        // A version posted for the record has to be one the plugin implements and
        // the journal offers — or the one the record is already on, so a record
        // on a version the journal has since stopped offering still saves.
        // Reading a refused version as the default instead would store another
        // version than the one sent and answer success. An absent `version`
        // keeps the stored one below. The stored version is compared as the form
        // saw it (`GET metadata` answers the resolved one), not as the row holds it.
        if (array_key_exists('version', $data)) {
            $reason = null;
            if (!Constants::isKnownConfigVersion($data['version'])) {
                $reason = 'plugins.generic.codecheck.configVersion.unknown';
            } elseif (!Constants::isConfigVersionAllowed(
                $data['version'],
                $enabledVersions,
                Constants::resolveConfigVersion($stored->spec_version ?? null)
            )) {
                $reason = 'plugins.generic.codecheck.configVersion.notOffered';
            }
            if ($reason !== null) {
                return [
                    'success' => false,
                    'error' => __($reason, [
                        // Shortened: the client chose it, and it is quoted back in a message.
                        'version' => is_scalar($data['version']) ? mb_substr((string) $data['version'], 0, 50) : gettype($data['version']),
                    ]),
                    'status' => 400,
                ];
            }
        }

        // Who the record names as codecheckers is for an editor to decide: their
        // ORCID iDs are what an ORCID account must match to be credited for the
        // check, so a reviewer who could edit the list could name an account
        // they hold and connect it (GHSA-4p3r-qgp4-g74r). Anyone else's save
        // keeps the stored list exactly as it is, whatever was posted: their
        // form does not offer the list, so a different one is stale — loaded
        // before an editor changed it — or crafted, and neither should refuse
        // the rest of the save. Kept as stored, not normalised, so a legacy
        // entry is not rewritten by someone who may not change it.
        $mayEditCodecheckers = CodecheckSubmissionAccess::mayEditCodecheckers(
            $request->getUser(),
            (int) $submissionId,
            (int) $request->getContext()?->getId()
        );
        if (!$mayEditCodecheckers) {
            $data['codecheckers'] = json_decode($stored->codecheckers ?? '[]', true);
        }

        // The certificate identifier and the register issue it names are a
        // journal manager's (#65), as reserving one is: anyone else's form
        // shows them read-only, so their save keeps what is stored, the same
        // way as the codecheckers above.
        if (!CodecheckSubmissionAccess::mayManageIdentifier($request->getUser(), (int) $request->getContext()?->getId())) {
            $data['certificate'] = $stored->certificate ?? null;
            $data['issue'] = json_decode($stored->issue ?? 'null', true);
        }

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

        // And for the GitHub usernames, which the register issue is assigned to
        // (#186): a name GitHub would refuse is better refused here, where the
        // editor typed it, than at a public issue that ends up unassigned.
        $unusableUsernames = CodecheckCodecheckers::newUnusableGithubUsernames(
            $data['codecheckers'] ?? null,
            $stored->codecheckers ?? null
        );
        if ($unusableUsernames !== []) {
            return [
                'success' => false,
                'error' => __('plugins.generic.codecheck.codecheckers.invalidGithubUsername', [
                    'username' => implode(', ', $unusableUsernames),
                ]),
                'status' => 400,
            ];
        }

        $codecheckers = CodecheckCodecheckers::withNormalizedEntries($data['codecheckers'] ?? []);

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
            'codecheckers' => $mayEditCodecheckers ? json_encode($codecheckers) : ($stored->codecheckers ?? '[]'),
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

        $linksBefore = CodecheckDoiDeposit::linksBeforeChange((int) $submissionId);
        if ($stored) {
            DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->update($metadataData);
        } else {
            $metadataData['created_at'] = date('Y-m-d H:i:s');
            DB::table('codecheck_metadata')->insert($metadataData);
        }
        // A certificate DOI or repository entered after the article was
        // deposited reaches Crossref or DataCite, if the journal asked (#19).
        CodecheckDoiDeposit::redepositIfChanged((int) $submissionId, $linksBefore);

        $this->afterSave($request, $submissionId, $stored, $codecheckers, $metadataData['issue']);

        return [
            'success' => true,
            'message' => 'CODECHECK metadata saved successfully'
        ];
    }

    /**
     * What a save sets off beyond the record (#186), for an
     * editor only: the journal's directory and the register issue are both
     * journal-wide, and a reviewer is held to one submission everywhere else
     * (#173).
     *
     * - The directory learns the entries this save brings in or changes, not
     *   the ones it carries unchanged: an older record re-saved must not put
     *   back a name or username a newer check replaced.
     * - The register issue is assigned when this save brings in a username, or
     *   records the issue for the first time — the identifier is reserved
     *   before the record holds the issue, so the assignment waits for this
     *   save. `CodecheckStatusRegisterUpdate::syncAssignees()` decides the rest.
     * - The register issue's JSON metadata block is rewritten from the record.
     */
    private function afterSave($request, int $submissionId, ?object $stored, array $codecheckers, string $issue): void
    {
        $context = $request->getContext();
        if (!$context || !CodecheckSubmissionAccess::isEditor($request->getUser(), $context->getId())) {
            return;
        }

        $storedCodecheckers = CodecheckCodecheckers::withNormalizedEntries($stored->codecheckers ?? null);

        CodecheckCodecheckerDirectory::rememberAll(
            $context->getId(),
            array_values(array_filter($codecheckers, fn (array $entry) => !in_array($entry, $storedCodecheckers, true)))
        );

        $newUsernames = array_diff(RegisterCodecheckers::usernames($codecheckers), RegisterCodecheckers::usernames($storedCodecheckers));
        $storedIssueNumber = json_decode($stored->issue ?? '', true)['number'] ?? null;
        $issueNumber = json_decode($issue, true)['number'] ?? null;

        if ($newUsernames !== [] || ($issueNumber !== null && $issueNumber !== $storedIssueNumber)) {
            CodecheckStatusRegisterUpdate::syncAssignees($submissionId);
        }

        // The issue's JSON metadata follows the record on every save; it is
        // read first and only written when it changed.
        CodecheckStatusRegisterUpdate::refreshMetadata($submissionId);
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
        if (($zenodoAddress = ZenodoRepositoryAddress::parse($repository)) !== null) {
            return $this->readYamlContent(ZenodoRepositoryAddress::downloadUrl($zenodoAddress), $repository);
        }
        // Check if the Repository is a GitHub Repository
        elseif (($githubAddress = GithubRepositoryAddress::parse($repository)) !== null) {
            return $this->importMetadataFromGitHub($repository, $githubAddress);
        }
        // Check if the Repository is an OSF Repository
        elseif (($osfAddress = OsfRepositoryAddress::parse($repository)) !== null) {
            return $this->importMetadataFromOSF($repository, $osfAddress);
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
     * Default branches GitHub has named in this request, by `owner/repo`:
     * publication validation and the deposit both ask, once per article of an
     * issue. Only an answer is remembered, so a failure is asked again.
     */
    private static array $defaultBranches = [];

    /**
     * GitHub's default branch for a repository, or null when GitHub could not
     * say: what the register reads, for `RegisterRepositoryName::for()`.
     *
     * @throws \UnexpectedValueException When GitHub did not answer or has no
     *                                    such public repository, saying so
     */
    public function githubDefaultBranch(string $owner, string $repo): ?string
    {
        $key = strtolower("{$owner}/{$repo}");
        if (isset(self::$defaultBranches[$key])) {
            return self::$defaultBranches[$key];
        }

        try {
            $branch = $this->client->api('repo')->show($owner, $repo)['default_branch'] ?? null;
        } catch (\Exception $e) {
            if (GithubHttp::wasUnreachable()) {
                throw new \UnexpectedValueException(GithubHttp::unreachableMessage('plugins.generic.codecheck.repositories.githubUnreachable'));
            }
            // Unauthenticated, GitHub answers 404 for a private repository too.
            if ($e->getCode() === 404) {
                throw new \UnexpectedValueException(__('plugins.generic.codecheck.register.repository.githubNotFound', ['repository' => "{$owner}/{$repo}"]));
            }
            return null;
        }

        return is_string($branch) ? (self::$defaultBranches[$key] = $branch) : null;
    }

    /**
     * The register's name for an address, a DOI resolved first as the import
     * resolves it; see `RegisterRepositoryName::for()`, also for the branch
     * GitHub could not compare with the default.
     */
    public function registerRepositoryName(string $repository, bool $acceptUnconfirmedBranch = false): string
    {
        return RegisterRepositoryName::for($this->curlApiClient->resolveDoi($repository), $this->githubDefaultBranch(...), $acceptUnconfirmedBranch);
    }

    /**
     * Import the codecheck metadata from the `codecheck.yml` in a GitHub repository:
     * the file the address names, or the `codecheck.yml` in the folder it names,
     * on the ref it names or else the repository's default branch, which GitHub
     * picks when it is asked for none.
     *
     * @param string $repository The GitHub address
     * @param array $address The address as `GithubRepositoryAddress::parse()` reads it
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromGitHub(string $repository, array $address): JsonResponse
    {
        $filename = $address['file'] ?? 'codecheck.yml';

        try {
            $file = $this->client->api('repo')->contents()->show(
                $address['owner'],
                $address['repo'],
                ltrim($address['path'] . '/' . $filename, '/'),
                $address['ref']
            );
        } catch (\Exception $e) {
            // GitHub answers 404 for a private repository too. Anything else —
            // a rate limit, no answer — is not a missing file, and says so.
            if (GithubHttp::wasUnreachable()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => GithubHttp::unreachableMessage('plugins.generic.codecheck.repositories.githubUnreachable'),
                    'repository' => $repository,
                ], 504);
            }
            if ($e->getCode() !== 404) {
                return self::failure($e, $repository);
            }
            $file = null;
        }

        // A folder of that name comes back as a list, which has no `type`.
        if (($file['type'] ?? null) !== 'file' || !isset($file['content'])) {
            return new JsonResponse([
                'success' => false,
                'error' => "There is no '{$filename}' file in this repository.",
                'repository' => $repository,
            ], 404);
        }

        return $this->yamlResponse(base64_decode($file['content']), $repository);
    }

    /**
     * Import the codecheck metadata from an OSF project: the file the address
     * names, or the `codecheck.yml` at the top of the project's OSF Storage.
     * Either way the download address is the one OSF's API gives for the file,
     * which a file has whether or not OSF has given it a short identifier yet.
     *
     * @param string $repository The OSF address
     * @param array $address The address as `OsfRepositoryAddress::parse()` reads it
     *
     * @return JsonResponse The Metadata from the Repositories `codecheck.yml`
     */
    private function importMetadataFromOSF(string $repository, array $address): JsonResponse
    {
        $filename = 'codecheck.yml';
        $apiUrl = $address['file'] !== null
            ? 'https://api.osf.io/v2/files/' . $address['file'] . '/'
            : 'https://api.osf.io/v2/nodes/' . $address['node'] . '/files/osfstorage/?filter%5Bname%5D=' . $filename;

        try {
            $data = json_decode($this->curlApiClient->fetch($apiUrl), true);
        } catch (\Throwable $e) {
            return self::failure($e, $repository);
        }

        if (!is_array($data['data'] ?? null)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid OSF API response',
                'repository' => $repository,
            ], 500);
        }

        // A file comes back on its own, the project's top folder as a list,
        // whose name filter matches any name containing the one asked for.
        $item = $address['file'] !== null
            ? $data['data']
            : collect($data['data'])->first(fn ($entry) => ($entry['attributes']['name'] ?? null) === $filename && ($entry['attributes']['kind'] ?? null) === 'file');
        $download = $item['links']['download'] ?? null;
        if (($item['attributes']['kind'] ?? null) === 'file' && is_string($download)) {
            return $this->readYamlContent($download, $repository);
        }

        return new JsonResponse([
            'success' => false,
            'error' => "{$filename} not found",
            'repository' => $repository,
        ], 404);
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
        try {
            $yamlContent = $this->curlApiClient->fetch($pathToYamlContent);
        } catch (\Throwable $e) {
            return self::failure($e, $repository);
        }

        return $this->yamlResponse($yamlContent, $repository);
    }

    /**
     * The API response for a fetched `codecheck.yml`: its metadata, or why it
     * does not parse.
     */
    private function yamlResponse(string $yamlContent, string $repository): JsonResponse
    {
        try {
            $metadata = Yaml::parse($yamlContent);
        } catch (\Throwable $e) {
            return self::failure($e, $repository);
        }

        // Everything reading the metadata takes it as a mapping; an empty file
        // or a bare value is not a `codecheck.yml`. The answer is JSON, which
        // has no infinity, no NaN and no bytes outside UTF-8.
        if (!is_array($metadata) || array_is_list($metadata) || json_encode($metadata) === false) {
            return new JsonResponse([
                'success' => false,
                'error' => 'The codecheck.yml in this repository holds no metadata.',
                'repository' => $repository,
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'repository' => $repository,
            'metadata' => $metadata,
        ], 200);
    }

    private static function failure(\Throwable $e, string $repository): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage(),
            'repository' => $repository,
        ], JsonResponse::errorStatus($e));
    }
}
