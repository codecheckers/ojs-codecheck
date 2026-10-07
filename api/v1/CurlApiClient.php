<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;

/**
 * Fetches a repository's `codecheck.yml` and resolves DOIs to the repository
 * they name.
 *
 * Through Laravel's HTTP client, which OJS ships, with the journal's `[proxy]`
 * setting applied (#65); tests answer by address with `Factory::fake()` (#191).
 * The name is from when this was a cURL client, kept for its callers.
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

    public function __construct(private Factory $http = new Factory())
    {
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->withUserAgent(self::USER_AGENT)
            // Laravel sends every request as JSON; a GET has no body to describe.
            ->withRequestMiddleware(fn (RequestInterface $request) => $request->withoutHeader('Content-Type'))
            // A repository host that never answers must not hold the editor's
            // request, or a publication, for as long as PHP lets it run.
            ->withOptions(GithubHttp::transferOptions() + [
                'allow_redirects' => ['max' => self::MAX_REDIRECTS, 'strict' => true],
            ]);
    }

    /**
     * @throws CurlHttpException carrying the answer's status, or 504 when the
     *   host did not answer and 502 when the request failed otherwise
     */
    public function fetch(string $url): string
    {
        try {
            $response = $this->request()->accept('*/*')->get($url);
        } catch (\Throwable $e) {
            // Laravel calls every transfer failure a connection failure; only a
            // host that did not answer is the plugin's 504, as for GitHub — a
            // redirect loop or anything else is a 502.
            $unanswered = $e instanceof ConnectionException
                && ($e->getPrevious() === null || $e->getPrevious() instanceof ConnectException);
            if ($unanswered) {
                throw new CurlHttpException("{$url} did not answer: " . $e->getMessage(), 504, $e);
            }
            throw new CurlHttpException("Request to {$url} failed: " . $e->getMessage(), 502, $e);
        }

        $httpCode = $response->status();
        if ($httpCode >= 400) {
            throw new CurlHttpException("Request to {$url} failed with HTTP status {$httpCode}.", $httpCode);
        }

        return $response->body();
    }

    /**
     * If the given address names a DOI (see `Constants::bareDoi()`), resolve it
     * to its final destination URL by following redirects. Anything else is
     * returned unchanged. If resolution fails for any reason, the original
     * address is returned.
     */
    public function resolveDoi(string $possibleDoiUrl): string
    {
        $doi = Constants::bareDoi($possibleDoiUrl);
        if ($doi === null) {
            return $possibleDoiUrl;
        }

        $url = 'https://doi.org/' . $doi;
        if (isset(self::$resolvedDois[$doi])) {
            return self::$resolvedDois[$doi];
        }

        try {
            // Only where it ends up is wanted, not the landing page itself. The
            // body is not streamed: Guzzle's stream handler would pin the TLS
            // version Laravel asks for to exactly 1.2. A hop to plain http is not followed.
            $effectiveUrl = (string) $this->request()
                ->accept('*/*')
                ->withOptions(['allow_redirects' => ['max' => self::MAX_REDIRECTS, 'strict' => true, 'protocols' => ['https']]])
                ->get($url)
                ->effectiveUri();
        } catch (\Throwable $e) {
            return $possibleDoiUrl;
        }

        // Resolved once the redirect leaves doi.org, whatever the landing page
        // then answers: Zenodo refuses a crawler now and then.
        if (!$effectiveUrl || preg_match('#^https?://(?:dx\.)?doi\.org/#i', $effectiveUrl)) {
            return $possibleDoiUrl;
        }

        return self::$resolvedDois[$doi] = $effectiveUrl;
    }
}
