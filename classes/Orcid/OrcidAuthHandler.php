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
 *   /<journal>/codecheck/orcid/startAuth  — redirect the codechecker to ORCID
 *   /<journal>/codecheck/orcid/callback   — receive the code back from ORCID
 *
 * Both are journal-scoped. The callback used to be the site-level
 * `/index/codecheck/orcid/callback`, which no request could reach unless the
 * plugin was also enabled site-wide: `register()` gates its hooks on
 * `getEnabled()`, and with no journal in the request that reads the
 * `context_id IS NULL` setting, which enabling the plugin for a journal never
 * writes. The first leg worked and the return leg was a bare 404 (#176).
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
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\user\User;

class OrcidAuthHandler extends Handler
{
    /**
     * How long a sealed `state` stays usable, in seconds.
     *
     * Long enough to read ORCID's consent screen, short enough that a state
     * that leaks — into a browser history, a referrer, a shared screenshot —
     * stops being useful quickly.
     */
    private const STATE_LIFETIME_SECONDS = 900;

    private CodecheckPlugin $plugin;

    /**
     * Refuse a request that no sealed state and no login speaks for.
     *
     * A page handler that declares no policy is **permitted**: for page routers
     * PKP applies a blacklist, not a whitelist — `PKPHandler::authorize()` sets
     * AUTHORIZATION_PERMIT when no policy applies, "to maintain backwards
     * compatibility". This class declared none, so both of its operations were
     * reachable by an anonymous visitor (GHSA-4p3r-qgp4-g74r), and neither the
     * plugin's CSRF check nor its role check applies here — those live in the
     * API controller, a different entry point entirely.
     *
     * `ContextRequiredPolicy` applies to both routes, which are journal-scoped
     * (#176). `UserRequiredPolicy` applies to `startAuth` only: the callback is
     * ORCID's request, not the codechecker's, and it carries its own authority
     * in the sealed `state` — see `flowFromState()`. Requiring a session there
     * would lose an authorisation whenever one lapsed at ORCID's consent
     * screen, and would say nothing about who started the flow anyway.
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request), true);

        if (($request->getRequestedArgs()[0] ?? '') === 'startAuth') {
            $this->addPolicy(new UserRequiredPolicy($request), true);
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Seal who started this flow, for what, and when, into the `state`.
     *
     * `Crypt` is Laravel's authenticated encryption under OJS's `app_key`, so
     * the value is neither readable nor alterable by the person carrying it —
     * which is what lets the callback take the acting user from here instead of
     * from whatever session happens to be open in the browser that returns.
     */
    private function sealState(int $submissionId, int $userId): string
    {
        return Crypt::encryptString(json_encode([
            'submissionId' => $submissionId,
            'userId'       => $userId,
            'issuedAt'     => time(),
        ]));
    }

    /**
     * The flow a `state` stands for, or null when it stands for nothing.
     *
     * Returns `['submissionId' => int, 'userId' => int]`. A state that was not
     * minted here fails to decrypt; one that has expired is refused. Both send
     * the same popup error, because neither tells the person anything they can
     * act on beyond starting again.
     */
    private function flowFromState(string $state): ?array
    {
        try {
            $flow = json_decode(Crypt::decryptString($state), true);
        } catch (\Throwable $e) {
            CodecheckLogger::warning('ORCID callback with an unreadable state parameter');
            return null;
        }

        if (!is_array($flow)) {
            return null;
        }

        $submissionId = (int) ($flow['submissionId'] ?? 0);
        $userId       = (int) ($flow['userId'] ?? 0);
        $issuedAt     = (int) ($flow['issuedAt'] ?? 0);

        if (!$submissionId || !$userId || !$issuedAt) {
            return null;
        }

        if (time() - $issuedAt > self::STATE_LIFETIME_SECONDS) {
            CodecheckLogger::info('ORCID callback with an expired state for submission ' . $submissionId);
            return null;
        }

        return ['submissionId' => $submissionId, 'userId' => $userId];
    }

    /**
     * The submission this user may act on, or null when they may not.
     *
     * On `startAuth` the user is the one asking and the submission id is theirs
     * to type, so this is what stops them typing someone else's. On `callback`
     * both come from the sealed state, and this re-asks the question because a
     * role can be withdrawn while its holder is at ORCID's consent screen.
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
    private function submissionUserMayActOn(?User $user, int $submissionId, $request): ?Submission
    {
        // Two-argument get(): the submission has to belong to the journal whose
        // URL this was reached through, or one journal's editor could authorise
        // against another's submission by keeping their own path in the URL.
        // ContextRequiredPolicy guarantees there is a journal to ask about.
        $contextId  = (int) $request->getContext()->getId();
        $submission = Repo::submission()->get($submissionId, $contextId);

        if (!$submission) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.submissionNotFound'));
            return null;
        }

        if (!CodecheckSubmissionAccess::canWriteMetadata($user, $submissionId, $contextId)) {
            CodecheckLogger::warning(
                'ORCID authorisation refused: user ' . ($user?->getId() ?? '?')
                . ' may not act on submission ' . $submissionId
            );
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.notPermitted'));
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
     * GET /<journal>/codecheck/orcid/startAuth?submissionId=XX
     * Redirects the codechecker to ORCID's consent screen.
     */
    public function startAuth($args, $request): void
    {
        $context   = $request->getContext();
        $contextId = $context->getId();

        $submissionId = (int) $request->getUserVar('submissionId');
        if (!$submissionId) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.missingSubmissionId'));
            return;
        }

        // Before the configuration checks below, not after: whether this journal
        // has ORCID set up is not something to tell a caller who may not act on
        // the submission in the first place.
        if (!$this->submissionUserMayActOn($request->getUser(), $submissionId, $request)) {
            return;
        }

        if (!$this->plugin->getSetting($contextId, Constants::ORCID_ENABLED)) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.notEnabled'));
            return;
        }

        $clientId     = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_ID);
        $clientSecret = $this->plugin->getSetting($contextId, Constants::ORCID_CLIENT_SECRET);

        if (!$clientId || !$clientSecret) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.noCredentials'));
            return;
        }

        // Sealed rather than stored in the session: the authorisation should
        // survive a session that lapses while its owner is at ORCID, and the
        // journal is not carried at all — the callback is journal-scoped now,
        // and a caller-supplied path used to feed a redirect.
        $state = $this->sealState($submissionId, (int) $request->getUser()->getId());

        $client      = $this->buildApiClient($contextId);
        $redirectUri = $this->buildRedirectUri($request);
        $authUrl     = $client->buildAuthorizationUrl($redirectUri, $state);

        $request->redirectUrl($authUrl);
    }

    /**
     * GET /<journal>/codecheck/orcid/callback?code=XX&state=YY
     * ORCID redirects here after the codechecker grants access.
     */
    public function callback($args, $request): void
    {
        $error = $request->getUserVar('error');
        if ($error) {
            $desc = $request->getUserVar('error_description') ?? __('plugins.generic.codecheck.orcid.auth.error.accessDenied');
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.denied', ['error' => $desc]));
            return;
        }

        $code  = $request->getUserVar('code');
        $state = $request->getUserVar('state');

        if (!$code || !$state) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.invalidCallback'));
            return;
        }

        // Everything this request is allowed to do comes from here. The person
        // browsing is ORCID's redirect target, not necessarily the codechecker,
        // and their session is never consulted.
        $flow = $this->flowFromState($state);
        if (!$flow) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.securityCheck'));
            return;
        }

        $submissionId = $flow['submissionId'];
        $actingUser   = Repo::user()->get($flow['userId']);

        if (!$actingUser) {
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.initiatorGone'));
            return;
        }

        $submission = $this->submissionUserMayActOn($actingUser, $submissionId, $request);
        if (!$submission) {
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
                        $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.orcidMismatch', ['orcidId' => $orcidId]));
                        return;
                    }
                }
            }

            $tokenDAO = new OrcidTokenDAO();
            $tokenDAO->upsertToken($submissionId, $orcidId, $accessToken, $refreshToken, $expiresAt);

            CodecheckLogger::info('ORCID token stored for ' . $orcidId . ' / submission ' . $submissionId);

            $workflowUrl = $request->getBaseUrl()
                . '/index.php/' . $request->getContext()->getPath()
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
            $this->sendPopupError(__('plugins.generic.codecheck.orcid.auth.error.tokenExchange', ['error' => $e->getMessage()]));
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

    /**
     * Where ORCID sends the codechecker back.
     *
     * Journal-scoped, so the plugin is enabled for the request that arrives
     * (#176). ORCID matches a registered redirect URI by prefix, so one
     * registration of the installation's base URL covers every journal.
     */
    private function buildRedirectUri($request): string
    {
        return $request->getBaseUrl()
            . '/index.php/' . $request->getContext()->getPath()
            . '/codecheck/orcid/callback';
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