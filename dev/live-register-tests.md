# Live tests against the CODECHECK register

Two things the plugin does cannot be tested against a stub, because the whole
point of them is that GitHub receives something:

- **reserving a certificate identifier** — the plugin asks the register which
  `YYYY-NNN` identifiers are taken, picks the next free one and opens an issue;
- **recording a status** — the plugin comments on that issue, so the register
  shows when a check moved on and not only where it stands (#150).

These run against a **testing register**, never the real one.

    codecheckers/testing-dev-register

That repository exists for exactly this ("a testing and development version of
the CODECHECK Register to explore machine-to-machine workflows"). It carries a
`register.csv` and the real label set, so the plugin behaves as it would against
the live register.

## Running one

    make test-live GITHUB_TOKEN=ghp_xxx

Requires `make serve` on the side, as the rest of the e2e suite does. Optional
knobs: `LIVE_SUBMISSION` (default 9), `REGISTER_ORG`, `REGISTER_REPO`.

The spec is `cypress/tests/live/register-issue.cy.js`. It is **not** in any
suite — `specPattern` in `cypress.config.js` is `cypress/tests/e2e/**`, so the
live directory is excluded by construction and the target opts in with
`--config specPattern=…`. The spec also refuses to run unless `CYPRESS_live=1`,
so running the file directly by accident fails loudly instead of writing to a
public repository.

## What it leaves behind

**Each run creates a new issue in the testing register, and nothing deletes it.**
That is deliberate: an identifier reservation is meaningful only if it is
recorded, and a comment needs an issue that still exists. Runs therefore
accumulate, which is why the testing register is a separate repository from the
real one.

The issues a live test creates look like ordinary register issues — the plugin
composes the title from the author string and the identifier, so there is no
"live test" marker in it. Recognise them by the journal name in the body
(**CODECHECK Demo Journal**) and by the submission ID beside it.

The last run is written to `cypress/tests/live/.last-run.json` (gitignored), so
the issue it created can be found afterwards.

**A live run dirties the local database, and that is fine.** The reservation
writes a certificate identifier and the issue number onto the submission it
names, which is fixture data the ordinary e2e suite asserts against. Submission 8
is the default because it starts with no certificate and no issue. Afterwards it
has both, so `make db-reset` before trusting the normal suite again — that also
wipes the PAT, which is not in the dataset.

Do not contort a live test to keep the fixtures pristine. **Create whatever
records a live test needs**: a fresh submission, a second one for a second
scenario, a `codecheck_metadata` row with the shape under test. The local
database is scratch and one command from being rebuilt; the register is not.
The rule is the other way round from the e2e suite, which must leave the dataset
as it found it.

## Configuring the local instance

Settings live in `plugin_settings`, and OJS caches them, so a write straight to
the database needs the cache cleared:

    mysql -h127.0.0.1 -uojs -pojs ojs_codecheck_350 -e "
      UPDATE plugin_settings SET setting_value='<TOKEN>'
       WHERE plugin_name='codecheckplugin' AND setting_name='githubPersonalAccessToken';
      UPDATE plugin_settings SET setting_value='[\"updateTitle\",\"updateBody\",\"updateStatus\"]'
       WHERE plugin_name='codecheckplugin' AND setting_name='codecheckGithubUpdateFields';"
    make clear-cache

`githubRegisterOrganization` and `githubRegisterRepository` are already
`codecheckers` / `testing-dev-register` in the seeded dataset.

**`codecheckGithubUpdateFields` matters more than it looks.** It is the journal's
choice of what the register issue reflects, and it gates the status comment: with
`[]` — the dataset's default — the comment is skipped silently and the live test
for #150 proves nothing. It must contain `updateStatus`.

The token is **not** in the dataset and must never be committed. `make db-reset`
reloads the dump and wipes it, so it has to be set again after every reset.

## What the first run found

The live test was written against the endpoints as documented and failed four
times before passing. Each failure was worth having:

1. **`POST identifier` takes the whole form state, not a submission id.** It
   composes the register issue from `submission.title`, `submission.authorString`,
   `repositories`, `codecheckers` and `issue.labelsSelected`, plus a
   `reserveIdentifierMode` of `api`, `newIssueUrl` or `linkExistingIdentifier`.
   `IdentifierParameterValidator` refuses a thin payload with a 400 *before*
   GitHub is touched, which is the good news.
2. **Reserving fatalled whenever authors are anonymous.**
   `getAuthorStringBasedOnAuthorAnonymity()` returned `null` — its declared type —
   into `reserveIdentifierWithApi(..., string $authorString, ...)`, a TypeError
   that surfaced as a 400. Anonymous authors is the default, so this was the
   ordinary path. Fixed: it returns `''`, which the issue builder already renders
   as "New CODECHECK". **No test caught this** because nothing exercised the
   reservation without a stub.
3. **`POST identifier` does not store what it created.** It opens the issue and
   returns `identifier`, `issueUrl` and `issueNumber`; in the workflow it is the
   *form* that saves the record afterwards. A live test has to do the same, or
   the submission has no issue number and nothing knows what to comment on.
4. **`POST status/update` requires `userId` as an integer in the payload** — and
   takes it from the payload rather than the session, so a caller can attribute a
   status change to another user.

Two things worth fixing that this run exposed and that are not yet filed:

- **A status is not checked against `Constants::CODECHECK_STATUSES`.** Any string
  is stored and then published to the register — the first run put
  `##plugins.generic.codecheck.status.codecheckerAssigned##` on issue #190,
  because the key was misspelled and `__()` renders an unknown key that way. A
  typo in a client becomes a public comment.
- **`userId` from the payload**, as above.

## Learnings

- **A token is needed in two places.** The plugin reads its own PAT from
  `plugin_settings`; the test reads the register back through the GitHub API with
  `CYPRESS_githubToken`. They can be the same token, but the test does not take
  it from the plugin's settings — asserting through the plugin's own storage
  would be asserting the plugin against itself.
- **Assert on the register, not on the plugin's response.** The plugin answering
  `success: true` says it thinks it wrote; only reading the issue back says
  GitHub agrees. The third test in the spec is the one that matters.
- **Reservation consumes an identifier.** Every run takes the next free
  `YYYY-NNN` in the testing register. They are cheap there, but a run is not free
  and cannot be undone by deleting an issue — the number stays used in
  `register.csv` terms.
- **Unauthenticated GitHub calls are rate limited to 60/hour per IP**, which is
  easy to exhaust while iterating. The plugin's settings-form validation also
  reaches GitHub unauthenticated (`SettingsForm::checkRegisterRepository()`,
  two requests: `register.csv` and the `id assigned` label), which is why it
  only fires when the organisation or repository actually changes.
- **A register with no identifier yet answers with a question, not a
  reservation.** `POST identifier` returns `confirmFirstIdentifier` and reserves
  nothing until the editor agrees, so the spec sends `confirmFirstIdentifier:
  true` in its body. It does not send `confirmedIdentifier`, which the UI uses
  to have a stale confirmation refused — the spec accepts whatever identifier
  the server computes. A register whose `id assigned` label is missing, or that
  cannot be read, is **refused** rather than asked about (409 / 502), because
  reserving there would duplicate an identifier already recorded (#129, #130).
- **`php -S` is single-threaded.** `make serve` sets `PHP_CLI_SERVER_WORKERS=8`;
  without it a request that calls back into OJS deadlocks and Cypress hangs
  rather than failing.
- **A failed run can still leave a mark.** The first attempt reserved identifier
  `2026-070` and opened issue #189 before failing on a later step, so that
  identifier is spent and the issue is an orphan. Reservation is the irreversible
  part; everything after it can be retried. The spec now skips reserving when the
  submission already records an issue, so a rerun costs nothing.
- **Cypress is the right harness for this, not a bare HTTP script.** The endpoints
  need an OJS session and a CSRF token, which `cy.ojsLogin()` and
  `cy.getCsrfToken()` already produce; reproducing that outside the browser means
  reimplementing OJS's login. The alternative considered — driving the workflow
  form by clicking — was rejected: it tests the form's markup at the same time as
  the register interaction, and the form is the part most likely to change.

## Before a live run against the real register

Don't, unless a release depends on it. If it is ever necessary: use a submission
that is genuinely being checked, expect the identifier to be permanent, and tell
whoever maintains the register first.
