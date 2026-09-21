<?php
/**
 * @file classes/Orcid/OrcidAuthHandler.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
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
use APP\submission\Submission;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use APP\plugins\generic\codecheck\classes\Submission\CodecheckSubmissionAccess;
use PKP\security\authorization\UserRequiredPolicy;
use Illuminate\Support\Facades\DB;

class OrcidAuthHandler extends Handler
{
    private CodecheckPlugin $plugin;

    /**
     * Refuse anyone who is not logged in (GHSA-4p3r-qgp4-g74r).
     *
     * A page handler that declares no policy is **permitted**: for page routers
     * PKP applies a blacklist, not a whitelist — `PKPHandler::authorize()` sets
     * AUTHORIZATION_PERMIT when no policy applies, "to maintain backwards
     * compatibility". This class declared none, so both of its operations were
     * reachable by an anonymous visitor, and neither the plugin's CSRF check nor
     * its role check applies here — those live in the API controller, a
     * different entry point entirely.
     *
     * Only `UserRequiredPolicy` is declared, deliberately: ORCID sends the
     * codechecker back to `/index/codecheck/orcid/callback`, a site-level URL
     * with no journal context, so a `ContextRequiredPolicy` would refuse every
     * real return from ORCID. The journal is taken from the submission instead,
     * in `submissionCallerMayActOn()`.
     *
     * Being logged in is only half of it; `submissionCallerMayActOn()` does the rest.
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRequiredPolicy($request), true);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * The submission this caller may act on, or null when they may not.
     *
     * The submission id arrives in the query string and, on the way back, in the
     * state parameter. The nonce proves the flow started in this browser; it
     * says nothing about whether this browser should be starting it for *this*
     * submission. Without this an authenticated user could bind their ORCID to
     * any submission id they cared to type.
     *
     * Same rule as the API: an editor for any submission in the journal, a
     * reviewer only for the one they are assigned to. Returning the submission
     * rather than a bool saves the caller fetching it a second time.
     *
     * Null means refused, with the popup error already sent — and since
     * `sendPopupError()` ends in `exit`, that return never actually happens.
     * The callers still guard on it, as every error path in this file does, so
     * that dropping the `exit` does not silently change the control flow.
     */
    private function submissionCallerMayActOn(int $submissionId, $request): ?Submission
    {
        $submission     = Repo::submission()->get($submissionId);
        $requestContext = $request->getContext();

        // Reached through a journal's own URL, the submission has to be that
        // journal's: otherwise one journal's editor could authorise against
        // another's submission by keeping their own path in the URL. The
        // callback carries no journal at all, which is why this is conditional.
        if (!$submission
            || ($requestContext && (int) $requestContext->getId() !== (int) $submission->getData('contextId'))
        ) {
            $this->sendPopupError('Submission not found.');
            return null;
        }

        $contextId = (int) $submission->getData('contextId');

        if (!CodecheckSubmissionAccess::canWriteMetadata($request->getUser(), $submissionId, $contextId)) {
            CodecheckLogger::warning(
                'ORCID authorisation refused: user ' . ($request->getUser()?->getId() ?? '?')
                . ' may not act on submission ' . $submissionId
            );
            $this->sendPopupError(
                'Only an editor, or the reviewer assigned to this submission, may connect an ORCID account to it.'
            );
            return null;
        }

        return $submission;
    }

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
        if (!$context) {
            // No policy enforces this any more — see authorize().
            $this->sendPopupError('ORCID authorisation must be started from within a journal.');
            return;
        }
        $contextId = $context->getId();

        $submissionId = (int) $request->getUserVar('submissionId');
        if (!$submissionId) {
            $this->sendPopupError('Missing submissionId parameter.');
            return;
        }

        // Before the configuration checks below, not after: whether this journal
        // has ORCID set up is not something to tell a caller who may not act on
        // the submission in the first place.
        if (!$this->submissionCallerMayActOn($submissionId, $request)) {
            return;
        }

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
        // Checked again on the way back, not just on the way out: the state
        // parameter is the caller's to shape, so the submission it names has to
        // be re-authorised rather than trusted because startAuth once was. In
        // practice the nonce above already implies it — only an authorised
        // `startAuth` can put one in this session — so this is the second lock
        // rather than the one holding the door, and it catches the case where
        // the assignment was withdrawn while the user was at ORCID's consent
        // screen.
        $submission = $this->submissionCallerMayActOn($submissionId, $request);
        if (!$submission) {
            return;
        }

        // Burnt only now: a refusal above should not cost the user their one
        // nonce and force them through startAuth again.
        $request->getSession()->forget('orcid_nonce_' . $submissionId);
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