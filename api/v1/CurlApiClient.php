<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;

/**
 * Fetches a repository's `codecheck.yml` and resolves DOIs to the repository
 * they name.
 *
 * Through OJS's own HTTP client, so its `[proxy]` setting applies (#65); the
 * name is from when this was a cURL client, kept for its callers.
 */
class CurlApiClient implements ApiClientInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; Codecheck/1.0; +https://codecheck.org.uk)';

    /** As many as cURL followed here; repository downloads pass through CDNs. */
    private const MAX_REDIRECTS = 10;

    /**
     * DOIs resolved in this request: a publish asks for the same one up to
     * four times. Only a resolution is remembered, so a failure is tried again.
     */
    private static array $resolvedDois = [];

    /** OJS's client, built on first use: building it reads the version from the database. */
    public function __construct(private ?Client $client = null)
    {
    }

    private function client(): Client
    {
        return $this->client ??= Application::get()->getHttpClient();
    }

    /**
     * @throws CurlHttpException carrying the answer's status, or 504 when the
     *   host did not answer and 502 when the request failed otherwise
     */
    public function fetch(string $url): string
    {
        try {
            $response = $this->client()->request('GET', $url, [
                // A repository host that never answers must not hold the editor's
                // request, or a publication, for as long as PHP lets it run.
                RequestOptions::CONNECT_TIMEOUT => GithubHttp::CONNECT_TIMEOUT_SECONDS,
                RequestOptions::TIMEOUT => GithubHttp::TIMEOUT_SECONDS,
                RequestOptions::ALLOW_REDIRECTS => ['max' => self::MAX_REDIRECTS],
                RequestOptions::HEADERS => ['User-Agent' => self::USER_AGENT, 'Accept' => '*/*'],
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (ConnectException $e) {
            // Unreachable or too slow: the plugin's word for that is 504, as for GitHub.
            throw new CurlHttpException("{$url} did not answer: " . $e->getMessage(), 504, $e);
        } catch (\Throwable $e) {
            throw new CurlHttpException("Request to {$url} failed: " . $e->getMessage(), 502, $e);
        }

        $httpCode = $response->getStatusCode();
        if ($httpCode >= 400) {
            throw new CurlHttpException("Request to {$url} failed with HTTP status {$httpCode}.", $httpCode);
        }

        return (string) $response->getBody();
    }

    /**
     * If the given URL is a DOI link (doi.org or dx.doi.org), resolve it to its
     * final destination URL by following redirects. Non-DOI URLs are returned unchanged.
     * If resolution fails for any reason, the original URL is returned.
     */
    public function resolveDoi(string $possibleDoiUrl): string
    {
        if (!preg_match('#^(?:(?:https?://)?(?:dx\.)?doi\.org/)?10\.\d{4,9}/.+$#i', $possibleDoiUrl)) {
            return $possibleDoiUrl;
        }

        // Normalize into a fully-qualified URL, regardless of what scheme/host
        // form the input came in as (bare DOI, doi.org/..., etc.)
        $doi = preg_replace('#^(?:https?://)?(?:dx\.)?(?:doi\.org/)?#i', '', $possibleDoiUrl);
        $url = 'https://doi.org/' . $doi;
        if (isset(self::$resolvedDois[$doi])) {
            return self::$resolvedDois[$doi];
        }

        $effectiveUrl = null;
        try {
            $response = $this->client()->request('GET', $url, [
                RequestOptions::ALLOW_REDIRECTS => ['max' => self::MAX_REDIRECTS],
                RequestOptions::CONNECT_TIMEOUT => GithubHttp::CONNECT_TIMEOUT_SECONDS,
                RequestOptions::TIMEOUT => GithubHttp::TIMEOUT_SECONDS,
                RequestOptions::HEADERS => ['User-Agent' => self::USER_AGENT],
                RequestOptions::HTTP_ERRORS => false,
                // Only where it ends up is wanted, not the landing page itself.
                RequestOptions::STREAM => true,
                RequestOptions::ON_STATS => function (TransferStats $stats) use (&$effectiveUrl) {
                    $effectiveUrl = (string) $stats->getEffectiveUri();
                },
            ]);
            $response->getBody()->close();
        } catch (\Throwable $e) {
            return $possibleDoiUrl;
        }

        if (!$effectiveUrl) {
            return $possibleDoiUrl;
        }

        return self::$resolvedDois[$doi] = $effectiveUrl;
    }
}
