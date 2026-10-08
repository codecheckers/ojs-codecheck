# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Overview

OJS generic plugin integrating the CODECHECK process into journal submission,
editorial and publication workflows. Targets **OJS 3.5.0+ / PHP 8.2+**.

It ships a Vue 3 UI inside the OJS backend, its own API, its own tables and
migrations, GitHub register integration (issues, labels, assignees,
`register.csv` deposit via PR), ORCID peer-review deposit, CODECHECK links in
Crossref/DataCite deposits, a publication-blocking validator and a status
state machine.

Background on any feature lives on its GitHub issue: find the feature's entry
in `CHANGELOG.md`, then `gh issue view <n> --json title,body,comments`. Read it
before changing behaviour you did not write — many rules here were deliberate
decisions by the repository owner.

## Development commands

```bash
composer install    # required: four classes require vendor/autoload.php at file scope
npm install
npm run build       # -> public/build/{build.iife.js,build.css}; gitignored, required at runtime
npm run watch

make lint           # php-cs-fixer, report only (must be clean)
make lint-fix
make lint-deps      # installs the tool into dev/tools/vendor/
make hooks          # pre-commit hook linting staged PHP

make test           # component + PHPUnit
make test-component # Cypress component tests, no OJS needed
make test-php       # PHPUnit, needs the linked OJS (sets OJS_ROOT)
make test-e2e       # Cypress e2e, needs `make serve`
make test-mail THROWAWAY=<name>  # specs that read sent mail, from Mailpit
make test-e2e-reverse / test-e2e-shuffle [SEED=n]   # catch order dependence
make screenshots    # every UI surface -> cypress/ui-screenshots/
make inspect URL=…  # Playwright: screenshot + DOM + console/network -> dev/out/
make check-scheduled-deposit THROWAWAY=<name>      # register deposit from the scheduled task
make throwaway-up / throwaway-down THROWAWAY=<name>
make db-reset / db-load / db-credentials / clear-cache
make doi-test-config / doi-export THROWAWAY=<name>
make test-live GITHUB_TOKEN=… / test-orcid-live    # live tests; read dev/live-*-tests.md first
```

- Re-run `npm run build` after every change under `resources/js/`. The bundle is
  an IIFE with `pkp` and `vue` external; it only runs inside OJS.
- **php-cs-fixer lives in `dev/tools/` with its own `composer.json`, never in the
  plugin's `require-dev`.** The plugin's `vendor/autoload.php` is registered
  *prepended*, so any package in it overrides OJS's copy for every request. Same
  for any future dev tool. `.php-cs-fixer.dist.php` is a copy of PKP's
  `lib/pkp/.php_cs_rules` minus `PKP/hookfixer`.

## Architecture

| Layer | Location |
|---|---|
| Plugin entry / hooks | `CodecheckPlugin.php` |
| API | `api/v1/CodecheckApiController.php` (`PKPBaseController`) |
| Domain/services | `classes/` |
| Backend UI | `resources/js/` → `public/build/` (Vue 3, extends OJS's Vue/Pinia app) |
| Reader UI | `classes/FrontEnd/` + `templates/frontend/` (Smarty) |
| Persistence | `classes/migration/`, `classes/Submission/CodecheckSubmissionDAO.php` |

### Hooks (`CodecheckPlugin::register()`)

- `Templates::Issue::Issue::Article` → `IssueTOC` badge
- `Templates::Article::Details` → `ArticleDetails` sidebar
- `Templates::Article::Main` → `ArticleAvailability` statement below the abstract
- `Schema::get::submission` / `Schema::get::publication` → plugin fields
- `Form::config::before` → opt-in checkbox on the start form; and
  `AvailabilityStatementField` on Publication › Metadata — matched by
  `PKPMetadataForm::FORM_METADATA` **id, never `instanceof`** (the wizard's
  `ForTheEditors` extends it)
- `Submission::edit` → persists `codecheckOptIn`
- `Submission::validate` → `saveWizardFieldsFromRequest()`
- `APIHandler::endpoints::plugin` → `CodecheckApiController`
- `LoadHandler` → `CodecheckPageHandler` (`codecheck/info`)
- `TemplateManager::display` (three callbacks) → dashboard config, workflow state,
  status locale keys, wizard steps
- `Template::SubmissionWizard::Section[::Review]` → wizard templates
- `Publication::validatePublish` → `validatePublicationHook()` can block publication
- `Publication::publish` → ORCID deposit, then register deposit (`SEQUENCE_LATE`)
- `Publication::publish::before` → `CertificateReferenceUpdate::addOnPublish()` (#183)
- `articlecrossrefxmlfilter::execute` / `datacitexmlfilter::execute` → `CodecheckDoiDeposit` (#19)
- `Context::add` → `writeDefaultSettings()`
- `Schema::get::user`, `publicprofileform::…`/`userdetailsform::…` and the
  profile/user-details template hooks → `GithubUsernameField` (#13). `Form`
  lower-cases `initdata`, `readuservars`, `execute`, but not `::Constructor`.
  The logged-in user is read before plugins load, against a schema without
  the property, so the value is read and written through `user_settings`
  directly (`readFor()`, `writeFor()`), never through a user object alone.
- `Mailer::Mailables` → `CodecheckerNeededEmail` under Emails (#31); its default
  template is `emailTemplates.xml` + `locale/*/emails.po`, installed by the
  install migration and, where it is missing, before the email is sent
- `StageAssignment::created` (an Eloquent model event; OJS raises no hook for a
  stage assignment) → `CodecheckerNeededNotice` emails an editor assigned to an
  opted-in submission without a linked codechecker (#31). There is no task in
  OJS's Tasks list: a plugin's notification type cannot have a link (#192).

**Registered outside the `getEnabled()` block** (and must stay there): register
deposit, `Publication::publish::before`, the DOI filter hooks, `Context::add`,
`UserAction::mergeUsers` (moves codechecker links, #13) and the
`StageAssignment::created` listener (OJS assigns editors on submission).
They run on the command line or in site-scoped requests where `getEnabled()`
reads the site row; each resolves the journal from the article/document and
checks enablement for that journal itself.

Callbacks return `false` so other plugins continue. `Hook::call` callbacks take
`(string $hookName, array $args)`; `APIHandler::endpoints::plugin` is raised
with `Hook::run`, so its callback takes `(string $hookName, APIRouter $router)`.
A TypeError inside a hook is swallowed by PKP — check the server log.

### Vue integration (`resources/js/main.js`)

- `pkp.registry.registerComponent(...)` for the components in
  `resources/js/Components/`
- `storeExtend("workflow")` — CODECHECK menu item (sentinel `stageId: 999`),
  `CodecheckMetadataForm` + `CodecheckStatusForm` / `CodecheckGithubIssueDisplay`;
  `CodecheckPublicationInfo` above Publication › Metadata (#34), fed by
  `window.codecheckDashboardConfig.publicationInfo`
- `storeExtend("dashboard")` — CODECHECK column, gated on
  `codecheckDashboardConfig.showDashboardColumn`, which is injected on **every**
  dashboard view (#178)
- Submission wizard: DOM-scraping helpers `CodecheckWizardManager` and
  `CodecheckReviewRefresher` — fragile, OJS markup changes break them
- `resources/js/optIn.js` (`isOptedIn()`, `notOptedInReason()`) is the one
  answer to whether a submission takes part; an unset flag is "no choice recorded"

Shared JS rules mirrored from PHP: `orcid.js` ↔ `CodecheckCodecheckers`,
`isWebUrl.js` ↔ `Constants::isWebUrl()`,
`configSpec.js` (fields each config version requires — warned, never enforced),
`authorEntries.js`.

### Markup and dialogs

- **`resources/js/markup.js`**: build HTML strings with the `html` tagged
  template, which escapes interpolations; plugin-authored markup is marked
  `raw()`; `toHtml()` produces the final string. Never concatenate (#179).
- **`resources/js/dialogs.js`** is the only place naming `pkp.modules.useModal`:
  `askForConfirmation`, `showInformation`, `askForInput`, `closeDialog`.
  - Primary action last; `destructive: true` for removals.
  - Escape / click outside counts as No.
  - Button labels use OJS `common.*` keys (translated everywhere); only
    "Change" is a plugin key.
  - `askForInput` opens a `bodyComponent` dialog with **no `actions`**: OJS's
    `Dialog` disables all actions after the first click, so input bodies draw
    their own buttons via the `dialogForm.js` mixin (implement `validate()` →
    `{valid, value}`, own `setup()` returning `t` — Vue 3 ignores a mixin's
    `setup`). An invalid value or an `onSubmit` returning a message keeps the
    dialog open with the error shown.
  - `closeDialog()` uses the modal store, falling back to `close-dialog-vue`.
- No `alert()`/`confirm()`/`prompt()` fallbacks.
- **OJS's Pinia stores are reached only through `resources/js/piniaStore.js`**
  (`piniaStore(name)`, `workflowStore()`): the one place naming the private
  `pkp.registry._piniaInstance._s` (`getPiniaStore()` throws for `modal`).

### API (`api/v1/`)

Routes under `api/v1/codecheck/…`. Authorization is PKP's:
`UserRolesRequiredPolicy`, `ContextAccessPolicy`, and `SubmissionAccessPolicy`
for methods listed in `SUBMISSION_SCOPED` (no reader branch). Endpoints take the
submission from `getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)`, never from
a request variable. CSRF is PKP middleware (`X-Csrf-Token`, non-GET only).
Refusals answer 401 `user.authorization.roleBasedAccessDenied`.

Roles per route: `READ_ROLES` (admits authors), `WRITE_ROLES`, `EDITOR_ROLES`
(anything journal-wide or public, #173), `ADMIN_ROLES`.
`CodecheckApiControllerRolesUnitTest` pins them.

**Adding an endpoint**: register in `getGroupRoutes()` with
`->middleware([self::roleAuthorizer(...)])`, return `response()->json(...)`,
and add the method to `SUBMISSION_SCOPED` if it acts on a submission —
otherwise it answers for any submission in the journal.

Endpoints: `GET labels|metadata|yaml|register|status|status/history|orcid-status|orcid-test|codecheckers/reviewers|codecheckers/reviews`,
`POST identifier|issue|metadata|references|repository|repository/preview|repository/validate|yaml/validate|status/update|orcid-deposit|codecheckers/github|codecheckers/reviews/close`.

**There is deliberately no file upload/download endpoint**
(`CodecheckApiControllerRoutesUnitTest` asserts it). Use OJS file services and
`SubmissionFileAccessPolicy` if one is ever needed.

Per-submission questions in `CodecheckSubmissionAccess` (per submission, not
per journal role — a Section editor invited as a reviewer has no stage
assignment):

- `mayKnowAuthors()` (#28) — false only for a reviewer in double-anonymous
  review on their current assignment. Then `GET metadata` omits authors and
  contact and sets `authorsWithheld`; the YAML has no `paper.authors`.
  `$revealAuthors` has no default on `getMetadata()`/`generateYaml()`.
  Repositories and the availability statement are still sent.
- `isLinkedCodechecker()` (#13) — a reviewer whose assignment in force
  (latest round, not cancelled/declined: `CodecheckerReviewers::currentOf()`)
  is linked by `userId` to an entry of the codechecker list. The reviewer
  branch of `canWriteMetadata()`, `orcidDepositScopeFor()` and the reviewer
  page's CODECHECK form; any other reviewer gets none of them.
- `mayEditCodecheckers()` (GHSA-4p3r-qgp4-g74r) — manager/site admin, or Section
  editor/Assistant with a stage assignment. Anyone else's save keeps the stored
  codechecker list, whatever was posted. Also gates `codecheckers/reviewers`
  and `codecheckers/github`.
- `mayManageIdentifier()` (#65) — journal manager or site admin. `identifier`,
  `issue`, `labels` are `ADMIN_ROLES`; `GET metadata` answers
  `permissions.manageIdentifier`; the form then shows the identifier read-only
  and sends no `issue` update; `saveMetadata()` keeps the stored `certificate` and `issue`
  for anyone else. Automatic register writes (status comment, labels, JSON
  block) are not covered by it.
- `isEditorOn()` (#127) — per submission, the one editor question (there is no
  journal-wide one): manager/site admin, or Section editor/Assistant with a
  stage assignment on it. `canWriteMetadata()` (an editor on it, or a linked
  codechecker), the ORCID deposit scope and GitHub assignment use it, so a
  journal-wide Section editor who reaches a submission only as its author or
  as an invited reviewer is not treated as its editor.
- `permissions()` (#127) — what the forms offer, built from the rules above:
  `GET metadata` answers `permissions` (`write`, `editCodecheckers`,
  `manageIdentifier`, `addCertificateReference`), `GET status` `canUpdate`,
  `GET orcid-status` `depositScope` (`all`/`own`/`none`, from
  `orcidDepositScopeFor()`, which `orcid-deposit` asks too). They are hints that save a refused
  request; the endpoints enforce their own rules, which the hints mirror
  (`saveMetadata`, `repository` and `status/update` ask `canWriteMetadata()`
  after the route's role list; `addCertificateReference` is only part of its
  endpoint's rule). **A form offers only what the
  server said**: closed until it answers, no role logic in JS; without
  `write` the fields sit in a disabled `<fieldset>` and Save is gone. A new
  action in a form needs its flag here, not a JS role check. Not rendered
  into the page: OJS 3.5's workflow is the dashboard's side panel, so the
  submission is unknown at render time.

`POST repository/preview` (`READ_ROLES`, so authors; #190) is the wizard's
load from an existing check: `previewForAuthor()` fetches like the import but
writes nothing, answers only the yml's repositories and manifest
(`CodecheckAuthorMetadata::entriesFromCodecheckYaml()`) and reports a title
mismatch as `titleMatches: false` instead of refusing. The author's pointer is
the submission field `existingCodecheck` (DOI or web address,
`isExistingCheckAddress()`, DOIs via `Constants::bareDoi()` ↔ `doiUrl()` in
`isWebUrl.js`); `GET metadata` answers it, withheld with the authors — but
OJS's own submissions API answers it to reviewers too (accepted). The form
offers the editor's import from it; there, and only there, a title mismatch is
asked about and imported on Yes (`acceptTitleMismatch`).

`POST repository` imports via `importMetadataForSubmission()`, which refuses a
`codecheck.yml` whose `paper.title` is missing or not the submission's
(`titlesMatch()`: whitespace-collapsed, case-insensitive; also used by extended
validation). The form import does not overwrite submission data and keeps the
identifier while `certificateReadonly` (linked, or not the user's to manage).

### Persistence

Tables (`classes/migration/install/CodecheckSchemaMigration.php`):

- `codecheck_metadata` — one row per submission: `spec_version,
  publication_type, manifest, repository, source, codecheckers, certificate,
  issue, check_time, summary, report, additional_content`. `repository` is
  `TEXT` JSON: `{"repositories": [{url, hidden, providedByAuthor, containsCodecheckYaml}, …]}`.
  `codecheckers` entries are `{userId, name, orcid, github}` (`userId` null for
  an entry not linked to an account; never in the register's JSON block).
- `codecheck_status` — append-only status history (FK, cascade delete)
- `codecheck_issue_labels` — venue labels, replaced by the scheduled refresh
- `codecheck_orcid_tokens` — ORCID tokens per codechecker

Migrations: `CodecheckMigration` base (`runUp()`; `down()` throws). No install of a
release exists (decision 2026-10-05), so the install migration creates the final schema directly (each
table only if missing) plus the `codecheck.yml` genre and default settings. It
runs on `setEnabled()` and, via `upgrade.xml` → `GalleryUpgradeMigration`, on a
Plugin Gallery upgrade (no `version` attribute; failures are logged, not
thrown, because OJS deletes the plugin directory on an exception; only runs
where `codecheck_metadata` exists). **Once an install exists, a schema change
needs an idempotent step added at the end of `runUp()`**; until then, edit the
create block and the `testData` dump. Nothing in the plugin drops a table.

**Writes** go through `CodecheckMetadataHandler::saveMetadata()` (editorial) and
`CodecheckAuthorMetadata` (wizard), which enforce the repository rules.
`CodecheckSubmissionDAO` only reads. Several classes still query
`codecheck_metadata` directly; there is no single owner.

**Repositories are read through `CodecheckRepositories`** (#154, #169):
- The `codecheck.yml` holder is the `containsCodecheckYaml` flag on the entry,
  never a list index.
- `publicEntries()`/`publicUrls()` exclude hidden ones (for readers);
  `selectedUrl()` does not (for fetching); `publicSelectedUrl()` is what anything
  writing somewhere public must use. A hidden selected repository is never
  substituted — publication is blocked and the deposit refuses.

Submission-level fields (`codecheckOptIn`, `retrieveReserveCertificateIdentifier`,
`codeRepository`, `dataRepository`, `manifestFiles`, `dataAvailabilityStatement`)
live in OJS submission/publication settings, separate from `codecheck_metadata`.
On the CLI and in DOI jobs read the opt-in from `submission_settings`
(`CodecheckSubmissionDAO::isOptedIn()`).

### Register / GitHub (`classes/CodecheckRegister/`)

- `CodecheckGithubRegisterApiClient` — issues, labels, assignees,
  `depositRegisterRow()` (branch + commit + PR)
- `CodecheckGithubRegisterIssue` — issue title/body; `metadataBlock()` is the one
  builder of the JSON `<details>` block
- `CertificateIdentifier(List)` — `YYYY-NNN`, next free one
- `CodecheckIssueLabels` — `fromDB()` / `fromApi()` (venues index)
- `CommunityCodecheckers` — reads the lists in `codecheckers/codecheckers` at `HEAD`
- `CodecheckStatusRegisterUpdate` — carries a status change to the issue:
  comment (#150), labels (#174), assignees, JSON block
- `CodecheckPostOrigin` — journal name/URL, versions, signature

Credentials come from plugin settings only; nothing at runtime reads `.env`.

Rules:

- **Every GitHub client comes from `GithubHttp::client()`** (time limits plus a
  per-request breaker; reset with `GithubHttp::reset()` — tests that trip it
  must). Never `new \Github\Client()`. `depositToRegister()` resets it per article.
  Everything that is not GitHub goes through Laravel's `Factory` (as
  `CurlApiClient` and `CommunityCodecheckers` do) with the journal's proxy from
  `GithubHttp::proxy()` and the same time limits.
- **Every post is signed** via `CodecheckPostOrigin`. The journal URL is built
  from config (`base_url`, `restful_urls`), **never from the request** (Host
  header is attacker-controlled). The JSON block keys are a published format —
  don't rename them.
- **Labels are added/removed individually, never replaced.**
  `CODECHECK_REGISTER_MANAGED_LABELS` (`needs codechecker`, `work in progress`)
  is all the plugin ever removes; `CODECHECK_REGISTER_STATUS_LABELS` maps status
  → labels; `labelChanges()` is the pure diff. Removals first, each write in its
  own try, 404 on removal = success, labels follow the *current* status.
  `id assigned` is never removed. Unknown status changes nothing. The form's
  label selection is add-only.
- **Assignees are only added, never removed.** `syncAssignees()` is the one
  place; it reads back (GitHub silently drops unassignable users) and returns
  `null` when GitHub could not be asked. Triggered by recording
  `codechecker assigned` or an editorial save that adds a username, for editors
  only, and only past `needs codechecker`.
- **The JSON block follows the record** (`refreshMetadata()`) after every status
  change and editorial save, under the journal's "update body" choice; found by
  an HTML comment marker (CRLF-normalised); written only when `metadataJson()`
  changed. The rest of the body is rewritten only by "update issue".
- A stored issue number is only used if `issue.url` is in the configured
  register (`issueUrlIsInRegister()`).
- GitHub creates unknown labels on use, so the settings form checks the register
  has all needed labels; plugin-managed labels are excluded from the form's list.
- Failures don't fail the triggering save: `registerWarning` /
  `registerUnreachable` are returned; explicit writes answer 504 on timeout.
- Codechecker names in comments go through `RegisterCodecheckers::describe()`
  (zero-width space after `@` and `#`; HTML entities are not enough).
- The settings form probes the register (two unauthenticated requests) only when
  organisation or repository changed.

**Register deposit** on `Publication::publish`: gated by
`shouldDepositToRegister()` for the article's journal; needs a reserved
certificate and a public repository flagged `containsCodecheckYaml` whose
`codecheck.yml` is fetchable; never blocks publication. Runs for the scheduled
task (CLI) too (#188). Not reached when OJS's web task runner publishes at the
end of a request for another journal (pkp-lib#9345) — cron works.

### Codecheckers (#186, #13)

Every codechecker is an OJS user assigned to the submission as a reviewer
("Add Reviewer", with the "Invitation to codecheck" template), and each entry
is linked to that account by `userId`. A submission without a review round
gets no codechecker.

- `CodecheckerReviewers` — the reviewers assigned now (`currentAssignments()`),
  the entry for an account (`entryFor()`: name, bare ORCID iD, GitHub username
  from `user_settings`), `isLinkedCodechecker()`, the merge hook.
- `saveMetadata()` goes through `CodecheckerReviewers::resolveEntries()`: a
  newly introduced `userId` must be a reviewer assigned now (else 400) and is
  copied from the account; a stored link keeps its stored entry; an entry
  without `userId` is kept only as stored (a new one is refused) and gives no
  access. `GET metadata` strips `userId` for anyone without `editCodecheckers`.
- `POST codecheckers/github` only fills an account that has no username.
- `CodecheckCodecheckers::withStoredEntries()` is the stored shape (with
  `userId`); `normalizedEntry()`/`withNormalizedEntries()` stay the public
  three-key shape the register uses. ORCID check digit computed locally, not
  `ValidatorORCID` (needs a booted app); stored bare. `buildYaml()` normalises
  author (URI) and codechecker (bare) iDs to one shape.
- The dialog offers assigned reviewers not on the list (`GET
  codecheckers/reviewers`, with `doubleAnonymous` and the community list's
  `githubSuggestion` for an account without a username); "Use it" saves the
  username to the account (`POST codecheckers/github`), never unasked.
- A `codecheck.yml` import (`importedCodecheckers()`, file spells `ORCID`)
  keeps only codecheckers matching an assigned reviewer by ORCID iD and names
  the rest.
- The CODECHECK tab warns beside a codechecker on a double-anonymous review
  (#28: they get no authors) and beside an unlinked entry.
- **Codecheckers are never anonymous to the authors**, whatever the review
  method, and nothing may say they are: their names are in the record,
  the certificate and the register. A codechecker who also gives a regular
  review is an accepted conflict.
- **Closing a review** (`CodecheckerReviewClosing`): from any *completed*
  status on, the status panel (`CodecheckCodecheckerReviews`) lists linked
  codecheckers' assignments in force without a completion date
  (`GET codecheckers/reviews`, empty before then) and closes one
  (`POST codecheckers/reviews/close`, refused unless offered) the way
  `PKPReviewerReviewStep3Form::execute()` does: completion date, "See
  comments", a review comment (certificate, register entry), editors'
  notification and email (last, each editor's failure logged, not fatal), task
  removed, event log. The review is claimed by a conditional update of
  `date_completed`, so it closes once. The comment is always visible to the
  authors, and names the register issue only if it is in the configured
  register. OJS cannot reopen a review, so `close-review.cy.js` puts it back
  through the `snapshotReviewAssignment`/`restoreReviewAssignment` tasks.

### ORCID deposit (`classes/Orcid/`)

- Only an account whose iD is on the submission's codechecker list is credited:
  `startAuth` refuses without a recorded iD, the callback refuses other
  accounts, `depositTargets()` drops other tokens.
- **Nothing contacts ORCID until there is something to deposit** (#182): the
  certificate check and `depositTargets()` come first; all requests go through
  private `deposit()`. Untested convention — don't call
  `ensureGroupIdRegistered()` elsewhere.
- `ensureGroupIdRegistered()` memoises per journal + API type per process, only
  successes, in a static nothing resets.
- `PeerReviewPayloadBuilder::groupIdFor()` is the one group-id derivation.
- Needs a journal in the request; not run from the scheduled task (by decision).
- `CodecheckPlugin::isOrcidDepositOn()` is the one gate (#13): the plugin's
  switch, and OJS's own ORCID integration not depositing reviews with the
  Member API (`ojsDepositsReviews()`; OJS then deposits the closed review
  itself). Deposit on publish, `orcid-deposit`, `startAuth`, the callback
  (before the code is exchanged) and the ORCID panels ask it.
- Live testing needs ORCID *Member* API sandbox credentials; see
  `dev/live-orcid-tests.md`.

### Publication validation

`CodecheckPublicationValidator` (opted-in submissions), short-circuiting, **order
matters**: (1) selected repository not hidden (#169), (2) the register can name
it (#36, `RegisterRepositoryName`; resolves a DOI and, for a GitHub branch,
asks GitHub — accepting a branch GitHub cannot confirm, which the deposit then
refuses), (3) current status in the allow-list, (4) generated YAML parses,
(5) with extended validation: the `codecheck.yml` is fetchable and its title
matches. Checks 1 and 2 run only while the register deposit is on. Check 3 can
return false without an error, so new gates go before it.

`Publication::validatePublish` runs only for REST publishing — not for
`IssueGridHandler::publishIssue()` or the scheduled task. So every gate needs a
matching refusal at the point of action (as the register deposit refuses a
private selection). Take the submission from the hook args (`$args[2]`), not
the router's handler (null under REST).

### Status

`CodecheckStatusHandler` (static) on `codecheck_status`. Statuses are locale
keys in `Constants::CODECHECK_STATUSES`: pending → needs codechecker →
codechecker assigned → stalled → completed → published certificate. "Pending"
is the absence of a row. The review stage's panel (`CodecheckReviewDisplay`)
reads the same status and record endpoints as the CODECHECK tab.

### DOI deposits (`classes/DoiDeposit/`, #19)

With `CODECHECK_DOI_DEPOSIT_LINKS` on, an opted-in article at *published
certificate* gets the certificate DOI as Crossref `hasReview` / DataCite
`IsReviewedBy`, and public repositories as `isSupplementedBy`. Everything is
resolved from the document (Crossref by DOI, DataCite by `publisherId`, since
test mode rewrites the prefix). Not validated at deposit time;
`DepositSchemaUnitTest` checks against vendored schemas in
`tests/DoiDepositUnitTests/schemas/`. `CODECHECK_DOI_REDEPOSIT` marks the DOI
stale when the links change (`linksBeforeChange()` / `redepositIfChanged()`
around status and record writes). OJS 3.5 only; 3.6 changes on #187.

### Certificate reference (#183)

`POST references` (`EDITOR_ROLES`) and `Publication::publish::before` add the
certificate to the article's references, only at *published certificate*.
`publish()` does not reparse `citationsRaw`, so the hook calls
`CitationDAO::importCitations()`. `CertificateReference::merge()` matches an
existing line by whole DOI/URL/identifier, never substring.

### Scheduled refresh (`classes/Tasks/RefreshCodecheckLists`, #65)

Refreshes venue labels and community codechecker lists, daily or weekly per
`CODECHECK_LISTS_REFRESH` (shortest across enabled journals).
- Registered `everyMinute()` and filtered by `isDue()`; `isDue()` never throws.
- Returns `true` even on failure (OJS emails admins on `false`); failures back
  off for an hour (`recordFailure()`).
- Only registered because `version.xml` has no `lazy-load` (pinned by
  `CodecheckPluginHooksUnitTest`).
- Readers fall back to on-demand fetches when nothing is stored (labels also
  when older than 30 days), through `CodecheckIssueLabels::refresh()`, which
  owns the hold-off: only a read tried and failed starts the hour.
- `RefreshCodecheckLists::listsReadable()` is the one sandbox check.
- The journals' interval is cached for an hour; saving the setting clears it.
- In sandbox mode neither list is fetched. `scheduler.php run` refuses in
  sandbox mode. The plugin's writes (register, `register.csv`, ORCID) are not
  sandboxed on purpose: they are tested against `testing-dev-register` and the
  ORCID sandbox.

### Settings

`classes/Settings/` + `templates/settings.tpl` (Smarty/FBV). A new setting goes
into `Constants`, `SettingsForm::initData()` + `readInputData()` + `execute()`,
`fetch()` if it needs template variables, and the template.
`settings-roundtrip.cy.js` then covers it if it renders. Fields outside
`{fbvFormArea}` lose the bordered box. Choice settings use
`.codecheck-choice-list`; option-dependent fields are `.badge-dependent-field`.

- Any verb added to `Manage::execute()` needs its own CSRF check (PKP's `manage`
  op has none). `recreateCodecheckerSetup` is one.
- **Codechecker role and invitation template** (#13): `CodecheckerJournalSetup`
  creates each once per journal on enable (not on `Context::add`: no new
  journal has the plugin enabled yet), storing the
  user group id / template key (`CODECHECKER_USER_GROUP_ID`,
  `CODECHECK_INVITATION_TEMPLATE_KEY`); never recreated on its own, only from
  the settings button. The template is an alternate to `REVIEW_REQUEST`; its
  `{$…}` variables survive `__()` because no parameters are passed.
- **Defaults**: `Constants::CODECHECK_SETTING_DEFAULTS` +
  `CodecheckPlugin::getSettingWithDefault()` (only `null` is unset) +
  `writeDefaultSettings()` (on enable, `Context::add`, install migration).
  Changing a default there does not reach existing rows — needs a migration.
  Use the map only for defaults that will stay right. Not in the map:
  `CODECHECK_ENABLED_CONFIG_VERSIONS` (resolved in `getEnabledConfigVersions()`),
  `CODECHECK_BADGE_HEIGHT` (normalised by `Constants::normalizeBadgeHeight()`),
  the localised texts, `CODECHECK_GITHUB_SIGNATURE`; and, not yet migrated,
  `CODECHECK_MODE`, `ORCID_API_TYPE`, `CODECHECK_BADGE_TYPE`.
- `isRegisterDepositEnabled()` is the single reader of the deposit switch (#177).
- Values rendered into `style` attributes are normalised on save and on read:
  `normalizeBadgeHeight()`, `normalizeBadgeTextColor()`.
- **Reader-visible text settings are multilingual** (#164):
  `CODECHECK_AVAILABILITY_STATEMENT_HEADING`, `CODECHECK_BADGE_TEXT`. Read with
  `Constants::localizedText()` (reader locale → primary locale → `null` for the
  `__()` default); write with `cleanLocalizedText()`;
  `SettingsForm::localizedTextToSave()` preserves other locales. Non-arrays read
  as unset. Multilingual FBV fields have no stable id (select by `name`) and must
  not get a `placeholder`. Exception: `CODECHECK_GITHUB_SIGNATURE` is
  single-valued English.
- `CODECHECK_BADGE_LINK_TARGET`: register page or DOI, falling back to the other.
  `classes/FrontEnd/Badge.php` resolves all badge settings.
- **Config versions**: the plugin knows only `2.0`; only concrete versions go in
  `CODECHECK_CONFIG_VERSIONS`, never `latest`. `saveMetadata()` answers 400 for
  an unknown version, or a known one the journal does not offer unless the
  record is already on it. A stored unknown version reads as the default
  (`resolveConfigVersion()`). JS mirrors of the defaults in
  `CodecheckMetadataForm.vue` are only a pre-load fallback.

### Logging and i18n

- `CodecheckLogger::debug|info|warning|error()`; no bare `error_log()`, no
  `console.log` (failures go to `console.error` and to the editor).
- `locale/en/locale.po`, keys prefixed `plugins.generic.codecheck.`.
  `registry/uiLocaleKeysBackend.json` is **generated** by `npm run build` from
  literal `t('…')`/`tk('…')` calls — never hand-edit. A key built at runtime or
  inside a ternary in `t(…)` is not extracted; spell keys out in a `tk()` map.
- **One sentence is one key**: use `{$name}` placeholders, build markup in code,
  render with `v-html` / unescaped Smarty. See PKP's
  [translating guide](https://docs.pkp.sfu.ca/translating-guide/en/coders).
- Brand colours: `#008033`, dark `#006629`, light `#e8f5e8`.

## Testing

### Component tests

`make test-component` — no OJS, no DB, no build. Mounts `.vue` sources, stubs
the API with `cy.intercept`. Import `cypress/support/pkp-mock.js` first in each
spec. The mock's `t()` returns the key for messages without placeholders, the
substituted text for messages with them, and **throws** on a parameter
mismatch or missing `.po` entry; substitution follows OJS's `String.replace`
semantics (`$` in user values must be doubled). Its `useModal` renders a real
dialog (`.pkp-mock-modal`, `.pkp-mock-modal__action`).

Not covered: `CodecheckGithubIssueDisplay.vue`, `main.js` `storeExtend` wiring,
wizard DOM helpers.

### E2E tests

`make test-e2e` with `make serve` running and the dataset loaded. Run against a
**throwaway** instance (see below), never the shared `ojs-350`.

- **Specs share fixtures and must restore what they change.** Submissions 5, 7,
  8, 9 are written by several specs; submission 10 is never touched. The status
  table is append-only: restore by recording the original status, and assert on
  history relatively.
- **Only reopening a closed review writes the database directly**:
  `cy.snapshotOpenReview()` / `cy.reopenReview()`, through the tasks in
  `cypress/plugins/reviewAssignmentTasks.js` (the `mysql` client,
  `CYPRESS_DB_*`, set by the `make` e2e and mail targets and CI; a bare `npm
  run test:e2e` or `test:mail` fails the specs that use them). Nothing else
  may: drive the form or the API.
- **The suite must make no external call.** Anything new on
  `Publication::publish` must be switched off in `publication-validation.cy.js`
  around the real publish. `codecheckers/reviewers` reads the community list,
  which sandbox mode (throwaways, CI) does not fetch.
- Use the commands in `cypress/support/e2e.js`: `cy.openCodecheckSettings()`,
  `cy.saveCodecheckSettings()`, `cy.codecheckSettingsForm()`,
  `cy.setCodecheckFields({...})`, `cy.setCodecheckSetting()`,
  `cy.getCodecheckSetting()`, `cy.ojsApi()` (needs a backend page open for the
  CSRF token), `cy.openBackend()` (opens one), `cy.saveCodecheckRecord()`,
  `cy.recordCodecheckStatus()`, `cy.assignParticipant()` /
  `cy.removeParticipants()` / `cy.participantAssignments()` (OJS's participant
  grid), `cy.inviteReviewer()` / `cy.unassignReviewer()` (its reviewer grid),
  `cy.invitationTemplates()`, `cy.snapshotOpenReview()` / `cy.reopenReview()`,
  `cy.publishedArticleId()`.
- A red run usually has a concrete cause: server down, plugin fatal, symlink
  pointing elsewhere, stale `cache/t_compile/`. Check those before calling it flaky.
- Uncovered: opt-in, the submission wizard, register deposit.

### Mail tests

`cypress/tests/mail/` is a separate suite, never run by `make test-e2e`:
`make test-mail THROWAWAY=<name>` starts Mailpit, switches the throwaway into
mail mode for the run and back afterwards (`mail-up` / `mail-down` alone keep
it there). CI runs it on every push as the mail leg of the e2e job. Mail mode
is `dev/mail-mode.sh`, the one place for it: sandbox mode forces OJS's `log`
mailer, so it turns sandbox off and stops OJS's web task and job runners
instead. The plugin's list fetches are then live, so **a mail spec opens no
page that reads the venue labels or the community list** (the CODECHECK tab,
the codechecker dialog). Read mail only through `cypress/support/mail.js`
(`cy.clearMail()`, `cy.mailTo(address, subject)`); restore fixtures as e2e
specs do. Plan and further cases: `.claude/plan-mail-tests.md`.

### PHPUnit

`make test-php`. Needs an OJS install (`OJS_ROOT`); `tests/bootstrap.php` maps
the plugin namespace to this checkout regardless of the symlink, and binds a
`FakeTranslator` (`__()` returns the key). Nothing is skipped.

Not unit-testable (DB/network/booted app in the first lines), covered by e2e
instead or not at all: API endpoint bodies, `CodecheckStatusHandler`,
`CodecheckRegisterDepositService`, `SubmissionWizardHandler`,
migrations, `CodecheckPageHandler`, `OrcidDepositService` beyond
`depositTargets()`. **Prefer e2e over booting the application in PHPUnit**;
extract pure static rules to unit-test them. **Outbound HTTP is faked by
address, not by method (#191)**: GitHub through `tests/Support/GithubFake`
(routes like `'POST /repos/o/r/issues/7/labels'`, over `GithubHttp::client()`;
an unknown route throws; assert with `assertSent()`/`addresses()`), every other
host through a Laravel `Factory` given to `CurlApiClient` (or
`CommunityCodecheckers::read()`) with `preventStrayRequests()` and `fake()`.
Patterns without a scheme match any host that ends so; use whole addresses
where a redirect leads to a sibling host. **A test that uses Guzzle
must `require` the plugin's `vendor/autoload.php` first**, as the production
classes do, or classes from OJS's older copy mix with the plugin's and break
later tests. `TemplateManager` cannot be mocked
(hand-written stand-in). OJS's router answers unknown routes/methods with
`api.404.endpointNotFound` before the plugin is reached.

### CI

`.github/workflows/tests.yml`: PHPUnit (OJS `stable-3_5_0`, MySQL 8), Cypress
component, Cypress e2e (full stack, sandbox mode, screenshots artifact) and, as
a second matrix leg on the same setup, the mail suite against a Mailpit service.
`.github/workflows/lint.yml`: php-cs-fixer + `php -l`.

### Live tests

`cypress/tests/live/` (outside `specPattern`, require `CYPRESS_live=1`):
register issue/comment against `codecheckers/testing-dev-register`
(`dev/live-register-tests.md`; every run leaves an issue) and ORCID sandbox
deposit (`dev/live-orcid-tests.md`). The register PAT is fine-grained, scoped to
the testing register (issues rw, contents r, pull requests rw, workflows rw).
**Never write a token into the repository**; a pasted token is spent.

### Test data

`testData/stable-3_5_0-codecheck/` — dump + files for journal `codecheck`; users
`admin`, `jmanager`, `seglen`, `dnuest`, `fostermann`, `rreviewer`,
`sectioneditor`, `ccodechecker` (password = username). `sectioneditor` is
Section editor, assigned as editor to submission 8 and only the author of 10.
`jmanager` is Journal manager, Journal editor and Section editor, so it can be
assigned to one submission in two editorial roles.
On submission 9 only: `rreviewer` is a double-anonymous reviewer,
`ccodechecker` (user 8, Codechecker role) a reviewer linked to its
codechecker list — its codechecker, and the list's only entry, so specs can
change the list and put it back (an unlinked entry cannot be re-added). The dump carries the full
CODECHECK schema and `enabled = 1`, so migrations never run against it.

**Any change to `codecheck_metadata`'s shape or its JSON blobs must be applied to
the dump in the same commit** (including `CREATE TABLE`), and to
`testData/demo/seed.sql` (`make demo-db`, not loaded by tests). Then
`make db-reset && make test-e2e && make screenshots` and look at the screenshots.

`showInTOC` and the other #178 defaults are deliberately absent from the dump so
the default-on reader path is exercised; `showArticleSidebar` is set.

Secrets live in `plugin_settings`, never in the dump. `.env` (gitignored) is
applied by `make db-credentials` (part of `db-load`) via
`dev/db-credentials.php`, which uses OJS's phpdotenv and leaves ORCID switched
off. Don't source `.env` from a shell.

## Local development environment

```
/home/daniel/git/codecheck/
├── ojs-codecheck/          this repo
└── ojs-350/                OJS `stable-3_5_0` checkout, as CI (`make ojs-install`)
    └── plugins/generic/codecheck -> ../ojs-codecheck
```

| | |
|---|---|
| Server | `make serve` → http://localhost:8350 (`php -S`, 8 workers) |
| Database | `ojs_codecheck_350`, `ojs`/`ojs` on `127.0.0.1:3306` (not `localhost`) |
| Journal | `codecheck`, `admin`/`admin` |

- **The plugin symlink is a single shared pointer.** Check it
  (`ls -la ojs-350/plugins/generic/codecheck`) before and after trusting an e2e
  run. After repointing: restart the server (realpath cache 120 s), run
  `make clear-cache` **and** `rm -rf ojs-350/cache/t_compile/* ojs-350/cache/t_cache/*`
  (Smarty keys compiled templates by path).
- A worktree's code is not what OJS runs until the symlink points at it.
  PHPUnit is unaffected; the Makefile finds `ojs-350` from a worktree too.
- Rows written directly into `plugin_settings` need `make clear-cache`.
- OJS 3.5 needs `app_key` (`make ojs-config` generates it).
- A new database needs a one-off root `GRANT ALL PRIVILEGES ON <db>.*`.
- The dataset's journal is not publicly enabled; anonymous visitors are sent to
  login (throwaways enable it).
- Cypress: `--config` does not override `e2e` viewport/screenshot keys — use
  `CYPRESS_*` env vars. The visual pass writes to `cypress/ui-screenshots/`, not
  `cypress/screenshots/`.

### Throwaway instances

`make throwaway-up THROWAWAY=<name>` hard-links the OJS tree (own config, cache,
files, public), runs MariaDB in Docker (port 3307) and serves on 8352; pass the
same `THROWAWAY=` to `serve`, `test-e2e`, `db-reset` etc. **Never write into an
OJS file in place there** (hard links; `sed -i` is fine), and run no `git` or
`npm` in its tree: `.git` and `node_modules` are hard-linked from `ojs-350` too. Throwaways run in
sandbox mode: no scheduled tasks or jobs from the web, no DOI deposits, no
venue/codechecker list fetches — but the plugin's register and ORCID writes
still go out.

`make doi-test-config` sets up DOIs (Crossref prefix 10.5555, DataCite test
prefix 10.5072, test-mode agencies with fake credentials; `AGENCY=`,
`REGISTERED=1`, `CERTIFICATES="2 7"`). `make doi-export ARTICLES="2 7" DOI_OUT=…`
writes the records OJS would deposit (calls `exportXML()` directly; clears
libxml errors between exports).

## Working agreements

- **Work in the main checkout on its current branch.** Worktree only when told
  to; ask if one seems warranted.
- **Never `git commit`** (any form), push, branch, merge or rebase unless asked.
  Instead: check `git status --short` / `git diff --stat` first (the working
  copy is shared), stage only the change's files **by name** (never `git add -A`
  / `.`), say what was left unstaged and why, and propose the commit message as
  text.
- **Behaviour and formatting go in separate commits** — hand over the behaviour
  commit and describe the follow-up formatting one.
- **Never post to GitHub without confirmation** — issues, comments, PR
  descriptions, reviews: show the exact text, wait for an explicit yes.
- **Plans** are in `.claude/plan-*.md` (gitignored, local only). When a task is
  done, propose the next one from a plan with status "not started"/"not settled"
  and update statuses. Plans that need others' input go on the GitHub issue.
  `.claude/ISSUE_CODE_IMPROVEMENTS.md` and `.claude/issue-65-update.md` are old
  reviews — verify against the code.
- **Non-trivial changes** (≥40 changed lines of `.php`/`.vue`/`.js` excluding
  generated files, locale, changelog and dumps; or any new class, hook, endpoint,
  migration, setting, or `codecheck_metadata` column/JSON key): run `/simplify`,
  then `/code-review` before handing over.

  | Change | Level |
  |---|---|
  | 40–150 lines of ordinary code | `medium` |
  | >150 lines, or `api/v1/`, `classes/CodecheckRegister/`, `classes/migration/`, publish/validatePublish hooks | `high` |
  | Data-rewriting migrations, the publication gate, roles/policies, token handling, public deposits | `max` (if it dies on a limit, rerun at `high`) |

  Run `/security-review` too for changes to roles/policies, token handling, or
  any setting rendered into HTML/attributes on a public page. Explain declined
  findings in the PR description.

## Conventions

- PSR-12, enforced by `make lint`. Speaking names, verbs for functions,
  docblocks on public classes/methods.
- Vue: match the file's existing style.
- **Releases**: `version.xml` (`<release>`, `<date>`), `CITATION.cff` (`version`,
  `date-released`) and the `CHANGELOG.md` heading must move together; procedure
  in `README.md`. `plugins.xml` (Plugin Gallery listing, #157) is updated on
  `main` after the package is uploaded: append only, never edit (the `md5` pins
  the package), never move or rename the file; `PluginsXmlUnitTest` validates it.
  When the first Zenodo release exists (#8), add its *concept* DOI to
  `CITATION.cff` `identifiers`. The author list in `CITATION.cff` is for the
  people concerned to decide.

### Changelog

Every user-visible change gets one line under `[Unreleased]` in the fitting
section:

- The outcome only — what the plugin does now, not history, reasons or
  alternatives.
- One line per entry, no manual wrapping.
- No internal names (classes, hooks, columns); name settings, pages or files
  the journal sees.
- Always reference the issue, e.g. `(#154)`.
- Lessons learned go on the issue (ask before posting). Conclusions that shape
  future work go in this file; if unsure where something belongs, ask.

## Traps

- `public/build/` and `vendor/` are gitignored but required at runtime.
- `registry/uiLocaleKeysBackend.json` is generated.
- Hook argument arrays carry **references**; unit tests must model them (see
  `CodecheckPluginUnitTest::buildLoadHandlerArgs()`).
- `stageId: 999` is a sentinel, not an OJS stage.
- A repository cannot be both hidden and the `codecheck.yml` holder — refused in
  the form, blocked by the validator, refused by the deposit (#169).
- Repository URLs are checked with `Constants::isWebUrl()` / `isWebUrl.js` at
  both write boundaries (`filter_var` accepts `javascript://`); renderers still
  check the scheme (#154, #170).
- **A validation failure must never look like a removal.** The wizard submits
  everything typed; `saveWizardFieldsFromRequest()` refuses via
  `&$errors[0]['repositories']` and writes nothing when refusing (#170).
- Only what a save introduces is judged (`newUnusableUrls($incoming, $stored)`):
  the author path drops and logs, the editorial path answers 400.
- The wizard's autosave ignores the CODECHECK section, so
  `CodecheckWizardManager.saveAuthorEntries()` PUTs `repositories` and
  `manifestFiles` itself; an absent key means unchanged, an empty string means
  remove all.
- The wizard textareas are the author's complete list
  (`CodecheckAuthorMetadata::merge()`), so they are seeded with only
  `providedByAuthor` entries.
- `GithubHttp`'s breaker and the ORCID group-id memo are statics nothing resets
  within a process.
- OJS 3.5.0-5's Manage Emails page fails for every email (pkp-lib#13050, fixed
  on `stable-3_5_0` for 3.5.0-6), so journals on that release cannot edit the
  plugin's email template there. To read an email a script sends, switch Laravel to the `array` mailer: OJS's
  `log` mailer fails on the command line.
