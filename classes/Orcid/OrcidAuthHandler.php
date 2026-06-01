<?php
/**
 * @file classes/Orcid/OrcidAuthHandler.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidAuthHandler
 * @brief Handles the OAuth 2.0 flow for codechecker ORCID authorisation.
 *
 * Two routes (wired in CodecheckPlugin::setCodecheckPageHandler):
 *   /codecheck/orcid/startAuth  — redirect the codechecker to ORCID
 *   /codecheck/orcid/callback   — receive the code back from ORCID
 */

namespace APP\plugins\generic\codecheck\classes\Orcid;

use APP\handler\Handler;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class OrcidAuthHandler extends Handler
{
    private CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    /**
     * GET /codecheck/orcid/startAuth?submissionId=XX
     * Redirects the codechecker to ORCID's consent screen.
     */
    public function startAuth($args, $request): void
    {
        $context = $request->getContext();
        $contextId = $context->getId();

        if (!$this->plugin->getSetting($contextId, Constants::ORCID_ENABLED)) {
            $this->showError('ORCID integration is not enabled for this journal.');
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        if (!$submissionId) {
            $this->showError('Missing submissionId parameter.');
            return;
        }

        // Generate a nonce and store it in the session for CSRF validation
        $nonce = bin2hex(random_bytes(16));
        $request->getSession()->put('orcid_nonce_' . $submissionId, $nonce);

        $state = base64_encode(json_encode([
            'submissionId' => $submissionId,
            'nonce'        => $nonce,
            'contextPath'  => $context->getPath(),
        ]));

        $client      = $this->buildApiClient($contextId);
        $redirectUri = $this->buildRedirectUri($request);
        $authUrl     = $client->buildAuthorizationUrl($redirectUri, $state);

        $request->redirectUrl($authUrl);
    }

    /**
     * GET /codecheck/orcid/callback?code=XX&state=YY
     * ORCID redirects here after the codechecker grants access.
     */
    public function callback($args, $request): void
    {
        // Handle denial
        $error = $request->getUserVar('error');
        if ($error) {
            $desc = $request->getUserVar('error_description') ?? 'Access denied.';
            $this->showError('ORCID authorisation denied: ' . $desc);
            return;
        }

        $code  = $request->getUserVar('code');
        $state = $request->getUserVar('state');

        if (!$code || !$state) {
            $this->showError('Invalid ORCID callback: missing code or state.');
            return;
        }

        $stateData    = json_decode(base64_decode($state), true);
        $submissionId = (int) ($stateData['submissionId'] ?? 0);
        $nonce        = $stateData['nonce'] ?? '';
        $contextPath  = $stateData['contextPath'] ?? 'index';

        if (!$submissionId) {
            $this->showError('Invalid state parameter.');
            return;
        }

        // Validate nonce (CSRF protection)
        $sessionNonce = $request->getSession()->get('orcid_nonce_' . $submissionId);
        if (!$sessionNonce || !hash_equals($sessionNonce, $nonce)) {
            $this->showError('Security check failed. Please try again.');
            return;
        }
        $request->getSession()->forget('orcid_nonce_' . $submissionId);

        // Get contextId from the submission — context may be null on the
        // callback URL since it routes through /index/ not the journal path
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            $this->showError('Submission not found.');
            return;
        }
        $contextId = $submission->getData('contextId');

        try {
            $client      = $this->buildApiClient($contextId);
            $redirectUri = $this->buildRedirectUri($request);
            $tokenData   = $client->exchangeCodeForToken($code, $redirectUri);

            $orcidId      = $tokenData['orcid'];
            $accessToken  = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            // ORCID Member API tokens last ~20 years, no need to store expiry
            $expiresAt = null;

            $tokenDAO = new OrcidTokenDAO();
            $tokenDAO->upsertToken($submissionId, $orcidId, $accessToken, $refreshToken, $expiresAt);

            error_log('[CODECHECK ORCID] Token stored for ' . $orcidId . ' / submission ' . $submissionId);

            // Build workflow URL using the context path we stored in state
            $workflowUrl = $request->getBaseUrl()
                . '/index.php/' . $contextPath
                . '/dashboard/editorial?workflowSubmissionId=' . $submissionId;

            // Close the popup and notify the parent window
            echo '<html><body>';
            echo '<script>';
            echo 'if (window.opener) {';
            echo '  window.opener.postMessage({ type: "orcidAuthSuccess", orcidId: ' . json_encode($orcidId) . ' }, "*");';
            echo '  window.close();';
            echo '} else {';
            echo '  window.location = ' . json_encode($workflowUrl) . ';';
            echo '}';
            echo '</script>';
            echo '<p>Authorisation successful. You may close this window.</p>';
            echo '</body></html>';

        } catch (\Throwable $e) {
            error_log('[CODECHECK ORCID] Token exchange failed: ' . $e->getMessage());
            $this->showError('ORCID token exchange failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildApiClient(int $contextId): OrcidApiClient
    {
        return new OrcidApiClient(
            $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID),
            $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET),
            $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE) ?? Constants::ORCID_API_TYPE_SANDBOX
        );
    }

    /**
     * Build the redirect URI — must match exactly what is registered
     * in the ORCID sandbox developer tools:
     * http://localhost:8888/ojs/index.php/index/codecheck/orcid/callback
     */
    private function buildRedirectUri($request): string
    {
        return $request->getBaseUrl()
            . '/index.php/index/codecheck/orcid/callback';
    }

    private function showError(string $message): void
    {
        error_log('[CODECHECK ORCID] Auth error: ' . $message);
        echo '<html><body>';
        echo '<p>Error: ' . htmlspecialchars($message) . '</p>';
        echo '<p><a href="javascript:window.close()">Close this window</a></p>';
        echo '</body></html>';
        exit;
    }
}