# Live ORCID tests

`make test-orcid-live` drives the real ORCID **sandbox**: it authenticates a
sandbox user, completes the OAuth round trip, and deposits the CODECHECK as a
peer-review activity on that user's sandbox record.

**It leaves data behind.** The deposit is a real write and nothing here deletes
it; each run adds another item. That is why it runs against a sandbox account
created for the purpose, never a real one.

The spec is `cypress/tests/live/orcid-deposit.cy.js`, outside `specPattern`
(`cypress/tests/e2e/**`) and refusing to run without `CYPRESS_live=1`, so it is
never picked up by `make test-e2e` or by CI.

## Status — 2026-09-21

**Blocked, waiting on ORCID staff.** The credentials in `.env` are *Public* API
sandbox credentials, and the deposit needs the Member API: the plugin requests
the `/activities/update` scope, which
[ORCID documents](https://info.orcid.org/documentation/integration-guide/registering-a-member-api-client/)
as Member-only. A request for sandbox Member API credentials has been submitted
via <https://info.orcid.org/register-a-client-application-sandbox-member-api/>.

With the Public credentials, step 1 below should pass and steps 2 and 3 cannot:
ORCID refuses the scope at the consent screen. Step 1 passing therefore proves
very little — do not read it as the integration working.

## What it covers

1. **The credentials** — `GET orcid-test`, which asks ORCID for a client
   credentials token. Writes nothing.
2. **The OAuth round trip** — consent on `sandbox.orcid.org` and back, driven
   through `cy.origin()`. This is the only way to obtain a user token, and the
   only test of the sealed `state` against a real redirect.
3. **The deposit** — `POST orcid-deposit`, then reading the record back through
   ORCID's *public* sandbox API (`pub.sandbox.orcid.org`) and asserting that the
   put-code the plugin was handed is really on the record. The assertion is what
   ORCID stored, not what the plugin reported.

Steps 2 and 3 skip themselves when `ORCID_TEST_USER_PASSWORD` or
`ORCID_TEST_USER_ID` are blank.

## Setup

### 1. Credentials in `.env`

`.env` is gitignored, mode 600, and read by phpdotenv — the same parser the
plugin uses, so there is no second interpretation of quoting. Fill in:

```
ORCID_CLIENT_ID=
ORCID_CLIENT_SECRET=
ORCID_API_TYPE=memberSandbox
ORCID_TEST_USER_EMAIL=chekhovbot-dev@mailinator.com
ORCID_TEST_USER_PASSWORD=
ORCID_TEST_USER_ID=
```

`make db-credentials` writes them into `plugin_settings`. It runs as part of
`make db-load`, so `make db-reset` puts them back by itself — the database can
be rebuilt from scratch at any time without re-typing a secret.
`make db-credentials-clear` takes them out again.

### 2. The redirect URI, and why it is not localhost

**ORCID's registration form refuses `localhost` as a redirect URI host** —
`http://localhost:8350` and `https://localhost:8443/...` are both rejected as
"invalid redirect URL". It wants something shaped like a domain.

`*.lvh.me` resolves to 127.0.0.1 publicly, with no hosts file and no DNS of your
own. This is registered and confirmed accepted by the sandbox developer tools:

```
http://codecheck.lvh.me:8350/
```

Per ORCID's [redirect URI FAQ](https://info.orcid.org/ufaqs/how-do-redirect-uris-work/),
a URI registered as just the host covers every path under it, so that one entry
covers the plugin's callback:

```
http://codecheck.lvh.me:8350/index.php/codecheck/codecheck/orcid/callback
```

Note the doubled `codecheck`: the first is the journal path, the second the
plugin's page. It changed with issue #176 — it used to be
`/index.php/index/codecheck/orcid/callback`, and a client registered with the
old value is refused at the last step.

**Reach OJS by the registered name.** `PKPRequest::getBaseUrl()` builds the URL
from the request's Host header and scheme; it only falls back to `base_url` in
`config.inc.php` when host auto-detection fails, which is to say on the command
line. Browsing as `localhost` therefore sends a redirect URI that is not the
registered one. `make test-orcid-live` sets the base URL accordingly.

### 3. HTTPS, if it turns out to be needed

ORCID's pages disagree: the [FAQ](https://info.orcid.org/ufaqs/how-do-redirect-uris-work/)
and the API tutorial say "only HTTPS URIs are accepted in production. You can
test using HTTP URIs", while the
[member client registration page](https://info.orcid.org/documentation/integration-guide/registering-a-member-api-client/)
says "Only HTTPS URIs are accepted" with no carve-out. The sandbox developer
tools accepted plain HTTP. The Member API form may not.

If HTTPS is required, two terminals:

```
make serve-https     # php -S announcing HTTPS (dev/https-router.php)
make serve-tls       # socat TLS front-end on :8443, self-signed certificate
```

and register

```
https://codecheck.lvh.me:8443/index.php/codecheck/codecheck/orcid/callback
```

`make serve-tls TLS_HOST=some.other.name` regenerates the certificate for a
different name. The certificate is self-signed, so the browser asks once.

`dev/https-router.php` exists because `PKPRequest::getProtocol()` reads
`$_SERVER['HTTPS']` and nothing else — no `X-Forwarded-Proto` — so behind a TLS
terminator OJS announces itself as http and hands ORCID an http redirect URI.
Apache would be told with `SetEnv HTTPS on`; the built-in server has no such
thing, and an `HTTPS=on` environment variable does not reach `$_SERVER`.

### 4. The journal must be enabled

The test dataset ships the journal with `enabled = 0`, and `PKPPageRouter`
bounces a logged-out visitor from a disabled journal to the login page *before*
`LoadHandler` runs. The callback deliberately does not require a session — its
authority is the sealed `state` — so a disabled journal turns a genuine ORCID
return into a login redirect whenever the session has lapsed.

```sql
UPDATE journals SET enabled = 1 WHERE journal_id = 1;
```

then `make clear-cache`. Remember to put it back if other specs depend on the
dataset as shipped.

## Running it

```
make serve                      # in one terminal
make test-orcid-live            # in another
```

Override the submission with `make test-orcid-live LIVE_ORCID_SUBMISSION=9`,
and the origin with `LIVE_ORCID_BASE_URL=...`.

## Reading the result

The deposit's put-code is printed by the spec and is the item's identifier on
the ORCID record. The record itself is at
`https://sandbox.orcid.org/<the iD>` under "Peer review". Nothing removes what
the test writes, so a record accumulating items across runs is expected, not a
sign of a bug.
