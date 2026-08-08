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

### Tests

Use the Makefile — it sets `OJS_ROOT` and the base URL for you:

```bash
make test              # component tests + PHPUnit (no server needed)
make test-component    # Cypress component tests — runs anywhere, no OJS needed
make test-php          # PHPUnit — needs the linked OJS install
make test-e2e          # Cypress e2e — needs `make serve` running
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
- `Schema::get::submission` → adds `codecheckOptIn`, `retrieveReserveCertificateIdentifier`
- `Schema::get::publication` → `classes/Submission/Schema.php` (wizard fields)
- `Form::config::before` → opt-in checkbox on the submission start form
- `Submission::edit` → persists `codecheckOptIn`
- `Submission::validate` → `saveWizardFieldsFromRequest()` writes wizard fields onto the publication
- `Dispatcher::dispatch` → installs `CodecheckApiHandler` for `api/v1/codecheck/*`
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

### Custom API (`api/v1/`)

`CodecheckApiHandler` is a hand-rolled router, installed from the `Dispatcher::dispatch`
hook and `exit`ing after serving. It is **not** a PKP API handler and does not
participate in PKP's authorization policies.

- Route matching: `preg_match('#api/v1/codecheck/(.*)#', …)` → `ApiEndpoint` lookup in
  `$this->endpoints[$method]`
- Auth: `X-Csrf-Token` header compared against `$request->getSession()->token()`, then
  `$user->hasRole($pkpRoles, $contextId)` using `CodecheckRoleManager`
  (`readMetadata` / `editMetadata` / `admin` role sets built in `CodecheckPlugin::setupAPIHandler()`)
- Responses via `JsonResponse::staticResponse([...], $httpCode)`

Endpoints: `GET labels|metadata|download|yaml|register|status|status/history`,
`POST identifier|issue|metadata|upload|repository|repository/validate|yaml/validate|status/update|users/roles/validation`.
Adding one: register it in the `$this->endpoints` array in the constructor, add the
handler method, emit a `JsonResponse`. (README documents this too.)

### Persistence

Tables are created by `classes/migration/install/CodecheckSchemaMigration.php`:

- `codecheck_metadata` — one row per submission: `version, publication_type, manifest,
  repository, source, codecheckers, certificate, issue, check_time, summary, report,
  additional_content`
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
- `CodecheckPlugin::setEnabled()` runs the install migration on enable;
  `resetSchema()` (settings UI "Clear / Reset DB") drops and recreates — the only
  destructive path.

**Known schema inconsistency (pre-existing, documented in `.claude/issue-65-update.md` N2):**
`schema.xml` is stale (declares `opt_in`, `code_repository`, `data_repository`,
`dependencies`, `execution_instructions` — none of which exist) and
`classes/Submission/CodecheckMetadataDAO.php` queries a third, non-existent shape
(`identifier`, `manifest_files`, `paper_metadata`, `repositories`). That DAO is **dead
code** — nothing outside its own unit test references it, and every query would throw
(swallowed by its bare `catch`). The live DAO is `CodecheckSubmissionDAO`.
Do not "fix" one of the three in isolation; align them or delete the dead ones.

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

`CodecheckPublicationValidator` runs three checks when the submission is opted in:
current status ∈ configured allow-list, generated YAML parses, and (only when
`CODECHECK_PUBLICATION_VALIDATION_EXTENDED` is on) `codecheck.yml` in the selected
repository is fetchable and its paper title matches the OJS title. Errors are merged into
OJS's publish-validation error array and block publishing.

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
+ `readInputData()`, and the template. `SettingsForm::validate()` also warns when the
configured register repo lacks a `register.csv`.

### Logging

Use `CodecheckLogger::debug|info|error()` (`classes/Log/CodecheckLogger.php`) — writes
`[codecheck][level] …` via `error_log()`. Do not add bare `error_log()` calls; a few
legacy ones remain (e.g. `CodecheckApiHandler` label handler).

⚠️ `CodecheckSubmissionDAO.php:114` calls `CodecheckLogger::warning()`, which **does not
exist** — that branch fatals. Either add the level or change the call.

### i18n

- `locale/en/locale.po` — ~280 keys, all prefixed `plugins.generic.codecheck.`
- `registry/uiLocaleKeysBackend.json` — **generated** by `i18nExtractKeys.vite.js` during
  `npm run build` by regexing `t('…')` / `tk('…')` out of `.vue`/`.js`. Never hand-edit;
  rebuild instead. New UI strings need a `.po` entry *and* a rebuild.

### Color scheme

CODECHECK brand: primary `#008033`, dark `#006629`, light `#e8f5e8`. Documented in
README.md; keep `css/codecheck.css` and inline component styles consistent.

## Testing

### Layout

```
tests/                       PHPUnit (18 files, 132 tests)
  bootstrap.php              PKP_STRICT_MODE + BASE_SYS_DIR (OJS_ROOT or ../../../..)
  PKPTestCase.php            local stub extending PHPUnit TestCase
  phpunit.xml                default config (testdox, whole dir)
  phpunit_with_coverage.xml  + coverage HTML into tests/results/
  runTests.sh                wrapper; honours OJS_ROOT; --coverage-report=true|false
  CodecheckPluginUnitTest.php
  CodecheckRegisterUnitTests/  CertificateIdentifier(List), GithubRegisterApiClient, IssueLabels
  DataStructuresUnitTests/     UniqueArray
  FrontEndUnitTests/           ArticleDetails
  LogUnitTests/                CodecheckLogger
  SettingsUnitTests/           Actions, Manage, SettingsForm
  SubmissionUnitTests/         CodecheckMetadataDAO, CodecheckSubmissionDAO, CodecheckSubmission
  WorkflowUnitTests/           CodecheckMetadataHandler, CodecheckYamlValidator

cypress/
  support/component.js         mounts via @cypress/vue, imports css/codecheck.css
  support/pkp-mock.js          fake window.pkp (localize, modal, registry, const) — import
                               this first in every component spec
  support/e2e.js               cy.ojsLogin(), cy.getCsrfToken(), swallow uncaught exceptions
  support/component-index.html
  tests/component/*.cy.js      5 specs, 48 tests
  tests/e2e/*.cy.js            3 specs, 10 tests
                               yaml-generation, article-sidebar-setting,
                               private-repository
  tests/visual/ui-screenshots.cy.js  screenshot pass — `make screenshots`

dev/
  inspect.mjs                Playwright page inspector -> dev/out/
```

### Component tests (the reliable suite)

`npm run test:component` — **passes locally with no OJS, no database, no build step**
(48/48, ~60 s; `CodecheckMetadataForm.cy.js` alone is ~56 s). Cypress mounts the `.vue`
sources directly through Vite and stubs the API with `cy.intercept`.

Covered: metadata form load/render, manifest files add/remove/comment, repository list
add/remove + private flag, certificate identifier reservation + labels, required-field
validation, YAML preview gating, codechecker modal, review display states, data &
software availability field.

Not covered: `CodecheckStatusForm.vue`, `CodecheckGithubIssueDisplay.vue`, the
`storeExtend` wiring in `main.js` (menu injection, dashboard column, file-manager
columns), and the wizard DOM-scraping classes.

### E2E tests

`make test-e2e` — 10 tests across 3 specs, driving a real OJS instance.

- `yaml-generation.cy.js` — YAML preview vs. download parity, preview-button gating
- `article-sidebar-setting.cy.js` — the `showArticleSidebar` setting, driven through
  the real settings form; restores the setting afterwards so the rest of the suite is
  unaffected
- `private-repository.cy.js` — a repository flagged private is visible to editors in
  the workflow form and absent from the published article and the issue TOC

Requires `make serve` running with the dataset loaded; `make setup` satisfies the
rest (plugin enabled, `public/build/` present, composer deps installed, `admin`/`admin`).

Still uncovered: opt-in, the submission wizard, status transitions, publication
validation, register deposit, and the settings form beyond the sidebar toggle.

### PHPUnit tests

`make test-php` — 132 tests, green (19 skipped, 1 warning, 2 deprecations).

PHPUnit needs an OJS installation: the tests load OJS classes and the runner uses the
PHPUnit shipped in `lib/pkp`. Both `runTests.sh` and `bootstrap.php` honour `OJS_ROOT`,
falling back to the four-levels-up layout CI uses. `OJS_ROOT` is mandatory here because
the plugin directory is a symlink — see "Local development environment".

Tests requiring Laravel facades / translator are `markTestSkipped` at `setUp()`:
all of `SettingsFormUnitTest` (9), `ManageUnitTest` (6), and 4 in `ActionsUnitTest`.
They are skipped locally *and* in CI.

Not covered by PHPUnit at all: `CodecheckApiHandler` (1.1k lines, the entire API surface),
`CodecheckRegisterDepositService`, `CodecheckPublicationValidator`, `CodecheckStatusHandler`,
migrations, `IssueTOC`, `CodecheckPageHandler`.

### CI (`.github/workflows/tests.yml`)

Three jobs on push/PR to `main`:

1. **PHPUnit** — checks out `pkp/ojs@stable-3_5_0` + plugin into
   `ojs/plugins/generic/codecheck`, MySQL 8 service, writes a dummy `.env`, runs
   `runTests.sh` twice (plain + coverage), uploads `tests/results/`.
2. **Cypress component** — plain `npm ci && npm run test:component`.
3. **Cypress e2e** — full stack: OJS + `pkp/datasets` + this repo's
   `testData/stable-3_5_0-codecheck` dump, `loadfiles.sh`, OJS npm build, plugin npm
   build, Apache + mod_php on :8888, then `npm run test:e2e`.

Note the README badge points at `component-tests.yml`, which no longer exists — all three
jobs live in `tests.yml`.

### Test data (`testData/stable-3_5_0-codecheck/`)

A PKP-datasets-shaped MySQL dump + article files for a "CODECHECK Demo Journal"
(path `codecheck`): 8 submissions (5 published across 2 issues, 2 in review, 1 submitted),
users `admin/admin`, `jmanager`, `seglen`, `dnuest`, `fostermann`, `rreviewer`
(password = username twice, except admin). `testData/README.md` documents manual loading
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
— and that was the branch calling the missing `CodecheckLogger::warning()`,
which made viewing any seeded article page fatal.

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
- **Two independent "enabled" switches.** `plugin_settings.enabled` turns the
  plugin on; the separate `showArticleSidebar` setting gates the reader-facing
  article sidebar in `ArticleDetails`. The dataset only has the first, so the
  frontend renders nothing without the second; the test dataset ships it.
  `IssueTOC` does *not* check it, so TOC badges appear either way — an
  inconsistency, not a feature.

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

## Conventions

- PSR-12; speaking names, verbs in function names; document public methods/classes
- Vue SFCs use `<script setup>`-style composition where already present — match the file
- Every user-visible change belongs in `CHANGELOG.md` (note: the changelog is currently
  far behind the code — it stops at "initial plugin structure")
- Release process, packaging (`package-plugin.sh`) and the API-extension recipe are in
  `README.md`
- `.claude/ISSUE_CODE_IMPROVEMENTS.md` and `.claude/issue-65-update.md` hold earlier
  code-review findings; several are still open (dual storage, schema mismatch, dead DAO)

## Traps

- `public/build/` gitignored but required — rebuild after pulling or after JS edits
- `vendor/` required at file-scope `require` — `composer install` before anything PHP
- `registry/uiLocaleKeysBackend.json` is generated — never hand-edit
- `CodecheckMetadataDAO` is dead and queries a non-existent schema — don't extend it
- `schema.xml` does not describe the real table — the migration does
- Hook argument arrays carry **references** (`[&$page, &$op, …]`). Writing through
  `$args[n]` propagates to the caller even though `$args` itself is by-value — unit
  tests must build the array with references to model this (see
  `CodecheckPluginUnitTest::buildLoadHandlerArgs()`)
- `stageId: 999` is a sentinel for the CODECHECK workflow menu item, not an OJS stage
- Two separate enable switches: `plugin_settings.enabled` and the
  `showArticleSidebar` setting. `ArticleDetails` checks the second, `IssueTOC` does not
- Data-structure changes must be mirrored into `testData/` in the same commit —
  see "Keep the test dataset in sync with data-structure changes"
- The API handler `exit`s after serving; it bypasses PKP authorization policies and
  does its own CSRF + role check
