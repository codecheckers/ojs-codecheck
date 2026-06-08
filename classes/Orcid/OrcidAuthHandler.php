<?php
/**
 * @file classes/Orcid/OrcidAuthHandler.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
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
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use Illuminate\Support\Facades\DB;

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
        $context   = $request->getContext();
        $contextId = $context->getId();

        if (!$this->plugin->getSetting($contextId, Constants::ORCID_ENABLED)) {
            $this->sendPopupError('ORCID integration is not enabled for this journal.');
            return;
        }

        $clientId     = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);

        if (!$clientId || !$clientSecret) {
            $this->sendPopupError(
                'ORCID credentials are not configured. Please ask the journal manager to set ' .
                'the Client ID and Client Secret in the CODECHECK plugin settings.'
            );
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        if (!$submissionId) {
            $this->sendPopupError('Missing submissionId parameter.');
            return;
        }

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
        $error = $request->getUserVar('error');
        if ($error) {
            $desc = $request->getUserVar('error_description') ?? 'Access denied.';
            $this->sendPopupError('ORCID authorisation denied: ' . $desc);
            return;
        }

        $code  = $request->getUserVar('code');
        $state = $request->getUserVar('state');

        if (!$code || !$state) {
            $this->sendPopupError('Invalid ORCID callback: missing code or state.');
            return;
        }

        $stateData    = json_decode(base64_decode($state), true);
        $submissionId = (int) ($stateData['submissionId'] ?? 0);
        $nonce        = $stateData['nonce'] ?? '';
        $contextPath  = $stateData['contextPath'] ?? 'index';

        if (!$submissionId) {
            $this->sendPopupError('Invalid state parameter.');
            return;
        }

        $sessionNonce = $request->getSession()->get('orcid_nonce_' . $submissionId);
        if (!$sessionNonce || !hash_equals($sessionNonce, $nonce)) {
            $this->sendPopupError('Security check failed. Please try again.');
            return;
        }
        $request->getSession()->forget('orcid_nonce_' . $submissionId);

        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            $this->sendPopupError('Submission not found.');
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
            $expiresAt    = null;

            // Verify the authenticated ORCID iD belongs to one of the
            // codecheckers assigned to this submission. If stored ORCIDs
            // exist and none match, reject the authorisation.
            $metadata = DB::table('codecheck_metadata')
                ->where('submission_id', $submissionId)
                ->first();

            if ($metadata && $metadata->codecheckers) {
                $codecheckers = json_decode($metadata->codecheckers, true);
                if (is_array($codecheckers)) {
                    $storedOrcids = array_filter(array_map(
                        fn($cc) => $cc['orcid'] ?? $cc['ORCID'] ?? null,
                        $codecheckers
                    ));

                    if (!empty($storedOrcids) && !in_array($orcidId, $storedOrcids)) {
                        CodecheckLogger::error(
                            'ORCID iD mismatch for submission ' . $submissionId .
                            ': authenticated as ' . $orcidId . ' but not in codechecker list'
                        );
                        $this->sendPopupError(
                            'The ORCID iD you authenticated with (' . $orcidId . ') ' .
                            'does not match any codechecker ORCID on record for this submission. ' .
                            'Please sign in with the correct ORCID account.'
                        );
                        return;
                    }
                }
            }

            $tokenDAO = new OrcidTokenDAO();
            $tokenDAO->upsertToken($submissionId, $orcidId, $accessToken, $refreshToken, $expiresAt);

            CodecheckLogger::info('ORCID token stored for ' . $orcidId . ' / submission ' . $submissionId);

            $workflowUrl = $request->getBaseUrl()
                . '/index.php/' . $contextPath
                . '/dashboard/editorial?workflowSubmissionId=' . $submissionId;

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
            CodecheckLogger::error('ORCID token exchange failed: ' . $e->getMessage());
            $this->sendPopupError('ORCID token exchange failed: ' . $e->getMessage());
        }
    }

    private function buildApiClient(int $contextId): OrcidApiClient
    {
        return new OrcidApiClient(
            $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID),
            $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET),
            $this->plugin->getSetting($contextId, Constants::ORCID_API_TYPE) ?? Constants::ORCID_API_TYPE_SANDBOX
        );
    }

    private function buildRedirectUri($request): string
    {
        return $request->getBaseUrl()
            . '/index.php/index/codecheck/orcid/callback';
    }

    private function sendPopupError(string $message): void
    {
        CodecheckLogger::error('ORCID auth error: ' . $message);
        echo '<html><body>';
        echo '<script>';
        echo 'if (window.opener) {';
        echo '  window.opener.postMessage({ type: "orcidAuthError", message: ' . json_encode($message) . ' }, "*");';
        echo '  window.close();';
        echo '} else {';
        echo '  document.write(' . json_encode(
            '<p>Error: ' . htmlspecialchars($message) . '</p>' .
            '<p><a href="javascript:window.close()">Close this window</a></p>'
        ) . ');';
        echo '}';
        echo '</script>';
        echo '<p>Error: ' . htmlspecialchars($message) . '</p>';
        echo '<p><a href="javascript:window.close()">Close this window</a></p>';
        echo '</body></html>';
        exit;
    }
}