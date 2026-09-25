# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Overview

OJS (Open Journal Systems) generic plugin integrating the CODECHECK process into
journal submission, editorial and publication workflows. Targets **OJS 3.5.0+ / PHP 8.2+**.

The plugin ships its own
Vue 3 UI layer inside the OJS backend, its own HTTP API, its own database tables
with a migration system, GitHub Register integration (issue creation/update,
`register.csv` deposit via pull request), a publication-blocking validator, and a
CODECHECK status state machine.

## Development commands

### Dependencies

```bash
composer install    # REQUIRED — see note below
npm install
```

`composer install` is **not optional**: three classes hard-`require`
`__DIR__/../../vendor/autoload.php` at file scope
(`classes/Workflow/CodecheckMetadataHandler.php:5`,
`classes/Workflow/CodecheckYamlValidator.php:5`,
`classes/CodecheckRegister/CodecheckGithubRegisterApiClient.php:5`).
Without `vendor/`, any request touching the API handler fatals.

### Frontend build

```bash
npm run build       # vite build -> public/build/{build.iife.js,build.css}
npm run watch       # rebuild on change
npm run dev         # vite dev server (rarely useful — the bundle runs inside OJS)
```

`public/` is **gitignored but required at runtime**. `CodecheckPlugin::addAssets()`
loads `public/build/build.iife.js` and `public/build/build.css`. After a fresh
clone the backend UI is simply absent until `npm run build` has run. Re-run after
every change under `resources/js/`.

Vite builds an **IIFE library** with `pkp` and `vue` marked external — the bundle
expects OJS's globals (`pkp.registry`, `pkp.modules.vue`) to already exist.
It cannot be exercised standalone; Cypress component tests substitute
`cypress/support/pkp-mock.js` for these globals.

### Code style

```bash
make lint       # report PHP coding-standard violations; changes nothing
make lint-fix   # rewrite the files to the standard
make lint-deps  # install the tool into dev/tools/vendor/
make hooks      # install the git pre-commit hook that lints staged PHP
```

**php-cs-fixer lives in `dev/tools/`, with its own `composer.json`, and must
stay out of the plugin's `require-dev`.** `vendor/autoload.php` is required at
file scope by three runtime classes and Composer registers it *prepended*, so
every package in the plugin's `vendor/` outranks OJS's copy of the same library
for every request that touches the plugin. php-cs-fixer pulls in a third of
Symfony; installed there, those versions — not `lib/pkp/lib/vendor`'s — would be
what OJS runs against, tested by nothing. The same reasoning applies to any
future development tool.

PHP-CS-Fixer with the rule set in `.php-cs-fixer.dist.php` — a copy of PKP's
own `lib/pkp/.php_cs_rules` (PSR-12 plus their additions), minus their custom
`PKP/hookfixer`, which only exists inside `lib/pkp`. It is a copy rather than an
include because the plugin is developed as a standalone checkout and must lint
without an OJS install beside it; if PKP changes its rules, this file is what to
update. The whole tree was swept to the standard in one formatting-only commit
for #43, so `make lint` is expected to be clean — a violation means the change
being made introduced it. `.github/workflows/lint.yml` runs the same check plus
`php -l`, separately from `tests.yml` because it needs no OJS, database or
browser.

### Tests

Use the Makefile — it sets `OJS_ROOT` and the base URL for you:

```bash
make test              # component tests + PHPUnit (no server needed)
make test-component    # Cypress component tests — runs anywhere, no OJS needed
make test-php          # PHPUnit — needs the linked OJS install
make test-e2e          # Cypress e2e — needs `make serve` running
make test-e2e-reverse  # the same specs backwards, to catch order dependence
make test-e2e-shuffle  # the same specs in a seeded random order (SEED=n replays one)
make screenshots       # capture every UI surface to cypress/ui-screenshots/
```

See [Testing](#testing) below for what actually runs where.

## Architecture

### Layers

| Layer | Location | Notes |
|---|---|---|
| Plugin entry / hooks | `CodecheckPlugin.php` | hook registration, asset loading, template state injection |
| HTTP API | `api/v1/` | custom router — *not* PKP's `PKPHandler` API |
| Domain/services | `classes/` | metadata, register, status, validation, settings |
| Backend UI | `resources/js/` → `public/build/` | Vue 3, injected into OJS's own Vue app |
| Frontend (reader) UI | `classes/FrontEnd/` + `templates/frontend/` | Smarty, article sidebar + issue TOC badge |
| Persistence | `classes/migration/`, `classes/Submission/*DAO.php` | own tables, Laravel query builder |

### Hooks registered (`CodecheckPlugin::register()`)

- `Templates::Issue::Issue::Article` → `IssueTOC::addCodecheckBadge` (badge in issue TOC)
- `Templates::Article::Details` → `ArticleDetails::addCodecheckInfo` (article sidebar)
- `Templates::Article::Main` → `ArticleAvailability::addAvailabilityStatement` (data and
  software availability statement, in the main column below the abstract). A *different*
  hook from the sidebar one above: it fires inside `.main_entry` right after the abstract
  section, which is why neither needs a template override
- `Schema::get::submission` → adds `codecheckOptIn`, `retrieveReserveCertificateIdentifier`
- `Schema::get::publication` → `classes/Submission/Schema.php` (wizard fields)
- `Form::config::before` → two separate callbacks: the opt-in checkbox on the
  submission start form, and `AvailabilityStatementField` adding the data and
  software availability statement to OJS's publication **Metadata** form so an
  editor can correct it after submission (Issue #167). The second matches
  `PKPMetadataForm::FORM_METADATA` **by id, never `instanceof`** — `ForTheEditors`
  extends that class and is the wizard's "For the Editors" step, so an
  `instanceof` check would put the field in the wizard as well. Nothing saves it:
  the form is a `PUT` against the publication API and the field is on the
  publication schema, so it round-trips on its own
- `Submission::edit` → persists `codecheckOptIn`
- `Submission::validate` → `saveWizardFieldsFromRequest()` writes wizard fields onto the publication
- `APIHandler::endpoints::plugin` → registers `CodecheckApiController` for `api/v1/codecheck/*`
- `LoadHandler` → `CodecheckPageHandler` for the public `codecheck/info` page
- `TemplateManager::display` (three separate callbacks) → dashboard config JSON,
  workflow submission state, status locale keys, wizard steps
- `Template::SubmissionWizard::Section` / `…::Section::Review` → wizard templates
- `Publication::validatePublish` → `validatePublicationHook()` can **block publication**
- `Publication::publish` → `depositToRegister()` opens a register.csv PR (best-effort)

Hook callbacks return `false` by design so other plugins/OJS continue to run —
`validatePublicationHook()` documents why at `CodecheckPlugin.php:93-102`.

### Vue integration (`resources/js/main.js`)

The plugin does **not** mount its own app in the backend. It extends OJS 3.5's Vue/Pinia
runtime:

- `pkp.registry.registerComponent(...)` for 7 components + 2 inline components
  (`CodecheckFileStatus`, `DashboardCellCodecheck`)
- `pkp.registry.storeExtend("workflow", …)` — adds a **CODECHECK menu item** to the
  workflow sidebar (uses sentinel `stageId: 999`), and injects `CodecheckMetadataForm`
  (primary) + `CodecheckStatusForm` / `CodecheckGithubIssueDisplay` (secondary)
- `pkp.registry.storeExtend("dashboard", …)` — adds the CODECHECK column
  (gated on `window.codecheckDashboardConfig.showDashboardColumn`)
- `pkp.registry.storeExtend("fileManager_SUBMISSION_FILES", …)` — adds a status column
  and a "mark as output" action (the action currently only `console.log`s)
- Two DOM-scraping helpers for the *submission wizard* (which is not extensible the same
  way): `CodecheckWizardManager` (loads/saves textarea values via the OJS REST API) and
  `CodecheckReviewRefresher` (`setInterval` + `MutationObserver` rewriting the review panel).
  These are fragile by nature — treat OJS markup changes as breaking.

Components (`resources/js/Components/`):
`CodecheckMetadataForm.vue` (2.3k lines — the main editorial form),
`CodecheckStatusForm.vue`, `CodecheckGithubIssueDisplay.vue`, `CodecheckReviewDisplay.vue`,
`CodecheckRepositoryList.vue`, `CodecheckManifestFiles.vue`,
`CodecheckDataAndSoftwareAvailability.vue`.

### API (`api/v1/`)

`CodecheckApiController extends PKPBaseController`, registered from the
`APIHandler::endpoints::plugin` hook. It replaced a hand-rolled router that did
its own CSRF and role checks and `exit`ed after responding (#50).

- **`getHandlerPath()` returns `codecheck`**, so the routes are the same URLs as
  before: `api/v1/codecheck/…`. The Vue layer did not change.
- **Authorization is PKP's.** `authorize()` adds `UserRolesRequiredPolicy`,
  `ContextAccessPolicy`, and — for the submission-scoped actions listed in
  `SUBMISSION_SCOPED` — `SubmissionAccessPolicy`, which resolves the submission,
  checks it belongs to this journal and scopes each role: a reviewer to the
  submission they were assigned to, an author to their own. **It has no
  `ROLE_ID_READER` branch**, which is what closes the disclosure the old handler
  had. Endpoints take the submission from
  `getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION)`, never from a
  request variable.
- **Roles are per route**, not per group — `READ_ROLES`, `WRITE_ROLES`,
  `EDITOR_ROLES`, `ADMIN_ROLES` — because they differ: reading admits an author,
  writing does not, and anything reaching the public register is for a journal
  editor or an administrator alone (#173). This is the shape
  `PKPBackendSubmissionsController` uses. `CodecheckApiControllerRolesUnitTest`
  pins the sets.
- **CSRF is global middleware.** `PKP\middleware\ValidateCsrfToken` reads the
  same `X-Csrf-Token` header the client already sent, and requires it only for
  `PUT|PATCH|POST|DELETE`. The old handler demanded one on GET too, so GET is
  now laxer — deliberately, and in line with the rest of OJS.
- **Refusals answer 401** `user.authorization.roleBasedAccessDenied`, where the
  hand-rolled checks answered 400 or 403. Nothing in the UI branches on the code;
  the e2e specs assert it.

Endpoints: `GET labels|metadata|yaml|register|status|status/history|orcid-status|orcid-test`,
`POST identifier|issue|metadata|repository|repository/validate|yaml/validate|status/update|users/roles/validation|orcid-deposit`.

Adding one: register it in `getGroupRoutes()` with the right
`->middleware([self::roleAuthorizer(...)])`, add the handler method returning a
`response()->json(...)`, and — if it acts on a submission — add the method name to
`SUBMISSION_SCOPED` so the policy applies. Forgetting that last step is the easy
mistake: the endpoint then answers for any submission in the journal.

**There is deliberately no file upload or download endpoint.** `GET download` and
`POST upload` were removed for #50 and are asserted absent by
`CodecheckApiControllerRoutesUnitTest`. Nothing ever called them — the manifest
records bare filenames that `handleFileUpload()` reads from the browser's file
picker without uploading anything — and what they did was dangerous: `download`
resolved a user-supplied path against the OJS web root and `readfile()`d it
whenever the string contained `codecheck` anywhere, and `upload` wrote an
attacker-named file, extension included, under the document root. If manifest
files ever need storing, use OJS's own file services and
`SubmissionFileAccessPolicy`, not a path built from `Core::getBaseDir()`.

### Persistence

Tables are created by `classes/migration/install/CodecheckSchemaMigration.php`:

- `codecheck_metadata` — one row per submission: `version, publication_type, manifest,
  repository, source, codecheckers, certificate, issue, check_time, summary, report,
  additional_content`. `repository` is a JSON blob,
  `{"repositories": [{url, hidden, providedByAuthor, containsCodecheckYaml}, …]}`,
  and is a `TEXT` column — it held `varchar(500)` until four GitHub addresses
  overflowed it (#154)
- `codecheck_status` — status history (FK → `codecheck_metadata`, cascade delete)
- `codecheck_issue_labels` — cached GitHub labels (refreshed if >6h old)
- `codecheck_orcid_tokens` — created but currently unused

Migration structure (added for issue #94):

- `classes/migration/CodecheckMigration.php` — abstract base; subclasses implement
  `runUp()`, base `up()` wraps it with logging; `down()` throws `DowngradeNotSupportedException`
- `install/CodecheckSchemaMigration` — creates tables, creates the `codecheck.yml` genre
  per context, then **calls each upgrade migration in order** so fresh and existing
  installs converge. Register new upgrades at the end of `runUp()`.
- `upgrade/I94_AddMissingColumns` — idempotent column adds
- `upgrade/I154_MoveCodecheckYamlFlagOntoRepository` — widens `repository` to `TEXT`
  and converts the old `repoWithCodecheckYaml` index into a `containsCodecheckYaml`
  flag on each entry. Its `convert()` is `public static` so the conversion is
  testable without a database
- `CodecheckPlugin::setEnabled()` runs the install migration on enable.
  **Nothing in the plugin drops a table** — the settings form's "Clear / Reset
  DB" button did, and was removed in #131; rebuild a development instance with
  `make db-reset` instead.

The migration is the single source of truth for this schema. A stale `schema.xml`
and a dead `CodecheckMetadataDAO` used to describe two further, contradictory
shapes; both were removed. `CodecheckSubmissionDAO` is the only DAO, and it **reads**:
`getBySubmissionId()` is all it does. It used to carry an `insertOrUpdate()`
that no production code called, wrote `issueUrl` and `issueNumber` columns the
schema has never had (the schema has one `issue` JSON column), and set
`repository` to whatever string it was handed — past `Constants::isWebUrl()`
and past the hidden/`containsCodecheckYaml` rule alike. Writes go through
`CodecheckMetadataHandler::saveMetadata()` and `CodecheckAuthorMetadata`, which
enforce both; a second write path that did not is worse than none, so it was
removed rather than taught the rules — teaching it would have made a third
writer of the shape that #154 and #169 came from.

**That does not make the DAO a gate.** It reads `codecheck_metadata` with the
query builder, and so do `CodecheckMetadataHandler`, `CodecheckAuthorMetadata`,
the API controller, the ORCID services and the register comment — there is no
single owner of this table, only two write paths that happen to hold the
invariants. Routing those queries through the DAO so the rules have one home is
the larger change nobody has made.

**Which repository holds the `codecheck.yml` is recorded on the repository entry,
never as a position in the list.** It was an index (`repoWithCodecheckYaml`), and
nothing kept it in step with the list it indexed — the editorial form splices
entries out and `CodecheckAuthorMetadata::merge()` reorders them, so it came to
name a repository nobody chose, in three places at once: what the article page
claims, what publication validation fetches, and what is deposited in the public
register (#154).

`classes/Submission/CodecheckRepositories.php` owns the rules for reading that
blob — `publicEntries()`, `publicUrls()`, `selectedUrl()`, `publicSelectedUrl()`,
`selectedIsPrivate()`, `unusableUrls()`, `newUnusableUrls()`,
`withoutNewUnusable()`, `withOneMarked()` — and the article page, `buildYaml()`, the
publication validator, the register deposit and the register issue all go
through it. Note the deliberate asymmetry: `publicEntries()` withholds hidden
repositories because it feeds readers, while `selectedUrl()` does not, because a
repository may be private and still hold the `codecheck.yml` that has to be
fetched. Fetching is not publishing, though: `publicSelectedUrl()` is the
question anything writing the chosen repository somewhere public must ask. The
register deposit asked `selectedUrl()` and committed the answer to a public pull
request, so a private repository withheld everywhere else was named in
`register.csv` (#169). It now asks `publicSelectedUrl()`, and
`CodecheckPublicationValidator` blocks publication while the marked repository
is private — the editor un-marks it or marks a public one. **Nothing is
substituted**: another repository in the `Repository` column would name
something nobody checked, which is wrong where no reader can see it. A
submission with *no* repository marked at all is left to the extended check, as
before.

The pre-#154 index is **not** read back at runtime: the migration converts it, and
accepting both shapes at once means a half-converted record resolves differently
depending on which reader asks, which is the original defect again.

Submission-level fields (`codecheckOptIn`, `retrieveReserveCertificateIdentifier`,
`codeRepository`, `dataRepository`, `manifestFiles`, `dataAvailabilityStatement`) live in
OJS's submission/publication settings via schema extension — a *separate* store from
`codecheck_metadata`.

### CODECHECK Register / GitHub integration (`classes/CodecheckRegister/`)

- `CodecheckGithubRegisterApiClient` — knplabs/github-api client; fetch/create/update
  register issues, fetch labels, and `depositRegisterRow()` (branch + commit + PR against
  the configured register repo)
- `CodecheckGithubRegisterIssue` — builds issue title/body/labels markdown
- `CertificateIdentifier` / `CertificateIdentifierList` — `YYYY-NNN` identifiers parsed
  from register issues; reservation picks the next free one
- `CodecheckIssueLabels` — `fromDB()` / `fromApi()` (`https://codecheck.org.uk/register/venues/index.json`)

Credentials come from **plugin settings**
(`Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN`, `…_REGISTER_ORGANIZATION`,
`…_REGISTER_REPOSITORY`), not from `.env`. A vestigial
`Dotenv::createImmutable()` call remains at
`CodecheckGithubRegisterApiClient.php:20-21`; CI writes a dummy `.env` for it.

Register deposit fires on `Publication::publish`, is gated by
`CODECHECK_REGISTER_DEPOSIT_ENABLED`, requires a reserved certificate and a repository
flagged as containing `codecheck.yml`, re-verifies the `codecheck.yml` is fetchable, and
**never blocks publication on failure** (logged only).

### Publication validation

**`Publication::validatePublish` is not on every publishing path.** The REST
submissions controller calls it before publishing; `IssueGridHandler::publishIssue()`
and the `PublishSubmissions` scheduled task call `Repo::publication()->publish()`
directly, with no validation. So an editor publishing a whole issue is never shown
a CODECHECK error, whatever the validator would have said. This is accepted rather
than worked around: every gate here is therefore backed by a refusal in the thing
it protects — the register deposit refuses a private selection on its own
(`Publication::publish` fires on all paths), so nothing private is disclosed on
the unvalidated routes; the editor simply gets a log line instead of a message.
Anything new that *must* not happen belongs in the same shape: a check in the
validator for the message, and a refusal at the point of action for the guarantee.

`CodecheckPublicationValidator` runs four checks when the submission is opted in:
the repository marked as holding the `codecheck.yml` is not hidden (#169 — it
would be named in the public register), current status ∈ configured allow-list,
generated YAML parses, and (only when
`CODECHECK_PUBLICATION_VALIDATION_EXTENDED` is on) `codecheck.yml` in the selected
repository is fetchable and its paper title matches the OJS title. Errors are merged into
OJS's publish-validation error array and block publishing.

**The list short-circuits — `validatePublication()` returns at the first check
that fails — so the order is load-bearing.** `validateCodecheckStatus()` can
return false while recording no error at all (no status row, extended validation
off), which ends the run with an empty error array and lets the publish through.
The #169 check is first for that reason: a gate placed after it would silently
not run on a default install.

### Status system

`CodecheckStatusHandler` (static, `codecheck_status` table) — `getCurrentStatusData`,
`getStatusDataHistory`, `updateStatus`, `automaticStatusUpdate`. Status values are
**locale keys**, listed in `Constants::CODECHECK_STATUSES` (pending → needs/assigned
codechecker → stalled → completed → published certificate). `CodecheckPlugin::addCodecheckStatusLocalizations()`
pushes translations into `pkp.localeKeys` for the Vue layer.

Note the README's "Status Levels" table (pending/in-progress/complete from
`CodecheckReviewDisplay.vue`) describes a *different, older* three-state display and does
not match `Constants::CODECHECK_STATUSES`.

### Settings

`classes/Settings/{Actions,Manage,SettingsForm}.php` + `templates/settings.tpl`
(Smarty/FBV form, not a Vue form). All keys are in `classes/Constants.php`. Anything
added to the form must be added in three places: `Constants`, `SettingsForm::initData()`
+ `readInputData()`, and the template. `SettingsForm::execute()` also warns when the
configured register repo lacks a `register.csv` or the `id assigned` label, and
says so as one "could not be read" warning when the repository answers neither.

**A verb added to `Manage::execute()` must bring its own CSRF check.** PKP's
`manage` operation has none — `SettingsPluginGridHandler` authorises it (site
admin or manager, `PluginAccessPolicy`), but unlike `enable`/`disable` it never
calls `checkCSRF()`. The `settings` verb is covered because `SettingsForm`
registers `FormValidatorPost` + `FormValidatorCSRF`, so the save is refused
inside `validate()`. The `resetSchema` verb was the one that did not, and it
dropped every CODECHECK table; it was removed entirely in #131, so today every
verb there is covered — which is a property to keep, not one to rely on.

Note the "three places" is really four for anything with a non-trivial default or a
list of options: `fetch()` assigns the template variables the field renders from.
`settings-roundtrip.cy.js` derives its field list from the rendered form, so a new
setting is covered there automatically — but only if it actually renders.

**A setting with a non-obvious default has one recorded default.**
`Constants::CODECHECK_SETTING_DEFAULTS` holds the name and the value;
`CodecheckPlugin::getSettingWithDefault()` reads through it and
`writeDefaultSettings()` writes from it. `CODECHECK_REGISTER_DEPOSIT_ENABLED` is
there because two readers disagreed about the unset state — the form rendered
the box ticked, the deposit read it as off (#177);
`CodecheckPlugin::isRegisterDepositEnabled()` is the single reader now, used by
the form, by `depositToRegister()` and by the publication gate.

**A journal acquires the plugin in three ways, so the write happens in three
places**: `setEnabled()` (the journal enables it), the `Context::add` hook (a
journal created while it is already enabled — that never calls `setEnabled()`),
and the install migration, which writes for journals that already have the
plugin *enabled* and no others. The dump carries the row by hand, because it has
`enabled` baked in so `setEnabled()` never runs there.

**`Context::add` is registered outside the `getEnabled()` block in
`register()`,** and must stay there: creating a journal is a site-scoped
request, so `getEnabled()` reads the *site* row, which a per-journal install
does not have. Registered inside, the hook never attaches at all. PKP's own
`installContextSpecificSettings` sits outside any enabled check for this reason.

**The row is for visibility; the reader is the guarantee.**
`getSettingWithDefault()` resolves the default for anything the three writers
miss, and a null context resolves to it as well, because "no journal to ask" and
"nothing stored" are the same answer. Two consequences worth knowing: the
writers only ever fill a gap and never reconcile, so **changing a value in
`CODECHECK_SETTING_DEFAULTS` does not reach a journal that already has the row**
— that is the price of having no unset state, and a real default change needs an
upgrade migration. And `getSettingWithDefault()` treats only `null` as unset: a
stored `''` or `[]` is what a switched-off boolean looks like, so a general
"empty means unset" rule would switch every default-on switch back on. A setting
that must fall back on an empty value says so where the emptiness means
something, not in the shared reader.

`CODECHECK_SHOW_AVAILABILITY_STATEMENT`, `CODECHECK_SHOW_DASHBOARD_COLUMN` and
`CODECHECK_SHOW_IN_TOC` — all **on** when unset, the feature being present until
a journal switches it off — joined the map for #178 and have no read-site
fallback left in PHP. They now get a written row on enable and on context
creation, so changing one of their defaults needs an upgrade migration.

**`CODECHECK_ENABLED_CONFIG_VERSIONS` deliberately did not join it**, although
it has the same shape. A recorded default is a *written* default, and this one
is expected to change: a row frozen at today's stable specification would still
offer `1.0` long after `1.1` replaced it, with no way for a migration to tell
that row apart from a deliberate choice. It resolves its default in
`getEnabledConfigVersions()`, which is its only reader, and which also owns the
two rules that are not the default — narrowing the stored list to the versions
the plugin knows (`CodecheckPlugin::narrowConfigVersions()`, which the settings
form applies on save as well, so the rule is one function rather than two
copies) and treating a list that narrows to nothing as nothing stored. **A
setting is a candidate for the map only if its default would still be right in
five years.**

The Vue layer cannot read a PHP constant, so `resources/js/main.js` carries its
own `showDashboardColumn` fallback. It stays unreachable only because
`callbackTemplateManagerDisplay()` injects the dashboard config on **every**
dashboard view. It once injected for `op == 'editorial'` alone, and
`mySubmissions` and `reviewAssignments` render the same Pinia store from the
same bundle, so the JS default decided there — showing the column to authors and
reviewers in a journal that had switched it off (#178).

**`CODECHECK_BADGE_HEIGHT` deliberately did not join the map either, and for
the opposite reason to the config versions**: the map abolishes the *unset*
state, and unset was never this setting's problem. The form stored `(int) ''` —
zero — for a cleared field and showed that back, while `Badge.php` read
`0 ?: 24` and rendered 24, so the disagreement was about a value that is
*stored*. A reader therefore has to judge the stored value whatever the map
says, and a written row would make a later change to a cosmetic pixel value
need an upgrade migration. `Constants::normalizeBadgeHeight()` is that judgment
and the only place it is spelled out — nothing recorded, emptied, zero,
negative or non-numeric is not a height, and anything outside
`CODECHECK_BADGE_HEIGHT_MIN`/`_MAX` is held to that range — applied on save in
`SettingsForm::execute()` and on read in `Badge::getStyle()`, as the badge text
colour's rule is. **The `min`/`max` on the form field is presentation**, drawn
from the same two constants: PKP submits the settings form through its own
handler, so the browser never refuses a value, and the height goes into a
`style` attribute on the article page and the issue TOC. The rule of thumb: **the map for a setting whose absence is
the ambiguity, a normaliser for one whose stored value can be nonsense.**

The settings below are **not** in the map. The two bullets cannot be, their
default being a localised string rather than a value a constant can hold; the
rest simply have not been migrated yet and still carry the two-reader shape that
produced #177 — `CODECHECK_MODE` (`'opt-in'`), `ORCID_API_TYPE`
(`ORCID_API_TYPE_SANDBOX`, six readers) and `CODECHECK_BADGE_TYPE`
(`'codeworks'`):

- `CODECHECK_AVAILABILITY_STATEMENT_HEADING` falls back to the localised
  `plugins.generic.codecheck.dataSoftwareAvailability` when cleared, so the article
  page never renders an empty heading. `CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT`
  is the ordinary kind, defaulting to **off**: an article with no statement says
  "No {$heading} provided for this work." rather than dropping the section, since
  silence cannot be told apart from a journal that never asked
- `CODECHECK_BADGE_TEXT` falls back to the localised
  `plugins.generic.codecheck.badge.textOnly` when cleared, so a journal showing text
  instead of a badge never shows nothing. `CODECHECK_BADGE_TEXT_COLOR` falls back to
  `CODECHECK_BADGE_TEXT_COLOR_DEFAULT` for anything that is not a six-digit hex
  colour — it is written into a `style` attribute on a public page, so it is
  validated both on save and on read, as OJS's own theme colour option is
  (pkp/pkp-lib#11974). That rule is `Constants::normalizeBadgeTextColor()`, the
  same shape as the height's: it was four copies — this pattern twice and a
  weaker `?:` fallback twice, which disagreed about a stored non-colour

`CODECHECK_ENABLED_CONFIG_VERSIONS` defaults to `CODECHECK_DEFAULT_CONFIG_VERSIONS`
— `1.0` alone, not every known version — so a journal that has not chosen records
checks against the current stable specification rather than a moving target.

`Constants::CODECHECK_DEFAULT_CONFIG_VERSIONS` and `getConfigSpecUrl()` are mirrored by
`CODECHECK_DEFAULT_CONFIG_VERSIONS` / `CODECHECK_SPEC_URL` in `CodecheckMetadataForm.vue`.
The JS copies are only the pre-load fallback — the authoritative list arrives with the
`GET metadata` response as `settings.enabledConfigVersions`. `tests/ConstantsUnitTest.php`
pins the PHP side. `CodecheckMetadataHandler::buildYaml()` emits the version recorded
for the check through the same helper, so the generated file declares the specification
the form was filled in against.

Anything rendered outside `{fbvFormArea}` in `settings.tpl` loses the bordered box:
`#codecheckSettings #codecheckSettingsArea .section` is what draws it. That is what
left the badge / logo group looking unlike every other group until it was moved
inside. Multiple-choice settings use `.codecheck-choice-list`; there is no dropdown
on the settings form any more. A field belonging to one radio option — the custom
badge URL, the text shown instead of a badge — is a `.badge-dependent-field`, shown
and hidden by `toggleCustomBadgeUrl()` in the template.

`classes/FrontEnd/Badge.php` resolves the badge settings (image URL, text, colour,
height, and where the badge links to) for both `ArticleDetails` and `IssueTOC`,
which used to carry a copy each of the `match` on badge type.

`CODECHECK_BADGE_LINK_TARGET` picks between the certificate's register page
(`Constants::getRegisterCertificateUrl()`, built from the `YYYY-NNN` identifier)
and its DOI; whichever the journal did not pick stands in when the preferred one
is missing, because a badge linking nowhere is worse than one linking to the
second choice.

`SettingsForm::execute()` reaches out to GitHub through
`checkRegisterRepository()` — but only when the register organisation or
repository actually changed. It makes **two** unauthenticated requests, for
`register.csv` and for the `id assigned` label, and they count against GitHub's
60/hour per-IP limit, so do not move that call back onto every save. The label
probe is `CodecheckGithubRegisterApiClient::repositoryHasLabel()`, the same one
the reservation uses, so the settings form and the reservation cannot come to
disagree about whether a register is usable (#129).

### Logging

Use `CodecheckLogger::debug|info|warning|error()` (`classes/Log/CodecheckLogger.php`) — writes
`[codecheck][level] …` via `error_log()`. Do not add bare `error_log()` calls; a few
legacy one remains, inside a commented-out block in `CodecheckPublicationValidator`.

### i18n

- `locale/en/locale.po` — ~280 keys, all prefixed `plugins.generic.codecheck.`
- `registry/uiLocaleKeysBackend.json` — **generated** by `i18nExtractKeys.vite.js` during
  `npm run build` by regexing `t('…')` / `tk('…')` out of `.vue`/`.js`. Never hand-edit;
  rebuild instead. New UI strings need a `.po` entry *and* a rebuild.

**One sentence is one key.** Never assemble a sentence from several keys, and never
concatenate a key with markup or a link label in the template. Word order, punctuation
placement and direction all differ between languages, so a message split into
"prefix" + link + "." can only ever come out right in English and breaks outright in
right-to-left languages. Put the whole sentence in one message with a `{$name}`
placeholder and pass the variable part in:

```po
msgid "plugins.generic.codecheck.form.intro"
msgstr "… For background and technical details see {$specLink}."
```

```js
// CodecheckMetadataForm.vue
this.t('plugins.generic.codecheck.form.intro', {specLink: '<a href="…">' + label + '</a>'})
```

When the parameter carries markup, the result has to be rendered with `v-html`
(Vue) or an unescaped Smarty variable — so build the markup in code and keep it out
of the translatable string. `CodecheckPlugin.php` does the same for
`{$codecheckLink}` on the submission form. This follows PKP's
[Semantics](https://docs.pkp.sfu.ca/translating-guide/en/coders#semantics) guidance.

Reference documentation:

- [PKP Translating Guide](https://docs.pkp.sfu.ca/translating-guide/en/) — locale file
  format, and [the coders' chapter](https://docs.pkp.sfu.ca/translating-guide/en/coders)
  on writing translatable strings
- [PKP Plugin Guide](https://docs.pkp.sfu.ca/dev/plugin-guide/en/) — plugin structure,
  hooks, settings forms and the release/packaging expectations

### Color scheme

CODECHECK brand: primary `#008033`, dark `#006629`, light `#e8f5e8`. Documented in
README.md; keep `css/codecheck.css` and inline component styles consistent.

## Testing

### Layout

```
tests/                       PHPUnit (31 files, 276 tests)
  bootstrap.php              PKP_STRICT_MODE + BASE_SYS_DIR (OJS_ROOT or ../../../..)
  PKPTestCase.php            local stub extending PHPUnit TestCase
  FakeTranslator.php         minimal translator so __() works without booting OJS
  phpunit.xml                default config (testdox, whole dir)
  phpunit_with_coverage.xml  + coverage HTML into tests/results/
  runTests.sh                wrapper; honours OJS_ROOT; --coverage-report=true|false
  CodecheckPluginUnitTest.php
  CodecheckRegisterUnitTests/  CertificateIdentifier(List), GithubRegisterApiClient,
                               GithubRegisterIssue, IssueLabels
  DataStructuresUnitTests/     UniqueArray, UniqueIdentifierArray
  FrontEndUnitTests/           ArticleAvailability, ArticleDetails, Badge, IssueTOC
  LogUnitTests/                CodecheckLogger
  ApiUnitTests/                CodecheckApiControllerRoles, CodecheckApiControllerRoutes,
                               IdentifierParameterValidator, JsonResponse
  MigrationUnitTests/          I154_MoveCodecheckYamlFlagOntoRepository (the
                               index-to-flag conversion, tested without a database)
  SettingsUnitTests/           Actions, Manage
  SubmissionUnitTests/         AvailabilityStatementField, CodecheckRepositories,
                               CodecheckSubmissionDAO, CodecheckSubmission, Schema
  WorkflowUnitTests/           CodecheckMetadataHandler, CodecheckPublicationValidator,
                               CodecheckYamlValidator

cypress/
  support/component.js         mounts via @cypress/vue, imports css/codecheck.css
  support/pkp-mock.js          fake window.pkp (localize, modal, registry, const) — import
                               this first in every component spec
  support/e2e.js               cy.ojsLogin(), cy.getCsrfToken(), swallow uncaught exceptions
  support/component-index.html
  tests/component/*.cy.js      6 specs, 79 tests
  tests/e2e/*.cy.js            11 specs, 63 tests
                               yaml-generation, article-sidebar-setting,
                               issue-toc-setting, issue-toc-badge,
                               private-repository, publication-validation,
                               settings-roundtrip, status-handler
  tests/visual/ui-screenshots.cy.js  screenshot pass — `make screenshots`

dev/
  inspect.mjs                Playwright page inspector -> dev/out/
```

### Component tests (the reliable suite)

`npm run test:component` — **passes locally with no OJS, no database, no build step**
(79/79, ~20 s). Cypress mounts the `.vue` sources directly through Vite and stubs the
API with `cy.intercept`.

Covered: metadata form load/render, manifest files add/remove/comment, repository list
add/remove + private flag, certificate identifier reservation + labels, required-field
validation, YAML preview gating, codechecker modal, review display states, data &
software availability field, the config version selector and the author's availability
statement in the read-only panel.

`cypress/support/pkp-mock.js` reads the real `locale/en/locale.po` and its `t()`
behaves in two ways on purpose:

- a message **without** placeholders resolves to the locale key, so specs assert on a
  stable identifier instead of English copy that changes whenever wording is edited
- a message **with** placeholders resolves to the translated text with the parameters
  substituted, and **throws** when the message and the call site disagree — a missing
  parameter, an extra one, or a plugin key with no entry in the `.po` at all. That is
  what stops a renamed placeholder from silently rendering `{$specLink}` to the user

Keys outside `plugins.generic.codecheck.` come from OJS's own locale files
(`common.loading`), so they are passed through unchecked.

**The mock's `useModal` renders a real dialog into the document** —
`.pkp-mock-modal` with one `.pkp-mock-modal__action` button per action — so a
confirmation can be answered in a spec rather than stubbed. It used to answer
only the first half of `const {useModal} = pkp.modules.useModal`, which made
`canUsePkpModal()` true and every modal path throw, so no spec could reach one.
`cypress/support/component.js` clears leftover dialogs between tests, because
`mount()` does not clear `document.body`.

Confirmations in `CodecheckMetadataForm.vue` go through
`askForConfirmation({title, question, onConfirm, onCancel})`, which uses that
modal and falls back to the browser's dialog where it is unavailable. A new
`confirm()` in this component is a bug, not a shortcut.

Not covered: `CodecheckStatusForm.vue`, `CodecheckGithubIssueDisplay.vue`, the
`storeExtend` wiring in `main.js` (menu injection, dashboard column, file-manager
columns), and the wizard DOM-scraping classes.

### E2E tests

`make test-e2e` — 63 tests across 11 specs, driving a real OJS instance.

**Several specs share submission fixtures, and each must restore what it
changes.** Submissions 8 and 9 are written by `publication-validation`,
`reviewer-rights` and `status-handler`; submission 10 is deliberately untouched
by the whole suite, which is what lets `status-handler` assert that it has no
status history. The status table is append-only with no delete endpoint, so
"restore" means recording the status the dataset ships with, not removing rows —
and assertions on history must be relative ("at least two", "the newest is X")
rather than on an exact count.

`make test-e2e-reverse` runs the same specs in the opposite order, which inverts
every dependency between them: a spec that quietly relies on what an earlier one
left behind fails there and nowhere else. Worth running after touching a spec
that writes. It is deliberately a separate target rather than randomising the
normal run — an order that changes every time turns a coupling bug into a flake
nobody can reproduce.

`make test-e2e-shuffle` covers the orders neither of those two reaches, without
giving that property up: the order comes from a seed, which is printed before
the run and taken back as `make test-e2e-shuffle SEED=…`, so an order that finds
a coupling bug can be replayed exactly. `dev/shuffle-specs.mjs` does the
shuffling. The normal run stays deterministic; this is the target to reach for
after touching a spec that writes, when reverse order alone is not convincing.

**A red run here has usually had a specific cause.** During the #50 work a whole
run went red three times: once because a deletion had left the plugin fatal, and
twice because the dev server was down. "The suite is flaky" was the wrong first
hypothesis on each occasion. Check that the server answers and that the plugin
loads before concluding anything about the tests.

- `yaml-generation.cy.js` — YAML preview vs. download parity, preview-button gating
- `article-sidebar-setting.cy.js` — the `showArticleSidebar` setting, driven through
  the real settings form; restores the setting afterwards so the rest of the suite is
  unaffected
- `private-repository.cy.js` — a repository flagged private is visible to editors in
  the workflow form and absent from the published article and the issue TOC
- `issue-toc-setting.cy.js` — the `showInTOC` setting, and that it is independent of
  the article sidebar
- `cy.setCodecheckSetting(fieldId, enabled)` (in `cypress/support/e2e.js`) drives a
  plugin checkbox setting through the real settings form; specs that toggle settings
  restore them in an `after()` hook.
- `issue-toc-badge.cy.js` — what the badge renders: image variants, height, the
  text-only form with its configured wording and colour, and where it links.
  Each variant is also captured to `cypress/screenshots/`, so the settings can be
  looked at rather than inferred
- `publication-validation.cy.js` — the CODECHECK gate on publishing, driven
  through the endpoint OJS publishes with. Most of it uses submission 8, in
  review with no issue, which OJS refuses whatever CODECHECK says, so those
  tests assert on which errors come back. One test is the other half: it
  unpublishes submission 5 — production stage, assigned to an issue, nothing for
  OJS to object to — shows CODECHECK blocking it, then accepts the status and
  really does publish it, putting the journal back as it was. It calls
  `ensurePublished()` first rather than trusting the state it finds, and
  switches the register deposit off so `Publication::publish` does not reach
  for GitHub. One more test marks submission 8's repository private and
  restores it afterwards: that is the #169 gate, and it has to run against the
  database because the check reads the stored blob
- `status-handler.cy.js` — the status API end to end: pending until recorded,
  newest record wins, append-only history newest first, rejected payloads, and
  the automatic update that picks a status from whether a codechecker is
  assigned and then stops deciding once a person has. It writes rows nothing
  deletes — the table is an append-only log with no delete endpoint — so it
  restores only the *current* status of the submissions it touches
- `settings-roundtrip.cy.js` — every field the settings form renders keeps its value
  across a save. Derives the field list from the rendered form, so a setting added
  without being wired into `readInputData()`/`execute()` fails here automatically
- `availability-statement-editing.cy.js` — the availability statement on OJS's
  publication Metadata form: that the field reaches the form OJS serves to the
  workflow, sits in a group the renderer draws (a field whose `groupId` is not one
  of the form's groups is silently dropped), carries the recorded value, saves
  through the form's own endpoint and reaches the article page — and that it is
  not on the other publication forms. Which form the field lands on is unit
  tested; the round trip belongs to OJS, which is why it is pinned here

Requires `make serve` running with the dataset loaded; `make setup` satisfies the
rest (plugin enabled, `public/build/` present, composer deps installed, `admin`/`admin`).

`CodecheckPublicationValidator` is covered the same way as `IssueTOC`: the
opt-in gate and the no-submission case are unit tested, and the checks that read
the database are covered by `publication-validation.cy.js`. **Publishing goes
through the REST API**, where `$request->getRouter()->getHandler()` is null —
anything that reaches for the authorized submission that way fatals inside the
hook, and PKP swallows it with "failed to handle the hook". Take the submission
from the hook arguments instead (`$args[2]`).

Still uncovered: opt-in, the submission wizard, and register deposit.

### PHPUnit tests

`make test-php` — 276 tests, green, none skipped.

PHPUnit needs an OJS installation: the tests load OJS classes and the runner uses the
PHPUnit shipped in `lib/pkp`. Both `runTests.sh` and `bootstrap.php` honour `OJS_ROOT`,
falling back to the four-levels-up layout CI uses. `OJS_ROOT` is mandatory here because
the plugin directory is a symlink — see "Local development environment".

`tests/bootstrap.php` binds a `FakeTranslator` into Laravel's container so `__()`
works without booting the application; it returns the locale key rather than a
translation, so assert on structure and identifiers, not on translated text.

Nothing is skipped. Anything needing a constructed `SettingsForm` was deleted
rather than skipped: `PKP\form\Form::__construct` resolves journal locales
through a database-backed facade, so building one is an integration test, and
the `*-setting.cy.js` e2e specs already open the settings form, change a value
and save it. **Prefer an e2e test over booting the application inside PHPUnit.**

The API's role sets are covered by `CodecheckApiControllerRolesUnitTest`, and the
absence of the removed file endpoints by `CodecheckApiControllerRoutesUnitTest`.

Everything else about the API is covered end to end rather than in PHPUnit, and
deliberately so: routing, CSRF and authorization are now PKP's, not the plugin's,
so testing them here would test OJS. What the plugin still owns — which role may
reach which route, and whether a reviewer is held to the submission they were
assigned to — is pinned by `reviewer-rights.cy.js` against a running instance.

The endpoint *bodies* are not unit tested: nearly all of them reach the database
or GitHub in their first lines, and the e2e specs cover them through real HTTP.
The routes themselves cannot be enumerated in PHPUnit either — they are
registered through Laravel's `Route` facade, which needs a booted application.

Worth knowing before adding coverage there: **OJS's own API router answers routes
and methods the plugin's endpoint table does not cover, before the handler is
reached.** Verified against a running instance — an unknown route and a valid
route with the wrong method both return OJS's `api.404.endpointNotFound`.

Not covered by PHPUnit at all: the `CodecheckApiController` endpoint bodies,
`CodecheckRegisterDepositService`, `SubmissionWizardHandler`, `CurlApiClient`,
migrations, `CodecheckPageHandler`. All of
them reach the database, the network or a booted application in their first few lines,
which is what makes them awkward rather than merely unwritten. `CodecheckStatusHandler`
is the same shape — every line a database query — and is covered by
`status-handler.cy.js` instead.

`IssueTOC` is covered up to the point where it needs the database: the setting and
opt-in gates are unit tested with a stub application in `PKP\core\Registry`, and what
it renders is covered by `issue-toc-badge.cy.js`. `TemplateManager` cannot be mocked in
these tests — it extends Smarty, whose class body requires plugin files off a relative
include path that only resolves inside a booted OJS, so a hand-written stand-in is used
instead.

### CI (`.github/workflows/tests.yml`)

Three jobs on push/PR to `main`:

1. **PHPUnit** — checks out `pkp/ojs@stable-3_5_0` + plugin into
   `ojs/plugins/generic/codecheck`, MySQL 8 service, writes a dummy `.env`, runs
   `runTests.sh` twice (plain + coverage), uploads `tests/results/`.
2. **Cypress component** — plain `npm ci && npm run test:component`.
3. **Cypress e2e** — full stack: OJS + `pkp/datasets` + this repo's
   `testData/stable-3_5_0-codecheck` dump, `loadfiles.sh`, OJS npm build, plugin npm
   build, Apache + mod_php on :8888, then `npm run test:e2e`.

`.github/workflows/lint.yml` is a fourth job on the same events, in its own
workflow: the coding-standard check and a `php -l` pass. See "Code style".

### Live tests against the CODECHECK register

Two things cannot be tested against a stub, because the point of them is that
GitHub receives something: reserving a certificate identifier (which opens an
issue in the register) and recording a status (which comments on that issue,
#150). `cypress/tests/live/register-issue.cy.js` covers both against
`codecheckers/testing-dev-register`, and **`dev/live-register-tests.md` is the
procedure** — read it before running one.

- `make test-live GITHUB_TOKEN=…`, with `make serve` on the side.
- The spec is outside `specPattern` (`cypress/tests/e2e/**`) so it is never in a
  suite, and refuses to run without `CYPRESS_live=1`.
- **Every run creates an issue in the testing register and nothing deletes it.**
  Runs accumulate on purpose; that is why the testing register is a separate
  repository.
- The token lives in `plugin_settings` and is not in the dataset, so
  `make db-reset` wipes it. `codecheckGithubUpdateFields` must contain
  `updateStatus` or the status comment is skipped and the test proves nothing.

**The PAT these tests use** is a *fine-grained* token, not a classic one:

| | |
|---|---|
| Resource owner | `codecheckers` (the organisation, not a personal account) |
| Repository access | only `codecheckers/testing-dev-register` |
| Issues | read and write |
| Contents | read |
| Pull requests | read and write |
| Workflows | read and write |

Issues read/write is what opens the register issue and comments on it; contents
read is what lets the plugin see `register.csv`; pull requests read/write is for
the `register.csv` deposit, which opens a PR. A classic token with `public_repo`
also works but grants far more — every public repository the holder can push to.

**The value is never written into this repository.** It goes into
`plugin_settings` on the local instance and nowhere else; anyone running a live
test supplies their own. A token that has been pasted into a chat, a terminal
transcript or a log should be treated as spent and rotated.

### Live tests against the ORCID sandbox

`cypress/tests/live/orcid-deposit.cy.js` + `make test-orcid-live`, with
**`dev/live-orcid-tests.md` as the procedure** — read it before running one.
Same shape as the register live tests: outside `specPattern`, gated on
`CYPRESS_live=1`, and it leaves a real peer-review item on a sandbox record.

Three things about this are not guessable and cost a while to establish:

- **The deposit needs *Member* API credentials.** The plugin requests the
  `/activities/update` scope, which ORCID grants only on the Member API; the
  Public API gives `/authenticate` and `/read-public`. Sandbox Member
  credentials are a separate request form
  (<https://info.orcid.org/register-a-client-application-sandbox-member-api/>)
  and anyone may apply. With Public credentials the credentials check passes and
  the consent screen refuses the scope, so a passing credentials check proves
  very little.
- **ORCID's registration form refuses `localhost` as a redirect URI host**,
  whatever the scheme. `http://codecheck.lvh.me:8350/` is accepted and resolves
  to 127.0.0.1 with no hosts file. Registering just the host covers every path
  under it. OJS must then be *reached* by that name: `getBaseUrl()` builds from
  the request's Host header and scheme, and only falls back to `base_url` when
  host auto-detection fails, i.e. on the command line.
- **Behind a TLS terminator OJS still says http.** `getProtocol()` reads
  `$_SERVER['HTTPS']` and nothing else — no `X-Forwarded-Proto` — so `base_url`
  does not help. `make serve-https` runs `php -S` with `dev/https-router.php`,
  which sets that one variable, and `make serve-tls` puts socat in front.

### Secrets in `.env`, and rebuilding the database

Secrets live in `plugin_settings` and deliberately never in the dataset dump, so
`make db-reset` drops them. `.env` (gitignored, mode 600) is the durable copy
and `make db-credentials` writes it into the database — it is part of
`db-load`, so a reset restores them by itself. `make db-credentials-clear`
removes them again.

`dev/db-credentials.php` does the reading and writing rather than the Makefile,
for two reasons worth keeping: **phpdotenv parses the file**, the same parser
`CodecheckGithubRegisterApiClient` already applies to `.env` at file scope, so
one file cannot mean two things — and a malformed `.env` fails there with a
message instead of later as a fatal on a register request. And **prepared
statements write it**, because whether a backslash in a secret survives a
hand-escaped SQL literal depends on the server's `sql_mode`.

Do not source `.env` from shell: an apostrophe or `$` in a password is then
executed rather than read.

### Test data (`testData/stable-3_5_0-codecheck/`)

A PKP-datasets-shaped MySQL dump + article files for a "CODECHECK Demo Journal"
(path `codecheck`): 8 submissions (5 published across 2 issues, 2 in review, 1 submitted),
users `admin/admin`, `jmanager`, `seglen`, `dnuest`, `fostermann`, `rreviewer`
(the password is the username). `rreviewer` is assigned as a reviewer to
submission 9 and to nothing else, which is what `reviewer-rights.cy.js` needs. `testData/README.md` documents manual loading
via `pkp/datasets`' `tools/load.sh`.

The dump used to lack `codecheck_status`, `codecheck_issue_labels`,
`codecheck_orcid_tokens` and the `issue` column, and because
`codecheckplugin.enabled = 1` is baked in, `CodecheckPlugin::setEnabled()` never
fires on bring-up so the migration never ran to add them.

These are now baked into the dump itself, so a plain load produces a working
instance. The trade-off is that the dump's table definitions have to be kept in
step with `CodecheckSchemaMigration` by hand.

### Keep the test dataset in sync with data-structure changes

**Any change to the shape of data stored in `codecheck_metadata` — or to any
JSON blob inside it — must be applied to `testData/stable-3_5_0-codecheck` in
the same commit.** The dataset is a fixture, not an archive: it is what CI's
e2e job and every local environment load, so a format change that skips it
leaves the seeded articles silently broken while the tests still pass.

This has already happened once. Commit `efaf1ed` removed the comma-separated
`repositories` format without migrating the dump, so
`CodecheckSubmission::getRepositories()` returned `[]` for every seeded article
— and that was the branch calling `CodecheckLogger::warning()`, which did not
exist at the time, so viewing any seeded article page was fatal.

Columns are handled by upgrade migrations under `classes/migration/upgrade/`;
the dump has no such mechanism, so it must be edited directly. After changing
it, run `make db-reset && make test-e2e && make screenshots` and look at the
screenshots — an empty list renders as nothing at all and is easy to miss.

The dump's `config.inc.php` is also not directly usable: `files_dir` points at
`/Applications/MAMP/htdocs/ojs/files` and the DB block assumes `root/root@localhost`
on database `ojs`, while `base_url` is `http://localhost:8888/ojs`. CI patches these
with `sed`; any local setup must too.

## Local development environment

Everything is driven from the `Makefile` (`make help`). The plugin is developed
in this standalone checkout and **symlinked** into an OJS install next to it:

```
/home/daniel/git/codecheck/
├── ojs-codecheck/          this repo
└── ojs-350/                OJS 3.5.0-5 (created by `make ojs-install`)
    └── plugins/generic/codecheck -> ../ojs-codecheck
```

| | |
|---|---|
| OJS | `/home/daniel/git/codecheck/ojs-350` (3.5.0-5, tarball) |
| Server | `make serve` → http://localhost:8350 (`php -S`) |
| Database | `ojs_codecheck_350`, user `ojs`/`ojs` on `127.0.0.1:3306` |
| Journal | `codecheck`, admin `admin`/`admin` |

Notes that matter when touching this:

- **`OJS_ROOT` is required for PHPUnit.** PHP resolves `__FILE__` through the
  symlink, so `tests/bootstrap.php`'s default "four levels up" lands outside the
  OJS tree. `bootstrap.php` and `runTests.sh` honour `OJS_ROOT`; `make test-php`
  sets it. CI uses a real checkout, where the default still applies.
- **`Hook::run()` and `Hook::call()` hand arguments to callbacks differently.**
  `Hook::call($name, $args)` delegates to `run($name, [$args])`, and `run()`
  spreads: `call_user_func_array($callback, [$hookName, ...$args])`. So a
  `Hook::call` callback takes `(string $hookName, array $args)` — which is every
  hook this plugin registers except one. `APIHandler::endpoints::plugin` is raised
  with `Hook::run('...', [$this])`, so its callback takes the router as its own
  parameter: `(string $hookName, APIRouter $router)`. Getting it wrong is
  invisible — the TypeError is thrown inside the hook, PKP swallows it, and the
  request falls through to OJS's `api.404.endpointNotFound`, which reads like a
  routing problem. The server log is the only place it appears.
- **Repointing the plugin symlink does not take effect for up to two minutes.**
  `realpath_cache_ttl` is 120s, so a long-running `php -S` keeps resolving
  `plugins/generic/codecheck` to the previous target. Tests run just after a swap
  silently mix old and new code. Restart the server after swapping.
- **A git worktree cannot be tested without repointing the symlink.** OJS resolves
  `APP\plugins\generic\codecheck\…` through `plugins/generic/codecheck`, which
  points at the main checkout — so PHPUnit run *from* a worktree still loads the
  main checkout's classes while using the worktree's test files. The result is
  quietly wrong rather than an error: a test written against worktree code fails
  against main's. Point the symlink at the worktree for the run and put it back
  afterwards. The same applies to `make serve` and everything e2e.
- **The DB host must be `127.0.0.1`, not `localhost`** — mysqli reads
  `localhost` as a socket path.
- **OJS release tarballs ship without PHPUnit** (`--no-dev`). `make ojs-install`
  runs `composer install` inside `lib/pkp` to add it. That command exits
  non-zero on a tarball because the captainhook composer plugin cannot install
  git hooks; the Makefile tolerates it and verifies the phpunit binary instead.
- **Creating a new database needs a one-off root grant.** The `ojs` account has
  `CREATE ON *.*` but not `INSERT`, so `GRANT ALL PRIVILEGES ON <db>.*` must be
  issued from a root shell (`sudo mysql`) once per database. Documented in
  README under "Local development environment".
- **The test dataset is self-contained** — it carries the full CODECHECK
  schema and the `showArticleSidebar` setting, so `make db-load` needs no
  repair step and the plugin's migrations never run against it. That means
  schema changes must be written into the dump by hand; see `testData/`.
- **OJS 3.5 refuses to serve anything without `app_key`.** The config template
  ships it empty and Laravel's encrypter throws during bootstrap, so every page
  is a bare HTTP 500 with the reason only in the server log. `make ojs-config`
  runs `lib/pkp/tools/appKey.php generate` when it is missing.
- **Plugin settings are cached by Laravel's file cache** under
  `cache/opcache/`. Rows written straight into `plugin_settings` stay invisible
  until it is cleared — `make clear-cache` (folded into `make db-load`).
- **`cy.screenshot()` crops to the browser window, not the viewport**, and
  `cypress run` defaults that window to 1280x720. `cypress.config.js` sizes the
  window to the configured viewport in a `before:browser:launch` handler; without
  it every capture is silently cut off at 1280px wide.
- **CLI `--config` does not reliably override keys already set in the `e2e`
  block** of `cypress.config.js`, and fails silently when it doesn't.
  `specPattern` does get through; `viewportWidth`/`viewportHeight` and
  `screenshotsFolder` do not. The `CYPRESS_*` environment variables do win, so
  `make screenshots` and the CI job pass viewport and output directory that way.
- **The visual pass writes to `cypress/ui-screenshots/`, not
  `cypress/screenshots/`.** Cypress empties `screenshotsFolder` before every
  run, so sharing one directory means whichever suite runs second destroys the
  other's output.
- **`php -S` is single-threaded**, so any page that calls back into OJS
  deadlocks and Cypress hangs rather than fails. `make serve` sets
  `PHP_CLI_SERVER_WORKERS=8`.
- **Three independent display switches.** `plugin_settings.enabled` turns the
  plugin on; `showArticleSidebar` gates the reader-facing article sidebar in
  `ArticleDetails`; `showInTOC` gates the badge in `IssueTOC`. Their defaults
  differ on purpose: `showInTOC` treats unset as on (recorded in
  `CODECHECK_SETTING_DEFAULTS`), because the badge predates the setting and
  journals that never configured it should keep what they had,
  while `showArticleSidebar` treats unset as off. The test dataset sets
  `showArticleSidebar` explicitly and leaves `showInTOC` unset, so the
  default-on path gets exercised. That is on purpose and is why the three
  settings that joined `CODECHECK_SETTING_DEFAULTS` for #178 were *not* given
  rows in the dump: a real install has them written by `writeDefaultSettings()`,
  but a fixture that carried them would stop exercising the reader that is the
  actual guarantee.

### Inspecting the UI

Two paths, both needing `make serve`:

- `make screenshots` — Cypress pass over settings, dashboard column, workflow
  CODECHECK tab, article sidebar, issue TOC and info page; full-page PNGs into
  `cypress/ui-screenshots/`. Asserts only that pages load. Captures at
  1920x1200; override with `make screenshots SHOT_WIDTH=… SHOT_HEIGHT=…`.
  Also runs in CI, uploaded as the `ui-screenshots` artifact.
- `make inspect URL=…` / `node dev/inspect.mjs <url>` — Playwright; logs in,
  dumps screenshot + rendered HTML + console/network log (including failed
  requests and HTTP >=400) into `dev/out/`. Captures at 1920x1200 by default.
  This is the debugging tool — use it when you need the DOM or the console, not
  just a picture. Takes `--selector`, `--wait`, `--width`,
  `--height`, `--headed`, `--user`/`--pass`, `--no-login`. Falls back to the
  system Chrome when Playwright's bundled Chromium is missing or version-skewed.

Host toolchain: PHP 8.2.31 (+ xdebug, mysqli, intl, gd), Node 18.20.8,
npm 10.8.2, Composer 2.8.12, MariaDB 10.6 on 3306, Docker 29.6 / Compose v5.2,
Chrome + Chromium + Firefox, Cypress 14.5.4, Playwright 1.61.

## Working agreements

### Never commit — stage a changeset and propose the message

**Do not run `git commit`.** Committing is the author's act: it puts a name and a
message on a change, and it is the last point at which the change can still be
shaped. Prepare the commit instead, and hand it over:

1. **Look at the working copy first.** `git status --short` and `git diff --stat`,
   before staging anything. The working copy is shared: it may hold someone else's
   edits, an in-progress merge with conflict markers, a rebase, or a branch that is
   not the one the work belongs on. This has already happened in practice — a
   session found `feature/orcid-deposit` mid-merge with eight unresolved files
   while it was working on something else entirely.
2. **Stage only the files the change is made of**, by name:
   `git add path/one path/two`. **Never `git add -A` or `git add .`** — they sweep
   up whatever else is lying around, and the result is a commit that nobody can
   review because it contains two unrelated things.
3. **Say what was left unstaged and why**, if anything was: a modified file that is
   not part of the change is information the author needs, not noise to hide.
4. **Propose the commit message** as text, in the report — subject line and body,
   in the form described under Conventions, with the attribution trailer. Do not
   write it into `.git/COMMIT_EDITMSG` or a file unless asked.

The author then commits, amends the message, or splits the change. If a change
really is two changes, stage and propose them one at a time rather than asking for
one commit that does both.

This applies to `git commit` in every form, including `--amend` and `commit -a`.
Pushing, branching, merging and rebasing are likewise the author's to run unless
they ask.

### Never create GitHub issues without confirmation

**Always ask for confirmation before creating an issue, and show the full
title and the full body text you intend to post.** Not a summary of it, not a
description of what it will say — the exact text, so it can be corrected
before it exists. Wait for an explicit yes. An issue is published under the
repository owner's name and is visible to the whole project; a wrong or
half-thought-out one costs someone else's attention to read and to close.

The same applies to anything else published on someone's behalf: comments on
issues and pull requests, PR titles and descriptions, and review comments.
Show the text, get a yes, then post.

### Plans in `.claude/`

**When a task is fully completed, check `.claude/` for unimplemented plans and
propose the next task from one.** `ls .claude/plan-*.md` lists what is there.
Read the plan before proposing, and say which one the proposal comes from.
Each states its own status; treat "not started" or "not settled" as available,
and update the status when work on it begins or lands.

A plan earns its keep by recording what would otherwise be rederived: what was
already established by experiment, what turned out to be false, and what still
needs a decision rather than an implementation.

**Plans are deliberately not committed.** `.claude/` is gitignored, because a
plan is ephemeral — it describes a moment in the work and goes stale as soon as
the code moves. Keeping them out of the repository avoids a second set of
documentation to maintain and contradict. The consequence is that they are
local to one working copy: a fresh clone has none, and nothing in `.claude/` is
shared by pushing.

So anything that needs to outlive this working copy has to leave it:

- **A plan that is extensive, or that needs discussion or a decision from
  someone else, belongs as a comment on the related GitHub issue** — not in
  `.claude/`, where nobody else will see it. Post it there, and leave the local
  plan as a short pointer to the issue.
- Conclusions that should shape future work belong in this file.
- Anything user-visible belongs in `CHANGELOG.md`.

`.claude/ISSUE_CODE_IMPROVEMENTS.md` and `.claude/issue-65-update.md` are
earlier point-in-time reviews rather than plans. Several of their findings are
now fixed and at least one is stale — check against the code before acting on
them.

### Keep content changes and formatting in separate commits

**A commit that changes behaviour must not also reformat.** When a change ends
up carrying both — a rename that the formatter then re-wraps, a lint fix noticed
on the way, an empty docblock the sweep left behind — stage and propose them one
after the other: the behaviour first, then the formatting on top. A reviewer can
then read the first commit without hunting for the two real lines among fifty
re-indented ones, and `git log -p` on a file still answers what changed and why.

This is how `9435bd7` and `efd9ad2` were split by hand after being handed over
as one changeset. Since only one changeset can be staged at a time, hand over
the behaviour commit, and say what the follow-up formatting commit will contain.

### Run `/simplify` and `/code-review` on non-trivial changes

**Before handing over a non-trivial change, run `/simplify` first, then
`/code-review` on the result.** In that order: `/simplify` changes the shape of
the code, so reviewing before it means reviewing code that is about to be
rewritten. Both run against the working tree, before the change is staged for the
author and before the PR — not after a merge, where a finding costs a second
round trip.

A change is **non-trivial** when either is true:

- it adds or changes **40 or more lines** of `.php`, `.vue` or `.js`, counting
  `git diff --stat` insertions plus deletions and ignoring `locale/*.po`,
  `registry/uiLocaleKeysBackend.json`, `package-lock.json`, `composer.lock`,
  `CHANGELOG.md`, `testData/` dumps and anything under `public/build/`
- **or** it adds a class, a hook registration, an API endpoint, a migration, a
  plugin setting, or a column or JSON key in `codecheck_metadata` — at any size.
  These are the changes where the damage is structural rather than proportional
  to the diff

Below that, and for documentation, wording, a locale entry or a dependency bump
on its own: skip both.

Effort level, for `/code-review`:

| Change | Level |
|---|---|
| 40–300 lines, ordinary domain or UI code | `high` |
| over 300 lines | `max` |
| `api/v1/`, `classes/migration/`, the `Publication::publish` / `validatePublish` hooks, or `classes/CodecheckRegister/` — at any size | `max` |

`high` is the floor rather than the default `medium` because of what this
codebase is. There is no compiler and no static analysis in CI (issue #43 is
still open), so a wrong array shape, a null context or a renamed key is found at
runtime or not at all. PHPUnit cannot reach the endpoint bodies, the migrations
or anything that touches the database, so a large share of the PHP has no test
that would catch a regression. And the failure modes here are quiet: hook
argument arrays carry references, the API handler `exit`s, and PKP swallows a
TypeError thrown inside a hook — which is exactly how `validatePublicationHook()`
went months without ever running, and how `setupAPIHandler()` left OJS answering
every plugin API call with a 404. Neither showed up as a failing test.

Also run **`/security-review`** — separately from the above, whatever the size —
when a change touches the role sets or policies in `CodecheckApiController`, the file
download path resolution, the GitHub token handling, or any setting rendered
into an attribute or into HTML on a public page.

If a review's findings are declined rather than fixed, say why in the PR
description, so the next reader does not re-derive the same objection.

## Conventions

- PSR-12 — enforced, not aspirational: run `make lint` (or let the pre-commit
  hook from `make hooks` do it) before handing a change over. Speaking names,
  verbs in function names; document public methods/classes
- Vue SFCs use `<script setup>`-style composition where already present — match the file
- Every user-visible change belongs in `CHANGELOG.md`, under `[Unreleased]` in the
  section it fits (Frontend, Configuration, Under the hood, …). See "Writing the
  changelog" below for how much to write
- Release process, packaging (`package-plugin.sh`) and the API-extension recipe are in
  `README.md`. **Three files state the release and must agree**: `version.xml`
  (`<release>`, `<date>`), `CITATION.cff` (`version`, `date-released`) and the
  heading in `CHANGELOG.md`. A release that moves one and not the others is the
  defect `version.xml` already had once, when it claimed `0.0.0.0` in a tagged
  release — see "Releases and citation metadata" below
- `.claude/ISSUE_CODE_IMPROVEMENTS.md` and `.claude/issue-65-update.md` hold earlier
  code-review findings; several are still open (dual storage, schema mismatch, dead DAO)

## Writing the changelog

**One or two lines per change, stating the result.** `CHANGELOG.md` is read by
someone deciding whether to upgrade and by someone wondering why the plugin
behaves as it does — not by someone reviewing the work. So:

- **Report the result, not the route to it.** What the plugin does now, and —
  for a fix — what it did wrong, in one clause. Not what was tried first, not
  which hypothesis turned out to be false, not how many places the rule used to
  live in.
- **No internal detail.** Class and method names, hook names, column names,
  which file a rule moved to: all of it belongs in the code, in `CLAUDE.md` or
  in the issue. A reader upgrading a journal cannot act on
  `Constants::normalizeBadgeHeight()`. Name a user-visible setting, a page or a
  file the journal handles (`codecheck.yml`, `register.csv`) instead.
- **Point at the issue, every time.** `(#154)` carries the whole story for
  anyone who wants it, at no cost to anyone who does not. Several issues on one
  entry is fine; entries that share an issue can be merged into one line.
- **Lessons learned go on the issue, not in the changelog.** What was
  established, what turned out false, what the next person should know — that is
  a comment on the issue it came from. Ask before posting one: an issue comment
  is published under the repository owner's name (see "Never create GitHub
  issues without confirmation").
- **Say so when something needs preserving and there is no obvious home.** A
  conclusion that shapes future work but fits neither the changelog nor an
  issue — a constraint discovered, a trap in OJS, a decision that will be
  re-litigated — belongs in `CLAUDE.md`. If it is not clear where it goes or how
  much of it to keep, **tell the user rather than guessing**: writing it in the
  wrong place is how one paragraph becomes three contradictory ones.

The rewrite that established this cut `[Unreleased]` from 567 lines to 225
without losing a single change.

**Read it the other way round when picking work up.** Because the detail lives
on the issues, `CHANGELOG.md` is the index into them: when a feature misbehaves
or has to change, **find the feature's entries there first, then read the issues
they point at** — `gh issue view <n> --json title,body,comments`. That is where
the background is: what was decided and why, which alternatives were rejected,
what turned out to be false, and which defects the feature has already had. A
change made without it re-derives the same reasoning, or quietly reverts a
decision someone took deliberately. Several entries often share an issue, and an
entry naming two issues usually means the second explains the first.

The same applies to a bug report about behaviour you did not write: the entry
that introduced the behaviour names the issue that asked for it.

## Releases and citation metadata

`CITATION.cff` is what GitHub renders in the "Cite this repository" widget, and
GitHub validates it on push — a malformed file is worse than none, because the
widget then shows an error to anyone who wanted to cite the plugin. `cffconvert
--validate` checks it locally, but it pins `jsonschema<4` and will downgrade a
shared environment to get it, so GitHub's own validation is usually the better
check.

**At every release, three files state the version and must be moved together**:
`version.xml`, `CITATION.cff` and `CHANGELOG.md`. The release procedure in
`README.md` steps through `version.xml`; `CITATION.cff` carries the same two
values (`version`, `date-released`) and is the one most easily forgotten,
because nothing at runtime reads it and no test covers it.

**The Zenodo DOI is not in the file yet, and this is where it goes.** Issue #8
(beta release incl. Zenodo deposit) mints it. When it exists, add it to
`CITATION.cff` as

```yaml
identifiers:
  - type: doi
    value: 10.5281/zenodo.XXXXXXX
    description: The concept DOI for all versions
```

and use the *concept* DOI — the one Zenodo keeps stable across versions — not
the DOI of a single deposit, so a citation of "the plugin" does not pin the
reader to whichever release happened to be current. A version DOI can be added
alongside it per release if that is ever wanted, but the concept DOI is the one
that belongs in a file that ships in the repository.

**The author list is a human decision, not a derivation.** The current order
follows commit counts from `git shortlog -sne --all`, which is a stand-in rather
than an answer: authorship order, who counts as an author at all, and the
missing family name and ORCIDs for two of the three are things to ask the people
concerned. Email addresses are deliberately omitted although git history carries
them.

## Traps

- `public/build/` gitignored but required — rebuild after pulling or after JS edits
- `vendor/` required at file-scope `require` — `composer install` before anything PHP
- `registry/uiLocaleKeysBackend.json` is generated — never hand-edit
- `CodecheckSubmissionDAO` is the only DAO, and it only reads; the migration
  defines the schema
- Hook argument arrays carry **references** (`[&$page, &$op, …]`). Writing through
  `$args[n]` propagates to the caller even though `$args` itself is by-value — unit
  tests must build the array with references to model this (see
  `CodecheckPluginUnitTest::buildLoadHandlerArgs()`)
- `stageId: 999` is a sentinel for the CODECHECK workflow menu item, not an OJS stage
- Three independent display switches: `plugin_settings.enabled` turns the plugin
  on, `showArticleSidebar` gates the article sidebar, `showInTOC` gates the issue
  TOC badge. The latter two default differently — see the dev-environment notes
- Data-structure changes must be mirrored into `testData/` in the same commit —
  see "Keep the test dataset in sync with data-structure changes". The dump carries
  its own `CREATE TABLE`, so a column type change has to be edited there too
- Which repository holds the `codecheck.yml` is a `containsCodecheckYaml` flag on
  the entry, never a position in the list. Read the blob through
  `CodecheckRepositories`, not by hand — five readers used to decide it separately
  and drifted apart (#154)
- A repository cannot be both hidden and the one holding the `codecheck.yml`.
  `CodecheckMetadataForm.vue` refuses whichever of the two controls would create
  that state (and `validateForm()` refuses to save a record that arrived in it),
  `CodecheckPublicationValidator` blocks publication, and the register deposit
  refuses. The form is where the mistake is made, so it is where the message
  belongs; the other two are backstops (#169)
- A repository address is checked at both write boundaries — the editorial
  `saveMetadata()` and the author's wizard save — and the rule is
  `Constants::isWebUrl()`, mirrored in JS by `resources/js/isWebUrl.js`.
  `filter_var(…, FILTER_VALIDATE_URL)` is not enough, it accepts `javascript://…`.
  Anything rendering an address still checks the scheme itself, because a value
  stored before the rule existed is never rewritten (#154, #170)
- **A validation failure must never look like a removal.** An entry may only be
  removed by an act of removal. The wizard field therefore submits everything
  the author typed, invalid addresses included, and
  `saveWizardFieldsFromRequest()` refuses the save by writing into the
  `Submission::validate` hook's `&$errors[0]` under the key `repositories` —
  PKP's own model, where no form field filters its own payload and
  `FormComponent::$errors` carries the server's answer back to the field.
  Withholding the row instead was indistinguishable from a deletion and took
  the address already on file with it (#170). The hook must not write when it
  refuses: it runs during validation, so a save OJS is about to abandon must
  not have happened
- **The wizard's own autosave does not carry these fields.** It collects the
  fields of its `pkp-form` sections, and the CODECHECK section is raw markup
  from a template hook — so `CodecheckWizardManager.saveAuthorEntries()` PUTs
  `repositories` and `manifestFiles` to the submission endpoint itself, and
  renders the server's refusal under the field, because the wizard only renders
  errors for its own sections. A field whose textarea is absent from the DOM is
  left out of the body: an absent key means "unchanged", as it does to
  `Repository::edit()`, while an empty string means "remove them all"
- **The wizard's textareas are the author's complete list**, and
  `CodecheckAuthorMetadata::merge()` reads them that way: an entry marked
  `providedByAuthor` that does not come back is deleted, and anything that does
  come back becomes the author's. So the fields are seeded from the record by
  `CodecheckWizardManager.loadAuthorEntries()` with **only** the
  `providedByAuthor` entries (`resources/js/authorEntries.js`). Seeding with
  everything would hand the codechecker's additions to the author; seeding with
  nothing — what it did before #170 — made every save delete the author's own
  entries
- **Only what a save introduces is judged**, via
  `CodecheckRepositories::newUnusableUrls($incoming, $stored)`. Refusing every
  unusable address meant one bad value from the wizard refused every later save
  of that record, including one that changed only the summary, with nothing
  saying which field was at fault (#170). The author path drops what it would
  introduce and logs it; the editorial path answers 400
- The API handler `exit`s after serving; it bypasses PKP authorization policies and
  does its own CSRF + role check
