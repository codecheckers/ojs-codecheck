<?php
/**
 * @file classes/Orcid/OrcidApiClient.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidApiClient
 * @brief Handles all HTTP communication with the ORCID Member API.
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\plugins\generic\codecheck\classes\Constants;

class OrcidApiClient
{
    private string $clientId;
    private string $clientSecret;
    private string $orcidBaseUrl;
    private string $orcidApiUrl;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $apiType = Constants::ORCID_API_TYPE_SANDBOX
    ) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;

        if ($apiType === Constants::ORCID_API_TYPE_PRODUCTION) {
            $this->orcidBaseUrl = Constants::ORCID_URL_PRODUCTION;
            $this->orcidApiUrl  = Constants::ORCID_API_URL_PRODUCTION;
        } else {
            $this->orcidBaseUrl = Constants::ORCID_URL_SANDBOX;
            $this->orcidApiUrl  = Constants::ORCID_API_URL_SANDBOX;
        }
    }

    /**
     * Build the ORCID OAuth authorisation URL the codechecker must visit.
     */
    public function buildAuthorizationUrl(string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'scope'         => Constants::ORCID_ACTIVITIES_SCOPE,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
        ]);

        return $this->orcidBaseUrl . '/oauth/authorize?' . $params;
    }

    /**
     * Exchange the short-lived authorisation code for an access token.
     *
     * @throws \RuntimeException on failure
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $tokenUrl = $this->orcidBaseUrl . '/oauth/token';

        $postFields = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ]);

        $response = $this->httpPost($tokenUrl, $postFields, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $data = json_decode($response['body'], true);

        if (empty($data['access_token'])) {
            throw new \RuntimeException(
                'ORCID token exchange failed: ' . ($data['error_description'] ?? $response['body'])
            );
        }

        return $data;
    }

    /**
     * Get a client credentials token (2-legged OAuth) for group-id management.
     * This uses the client ID + secret directly, no user involvement needed.
     *
     * @throws \RuntimeException on failure
     */
    public function getClientCredentialsToken(): string
    {
        $tokenUrl = $this->orcidBaseUrl . '/oauth/token';

        $postFields = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
            'scope'         => '/group-id-record/update',
        ]);

        $response = $this->httpPost($tokenUrl, $postFields, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $data = json_decode($response['body'], true);

        if (empty($data['access_token'])) {
            throw new \RuntimeException(
                'ORCID client credentials token failed: ' . ($data['error_description'] ?? $response['body'])
            );
        }

        return $data['access_token'];
    }

    /**
     * Create a group-id record in ORCID using client credentials.
     * The group-id must exist before peer-reviews can reference it.
     *
     * Returns the put-code of the created group, or null if it already exists.
     *
     * @throws \RuntimeException on failure
     */
    public function createGroupId(string $groupId, string $groupName, string $groupType = 'journal'): ?string
    {
        $clientToken = $this->getClientCredentialsToken();

        $payload = json_encode([
            'name'        => $groupName,
            'group-id'    => $groupId,
            'description' => 'CODECHECK peer-review group for ' . $groupName,
            'type'        => $groupType,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = $this->orcidApiUrl . '/group-id-record';

        error_log('[CODECHECK ORCID] Creating group-id: ' . $groupId);

        $response = $this->httpPost($url, $payload, [
            'Accept: application/vnd.orcid+json',
            'Content-Type: application/vnd.orcid+json',
            'Authorization: Bearer ' . $clientToken,
        ]);

        // 201 = created, 409 = already exists (both are fine)
        if ($response['status'] === 201) {
            $putCode = $this->extractPutCodeFromLocation($response['headers']['location'] ?? '');
            error_log('[CODECHECK ORCID] Group-id created with put-code: ' . $putCode);
            return $putCode;
        }

        if ($response['status'] === 409) {
            error_log('[CODECHECK ORCID] Group-id already exists: ' . $groupId);
            return null; // already exists, that is fine
        }

        throw new \RuntimeException(
            'ORCID group-id creation failed (HTTP ' . $response['status'] . '): ' . $response['body']
        );
    }

    /**
     * POST a peer-review item to the codechecker's ORCID record.
     * Returns the put-code string on success.
     *
     * @throws \RuntimeException on failure
     */
    public function postPeerReview(string $orcidId, string $accessToken, array $payload): string
    {
        $url  = $this->orcidApiUrl . '/' . $orcidId . '/peer-review';
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        error_log('[CODECHECK ORCID] POST peer-review to: ' . $url);
        error_log('[CODECHECK ORCID] Payload: ' . $json);

        $response = $this->httpPost($url, $json, [
            'Accept: application/vnd.orcid+json',
            'Content-Type: application/vnd.orcid+json',
            'Authorization: Bearer ' . $accessToken,
        ]);

        if ($response['status'] === 409) {
            // Already exists — extract the existing put-code and treat as success
            $body = json_decode($response['body'], true);
            $msg = $body['developer-message'] ?? '';
            if (preg_match('/put-code\s+(\d+)/', $msg, $matches)) {
                return $matches[1];
            }
        }

        if ($response['status'] !== 201) {
            throw new \RuntimeException(
                'ORCID peer-review POST failed (HTTP ' . $response['status'] . '): ' . $response['body']
            );
        }

        $putCode = $this->extractPutCodeFromLocation($response['headers']['location'] ?? '');

        if (!$putCode) {
            throw new \RuntimeException('ORCID peer-review POST succeeded but put-code could not be parsed.');
        }

        return $putCode;
    }

    /**
     * PUT an updated peer-review item (re-deposit using existing put-code).
     *
     * @throws \RuntimeException on failure
     */
    public function putPeerReview(string $orcidId, string $accessToken, string $putCode, array $payload): void
    {
        $payload['put-code'] = (int) $putCode;

        $url  = $this->orcidApiUrl . '/' . $orcidId . '/peer-review/' . $putCode;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->httpPut($url, $json, [
            'Accept: application/vnd.orcid+json',
            'Content-Type: application/vnd.orcid+json',
            'Authorization: Bearer ' . $accessToken,
        ]);

        if ($response['status'] !== 200) {
            throw new \RuntimeException(
                'ORCID peer-review PUT failed (HTTP ' . $response['status'] . '): ' . $response['body']
            );
        }
    }

    // ------------------------------------------------------------------
    // Internal HTTP helpers
    // ------------------------------------------------------------------

    private function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        return $this->executeAndParse($ch);
    }

    private function httpPut(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        return $this->executeAndParse($ch);
    }

    private function executeAndParse($ch): array
    {
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        // Use strrpos to find the LAST \r\n\r\n — handles redirect responses correctly
        $headerSize = strrpos($raw, "\r\n\r\n");
        $rawHeaders = substr($raw, 0, $headerSize);
        $body       = substr($raw, $headerSize + 4);

        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    private function extractPutCodeFromLocation(string $location): ?string
    {
        if (empty($location)) {
            return null;
        }
        $parts = explode('/', rtrim($location, '/'));
        $last  = end($parts);
        return is_numeric($last) ? $last : null;
    }
}