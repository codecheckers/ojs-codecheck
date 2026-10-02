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
make check-migration   # the #185 upgrade migration against the dev database (writes; asks first)
make throwaway-up THROWAWAY=doi   # a throwaway OJS for anything that writes; see below
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
- `Publication::publish::before` → `CertificateReferenceUpdate::addOnPublish()`
  lists the certificate among the references (#183). Outside the
  `getEnabled()` block too: the scheduled task publishes on the command line.
  **`publish()` calls `dao->update()` without the old publication**, so a
  `citationsRaw` set in this hook is never reparsed; it calls
  `CitationDAO::importCitations()` itself. Like the DOI links, the line is
  written only at a *published certificate* status: an identifier is reserved
  when a check starts, and its register page does not exist until then.
  `CertificateReference::merge()` finds an existing line by the certificate's
  DOI, register address, linked address or identifier, each matched **whole**:
  a substring match took `…zenodo.1234567` for `…zenodo.123456` and overwrote
  someone else's reference
- `articlecrossrefxmlfilter::execute` / `datacitexmlfilter::execute` →
  `CodecheckDoiDeposit` adds the CODECHECK links to the DOI deposit (#19).
  Registered **outside** the `getEnabled()` block, like `Context::add`, and
  `SEQUENCE_LATE`; see "DOI deposits" below

Hook callbacks return `false` by design so other plugins/OJS continue to run —
`validatePublicationHook()` documents why at `CodecheckPlugin.php:93-102`.

### Vue integration (`resources/js/main.js`)

The plugin does **not** mount its own app in the backend. It extends OJS 3.5's Vue/Pinia
runtime:

- `pkp.registry.registerComponent(...)` for 9 components + 2 inline components
  (`CodecheckFileStatus`, `DashboardCellCodecheck`)
- `pkp.registry.storeExtend("workflow", …)` — adds a **CODECHECK menu item** to the
  workflow sidebar (uses sentinel `stageId: 999`), and injects `CodecheckMetadataForm`
  (primary) + `CodecheckStatusForm` / `CodecheckGithubIssueDisplay` (secondary).
  It also puts `CodecheckPublicationInfo` above OJS's form on **Publication ›
  Metadata** (#34): that page is the menu state
  `{primaryMenuItem: "publication", secondaryMenuItem: "metadata"}`, and the
  component's link to the CODECHECK tab is the store's own
  `navigateToMenu('codecheck')`. Its journal-level half — badge and the
  destinations from `CodecheckMetadataDestinations` — arrives as
  `window.codecheckDashboardConfig.publicationInfo`, because the workflow opens
  inside the dashboard. **A locale key built at runtime
  (`'prefix.' + id`) never reaches OJS**: `i18nExtractKeys.vite.js` collects only
  literal `t('…')`/`tk('…')`, so the component spells its keys out in a `tk()` map,
  and a ternary *inside* `t(…)` hides both of its keys the same way.
  **Whether a submission takes part, and why not, is `resources/js/optIn.js`**
  (`isOptedIn()`, `notOptedInReason()`): the CODECHECK tab's warning, the
  review stage, this panel and every `codecheckOptIn` gate in the backend ask
  it, so no two tabs can explain the same submission differently. An unset flag
  is "no choice recorded", in every mode. The wizard's Smarty templates keep
  their own wording — that is the author's step, not an editorial view
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
`CodecheckDataAndSoftwareAvailability.vue`, `CodecheckOrcidSection.vue`,
`CodecheckPublicationInfo.vue` (the Publication › Metadata panel), and the two dialog bodies
`CodecheckCodecheckerDialog.vue` / `CodecheckStatusDialog.vue`.

### Markup and dialogs (`resources/js/markup.js`, `resources/js/dialogs.js`)

`markup.js` is where a string of HTML is built: the `html` tagged template
**escapes everything interpolated into it**, and markup the plugin wrote itself
has to say so with `raw()`. `html` answers a marker rather than a string, so one
`html` nests inside another for free and `raw()` stays rare enough that a
`raw()` in a diff is the thing to look at twice; `toHtml()` is the single point
where a marker becomes the string handed to `innerHTML` or to OJS. `escapeHtml`
is still exported for the one caller that cannot use the template (the
introduction sentence, whose link is substituted by `t()` inside the message).

That is the reverse of the concatenation it replaced, where every new value had
to remember `escapeHtml()` — the two defects #179 came from were a dropped
closing tag and a user's name that ran as script in an editor's browser. Every
markup builder now goes through it: the dialogs, the status history table and
the submission wizard's review panel.

**Every dialog the plugin opens goes through `dialogs.js`** — nothing else names
`pkp.modules.useModal` (#179). It offers `askForConfirmation`, `showInformation`
(with an optional second action, which is how the YAML preview offers its
download) and `askForInput`, so a confirmation in the file manager and one in
the editorial form ask the same way round, with the same labels. **The primary
action is last in every one of them**, and a confirmation whose Yes removes
something passes `destructive: true`, which is what forwards OJS's
`modalStyle: 'negative'` and makes the confirm button warnable.

**A dismissal is an answer.** OJS calls a dialog's `close` prop on Escape and on
a click outside as well as from an action, so `askForConfirmation` forwards one
and settles the question once: dismissing means No, and pressing a button must
not also count as a dismissal. Passing no `close` at all — where this started —
meant an editor who pressed Escape on "open the register's first issue?" was
told nothing either way.

**The buttons are labelled from OJS's own `common.*` keys** — `common.yes`,
`common.no`, `common.close`, `common.cancel`, `common.add`. PKP ships those
translated in every locale it has, and the plugin ships only `locale/en`, so a
plugin key would read "Yes" beside an OJS dialog reading "Ja". A label belongs
to the plugin only where OJS has no word for it: the status dialog's "Change",
because `common.change` does not exist. The plugin's own `yes`/`no`/`modal.close`
/`modal.cancel`/`modal.add` entries were dropped when the dialogs stopped using
them.

**A dialog that asks for something is opened with no `actions`, and its body
draws its own buttons.** OJS's `Dialog` is built for a question that is over
once a button is pressed: the first click sets an internal flag that disables
*every* action for good and puts a spinner beside them, and the close X is
rendered only while the dialog has no actions. So a dialog that stays open to
say why it refused — which is the whole of #180 — would stay open with nothing
left to press, and only Escape would get the editor out, taking what they had
typed with it. Every `bodyComponent` dialog OJS opens itself only displays
something, which is why OJS has never met this. `resources/js/dialogForm.js` is
the mixin those bodies share: the buttons, and what pressing the primary one
means. A body implements `validate()` answering `{valid, value}`, brings its own
`setup()` returning `t` (**Vue 3 does not call a mixin's `setup`**), and shows
`error`, which holds whatever the caller refused the value with.

Closing one is `closeDialog()` in `dialogs.js`: `useModal()` exposes no closer,
and **`pkp.registry.getPiniaStore('modal')` throws** — that registry holds only
OJS's own component stores — so it goes through `pkp.registry._piniaInstance._s`,
as the rest of the plugin reaches the workflow store, and falls back to the
`close-dialog-vue` event.

None of this is reachable by the component suite on its own: the mock decides
how a dialog behaves. `cypress/support/pkp-mock.js` therefore models the
disabling, and `cypress/tests/e2e/codechecker-dialog.cy.js` drives the real one.

**A dialog that asks for something is a Vue component, not a string** (#180).
`askForInput({title, bodyComponent, bodyProps, submitLabel, onSubmit})` uses
OJS 3.5's `bodyComponent`/`bodyProps`, which OJS itself uses for its invitation
dialogs. The buttons belong to the dialog and the fields to the component, so
the two meet through a plain `form` object `askForInput` passes in: the body
registers `form.submit()`, which validates and answers `{valid, value}`, and
`form.setError(message)`. An invalid answer leaves the dialog open — the
component is already saying why beside the field — and so does an `onSubmit`
that answers with a message, which is how a refused status update is reported in
the dialog that caused it rather than only in the console.

The fields used to be read back through `document.getElementById('checker-name')`,
which meant an empty name closed the dialog having added nothing, the ORCID was
stored unchecked, and a second copy of the form on the same page would answer
for the first. `resources/js/orcid.js` is the ORCID check — format plus the
ISO 7064 MOD 11-2 check digit — and it is only in JS: nothing on the server
validates an ORCID iD before storing it.

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

**Who may know the authors is a second question beside access**, answered
by `CodecheckSubmissionAccess::mayKnowAuthors()` (#28). `SubmissionAccessPolicy`
admits a reviewer without asking the review method, so `GET metadata` and
`GET yaml` gave author names to every codechecker. **OJS hides the authors from
a reviewer in double-anonymous review only** — method 1 is "Anonymous Reviewer /
Disclosed Author" — so that is the one case withheld; an early version withheld
for every non-open assignment and was wrong. The decision follows the user's
*current* assignment, the one `ReviewAssignmentAccessPolicy` finds (last round,
none when cancelled or declined). Standing is **per submission, not per journal
role**: a Manager or site administrator always (the site administrator is asked
for in the site context, where `hasRole()` with the journal id never finds it);
an author, Section editor or Assistant only with a stage assignment on *this*
submission, because a Section editor invited as a blind reviewer reaches the
submission through that assignment alone. When the answer is no,
`getMetadata()` sends no authors and no contact and sets `authorsWithheld`, and
the YAML has no `paper.authors` (the form says so in the preview). **`$revealAuthors`
has no default** on `getMetadata()`/`generateYaml()`: the journal's own checks
(validation, register, YAML validation) pass `true`, and `POST status/update`
passes `false` because it needs only the codecheckers. Only names, ORCIDs and the
contact are withheld — the availability statement and repository URLs still reach
a withheld codechecker, since redacting the repositories would defeat the check.
The contact is the publication's primary contact (`getPrimaryAuthor()`) with its
email; the email is deliberately not in `getAuthors()`, which feeds the published
`codecheck.yml`.

**The editorial form's repository import takes only what the form lets an editor
change** (#28). `POST repository` goes through
`CodecheckMetadataHandler::importMetadataForSubmission()`, which refuses a
`codecheck.yml` whose `paper.title` is missing or is not the submission's — the
title comes from the authorised submission, never the request. The comparison is
`CodecheckMetadataHandler::titlesMatch()`: whitespace runs (and non-breaking
spaces) count as one space and capitals are ignored; the extended publication
validation uses the same function, so the two cannot disagree. The plain
`importMetadataFromRepository()` stays a bare fetch for the validator and the
register deposit. In `CodecheckMetadataForm.vue` the import no longer assigns
`submissionData` (title, authors, DOI are the submission's) and keeps the
certificate identifier while `certificateLocked` — the same condition that makes
the field read-only.

Endpoints: `GET labels|metadata|yaml|register|status|status/history|orcid-status|orcid-test`,
`POST identifier|issue|metadata|references|repository|repository/validate|yaml/validate|status/update|users/roles/validation|orcid-deposit`.
`POST references` (#183) is submission-scoped and for `EDITOR_ROLES`: it edits
the article's own metadata through `Repo::publication()->edit()`, refusing a
published latest version as OJS does, and answers `{line, changed}`.

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
- `codecheck_codecheckers` — the journal's directory of codecheckers (#186):
  `context_id, name, orcid, github_username`, unique per journal on the ORCID
  iD and on the username

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
- `upgrade/I186_AddCodecheckerDirectory` — creates `codecheck_codecheckers`;
  converts nothing
- `upgrade/I185_MoveRecordsToConfigSpec2` — moves every record on a config
  version the plugin no longer knows (`latest`, `1.0`) to `2.0`, and the
  `spec_version` column default with it. Runs after I93, whose column it reads
- `CodecheckPlugin::setEnabled()` runs the install migration on enable, and
  **`upgrade.xml` runs it when the plugin is upgraded from the Plugin Gallery**.
  A gallery upgrade replaces the files and records the new version and runs
  nothing else unless the package carries that file, so without it a journal that
  never re-enabled the plugin kept the old schema and rows. The file names
  `GalleryUpgradeMigration` alone, which runs the install migration (that calls
  every upgrade step) and **carries no `version`**: the installer records a
  descriptor's version as OJS's own when it is newer. The wrapper exists because
  `PluginHelper::upgradePlugin()` **deletes the plugin's directory when anything
  throws** and the installer catches only `Exception`: a failure — the old plugin
  object is still in memory and the migration calls it — is logged instead.
  **Nothing retries it on its own**: it runs again on an enable or an OJS
  upgrade, so an enabled journal stays on the old schema until an administrator
  disables and enables the plugin, and the log line is the only sign. It also
  upgrades only an install that already has `codecheck_metadata`, so an upgrade
  never creates the schema where the plugin was not enabled; where it was enabled
  in any journal the whole install migration runs, genre in every journal
  included, as it does on enable. `UpgradeXmlUnitTest` pins the class, that OJS's
  parser reads the file and that `.gitattributes` does not `export-ignore` it; the full gallery path (a packaged zip and a
  version bump) is not exercised, and is worth one run on a release candidate.
  **Nothing in the plugin drops a table** — the settings form's "Clear / Reset
  DB" button did, and was removed in #131; rebuild a development instance with
  `make db-reset` instead.
- **No automated run sees a legacy row**, because both datasets start on 2.0.
  `make check-migration` (`dev/check-migration-spec2.php`) seeds `latest` and
  `1.0` rows and a `latest` column default, runs I185 twice and asserts — and,
  as it writes to the development database, asks first and refuses unless the OJS
  plugin symlink points at this checkout. Run it after touching an upgrade
  migration that moves data. `make check-migration UPGRADE_XML=1` runs it the way
  a gallery upgrade does, through OJS's `Upgrade` installer and `upgrade.xml`; the
  installer also hooks the install migration itself, so that mode shows the
  descriptor is accepted and the result is right, not which of the two ran it.

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
`…_REGISTER_REPOSITORY`), not from `.env`. **Nothing at runtime reads an
environment variable or parses `.env`** — the `Dotenv::createImmutable()` call
this class used to make at file scope, and the dummy `.env` CI wrote for it,
both went with #50. `.env` is now a development file only, read by
`dev/db-credentials.php`.

Register deposit fires on `Publication::publish`, is gated by
`CODECHECK_REGISTER_DEPOSIT_ENABLED`, requires a reserved certificate and a repository
flagged as containing `codecheck.yml`, re-verifies the `codecheck.yml` is fetchable, and
**never blocks publication on failure** (logged only).

`CodecheckStatusRegisterUpdate` carries a recorded status change to the register
issue: a comment on its timeline (#150) and the labels that say where the check
stands (#174). Both halves need the same four things resolved — the journal's
`updateStatus` choice, the credentials, the register and the issue number — which
is why they share a class rather than a second resolver, and each is attempted
independently so a refused comment still brings the labels.

**The labels are changed one at a time, never replaced — on every path.** A
register issue carries labels nobody here owns — the venue, `check-nl`,
`buddy exchange`, `help welcome`, `metadata pending` — and writing the whole
list wipes all of them. `updateIssue()` did exactly that: it sent `labels` in
its `PATCH`, built from `id assigned` plus what the form had selected, so an
editor pressing "update issue" undid the status labels and every human one. It
now adds them instead, which makes the form's label selection **add-only** —
deselecting a venue label no longer takes it off the issue. That consequence is
currently unreachable through the UI: the label checkboxes sit in a `fieldset`
that is disabled once an identifier is reserved, so the selection cannot be
changed after the issue exists. Whether the form should be able to remove the
labels it offers is open; doing so means a second managed set, not a wholesale
write. `Constants::CODECHECK_REGISTER_MANAGED_LABELS` is the whole set the plugin
will ever **remove** (`needs codechecker`, `work in progress`) — it adds outside
it, namely `id assigned` and the venue labels the form offers — and
`CODECHECK_REGISTER_STATUS_LABELS` maps each status to the ones that belong on
the issue; `labelChanges()` diffs that against what the issue carries, and is
pure so the rule is unit tested without GitHub. A status missing from the map
changes nothing, because "unknown" is not a state to write into someone else's
repository. `id assigned` is deliberately not managed: it is set when the issue
is opened and never withdrawn, or the identifier could not be found again.
Stalled keeps `work in progress` — the comment says it stalled and who it waits
on, which labels are too coarse to carry — and `work in progress` comes off at
*completed*, published or not. These were the repository owner's decisions.

Three properties of the sync that are easy to undo by accident:

- **Every label write stands alone.** One `try` around the batch meant the first
  refusal abandoned the rest, leaving an issue saying both that it needs a
  codechecker and that one is working on it — the state #174 is about. Removals
  run first, so a half-done sync says too little rather than two things at once.
- **A removal GitHub answers 404 for has already happened.** Reporting it as a
  failure made the sync depend on nobody else having touched the issue.
- **The labels follow the *current* status, not the one being recorded**, so two
  recordings that interleave settle on the newest row rather than on whichever
  request finished last. The comment says what was recorded; the labels say
  where the check stands.

**Every post to the register is signed, through one object.**
`CodecheckPostOrigin` carries the journal's name and address, the OJS version
and the plugin's (`CodecheckPlugin::codeVersion()`, read from `version.xml`,
not the database), and the signature. `CodecheckGithubRegisterApiClient` takes
one, and appends `signature()` to the issue body, every comment and the deposit
PR body; the issue's JSON block takes `journal.url`, `journal.ojsVersion` and
`plugin` from it. **The journal's address is built from the configuration**
(`base_url`, its `base_url[<journal>]` override, `restful_urls`) by
`CodecheckPostOrigin::journalUrl()`, never through the dispatcher: that takes
the host from the request's `Host` / `X-Forwarded-Host` header, and an assigned
reviewer can trigger a status change, so a crafted header would put an address
of their choosing into a public post whenever `allowed_hosts` is left empty. The
client cannot be built without one, so a new write path
cannot go out unsigned by forgetting it. The JSON block is built with `json_encode` and is
a published format now that it parses: anything reading the register may rely
on its keys, so rename none of them.

**A recorded issue number is only an address together with its repository.** A
journal that moves from a testing register to the production one keeps the old
numbers on its submissions, and that number in the new register is somebody
else's check — so the stored `issue.url` is compared against the configured
register before anything is written, and a record from before the URL was stored
is accepted (`issueUrlIsInRegister()`). The settings form checks that the
register carries every label the plugin needs, because **GitHub creates an
unknown label when an issue is given one** — without the check the plugin would
invent labels in someone else's repository. And `CodecheckIssueLabels` keeps all
of them out of the list the form offers
(`isAssignedByThePlugin()`), or the form would add what a status change had just
removed.

### Codecheckers and register assignees (#186)

A codechecker in a submission's `codecheckers` list is `{name, orcid, github}`;
entries from before #186 have no `github` and read as having no username.
`CodecheckCodecheckers` is the rule for all three, at the save boundary, and
`resources/js/githubUsername.js` mirrors the username half as `orcid.js`
mirrors the ORCID half. The save path reads `orcid` alone. A `codecheck.yml`
spells it `ORCID`, so `CodecheckMetadataForm.importedCodecheckers()` maps an
import into the form's shape — that is where the second spelling entered, and
an imported iD was dropped on save while the form held it raw. An imported iD
that is not one is still dropped, or every later save would be refused, and a
username already on the form for the same iD (or name) survives the import.
(`OrcidAuthHandler` and the ORCID status endpoint still accept `ORCID` too;
they read the stored column, which now only ever holds `orcid`.)

**Everything journal-wide or public is for editors** (#173), checked with
`CodecheckSubmissionAccess::isEditor()`. `POST metadata` and `POST
status/update` admit an assigned reviewer, so the editor check is inside:
a reviewer's save neither writes the directory nor assigns anyone, and a
reviewer recording "codechecker assigned" gets the plain status comment (#150)
without codechecker lines.

**The submission keeps a copy; the directory is a memory.**
`CodecheckCodecheckerDirectory` fills `codecheck_codecheckers` from editorial
saves and feeds the dialog's picker, but nothing reads a check's codecheckers
from it — a check records who did it at the time. It learns only the entries a
save brings in or changes, so re-saving an older record cannot put back a name
or username a newer check replaced; the cost is that past checks are not
backfilled — the directory starts empty and fills as checks are edited. Entries
with neither an ORCID iD nor a username are not remembered, and a username held
by another entry is not moved silently. The lookup order — directory, then
community list — is `suggestGithubUsername()`, and the dialog checks the
directory it already loaded before asking. `GET codecheckers` and
`GET codecheckers/lookup` are `EDITOR_ROLES`.

**A suggested username is offered, never filled in.** The dialog shows it with
a "Use it" button. Filling it in on blur meant the blur that pressing Add
causes could fill it, and the editor added a username they never saw.

**The suggestion is the only server call to `raw.githubusercontent.com`.**
`CommunityCodecheckers` reads the three lists in `codecheckers/codecheckers`
at `HEAD` — the default branch is `master`, and a guessed `main` answered 404
for every list — concurrently, with columns found by header. A complete read is
cached for six hours in Laravel's cache, a partial or failed one for five
minutes. The e2e suite must make no external call, so a spec driving the
dialog against a real OJS intercepts `codecheckers/lookup`
(`codechecker-dialog.cy.js` does).

**Assignment follows the status, as the labels do, and is only added.**
`CodecheckGithubRegisterApiClient::syncAssignees()` is the one place that
assigns, and the one that decides it is best-effort; it answers `null` when
GitHub could not be asked, which is not "nobody assigned" — the comment then
names nobody rather than calling an assigned codechecker unreachable. It is
reached two ways, both through `CodecheckStatusRegisterUpdate`'s resolver (the
"update status" choice, credentials, an issue in the configured register) and
both for editors only:

- recording `codechecker assigned` (`apply()`, before the comment, so the
  comment can say whom it reached);
- an editorial save that brings in a username or records the issue for the
  first time (`syncAssignees()`), while the current status is past
  `needs codechecker` — an issue labelled `needs codechecker` with an assignee
  is the state #174 removed.

Issue creation and "update issue" do not assign: they carried the form's
unsaved list and bypassed the resolver. Nothing is unassigned: someone may
have assigned a person by hand, and withdrawing the plugin's own assignments
needs a record of which ones it made. GitHub **silently drops** a username it
cannot assign (not a member or collaborator of the register, has not
commented), so the answer is read back.

**The issue body's JSON block follows the record; the rest of the body does
not.** `CodecheckStatusRegisterUpdate::refreshMetadata()` rewrites the
`<details>` JSON block from the stored record after every status change and
every editorial save — editors only, under the journal's "update body" choice,
once the record carries its identifier. It reads the body first and writes only
when the block changed, and leaves a body without the block alone. The rest of
the body (paper title, authors, the readable status and codecheckers lines)
needs what only the form sends, so it is still rewritten by "update issue"
alone; `CodecheckGithubRegisterIssue::metadataBlock()` is the one builder of
the block for both paths.

**A codechecker without a usable username is the fallback, not an error.** The
comment names them, with their ORCID record, and links the journal's contact
page (`CodecheckPostOrigin::contactUrl()`, built from the configuration like
the journal address, never from the request). Such a codechecker is not
@-mentioned. `RegisterCodecheckers::describe()` keeps a name to one line,
escapes it, writes `&` as `&amp;` and follows every `@` and `#` with a
zero-width space. **An HTML entity is not enough**: GitHub renders `&#64;name`
as a mention, and `\#1` still links an issue.

### ORCID deposit (`classes/Orcid/`)

**Nothing in the ORCID deposit contacts ORCID until there is something to
deposit** (#182). `OrcidDepositService::depositForSubmission()` registered the
journal's peer-review group id first — two requests, since `createGroupId()`
begins by posting the client id and secret to `/oauth/token` — and only then
looked for a certificate identifier or an authorised codechecker. So a journal
with ORCID enabled and credentials configured made two calls to ORCID on every
publish of an opted-in submission that could deposit nothing, once per article
when an issue was published. The certificate check and `depositTargets()` now
come first, and every request is reached from the private `deposit()`, which
nothing enters without a target.

**That last property is a convention, not a guarantee, and no test covers it.**
`deposit()` being the only user of the client is what upholds it; an edit that
calls `ensureGroupIdRegistered()` from anywhere else reintroduces #182 in
silence. `depositForSubmission()` cannot be reached from PHPUnit — a journal
context, `Repo::submission()` and the database come first — and the live ORCID
test that would exercise it is blocked on Member API credentials
(`dev/live-orcid-tests.md`), so `make test-php`, `make test-component` and
`make test-e2e` would all stay green. What *is* pinned is `depositTargets()`,
the rule that decides whether there is anything to deposit at all.

`ensureGroupIdRegistered()` memoises per journal for the life of the process —
one request under mod_php or FPM, which is what `IssueGridHandler::publishIssue()`
needs, since it publishes every scheduled article of an issue in one request.
(Not the `PublishSubmissions` scheduled task: that runs on the CLI, where
`getContext()` is null and the plugin bails before the service is built.) **Only
a resolved attempt is remembered** — memoising a failure looked like a saving,
but one timeout on the first of twelve articles would stop the other eleven
registering and each would then deposit against a group that does not exist. The
key carries the journal and the API type, because every journal without an ISSN
shares the `orcid-generated:codecheck-ojs` fallback. Nothing is remembered across
processes: a stored flag that is wrong, because the record was deleted at ORCID
or the journal's ISSN changed, is worse than a request ORCID answers 409 to,
since nothing would ever try again. The memo is a static, because a service
instance is built per publish and would memoise nothing across an issue; nothing
resets it, which is a trap for the first test that reaches it.

**The group id itself has one derivation**, `PeerReviewPayloadBuilder::groupIdFor()`.
The payload cites it and the deposit service registers it, and they derived it
separately — so a change to ISSN handling could have had the deposit cite a
group nobody registered, which ORCID refuses, recording a reason that names the
group rather than the drift. The group *name* still has several derivations, and
`loadJournalInfo()`'s `onlineIssn ?? printIssn` falls through only on null, so a
cleared online ISSN masks a print one and files the journal under the shared
fallback; both are left alone deliberately, since ORCID answers 409 for a group
that exists and would never take a correction.

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
offer `2.0` long after `2.1` replaced it, with no way for a migration to tell
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
  "Not provided for this work." rather than dropping the section, since
  silence cannot be told apart from a journal that never asked. That sentence
  **does not name the heading**, which sits right above it: it used to, and a
  journal-worded heading dropped into a plugin-translated sentence mixed two
  languages whenever they differed (#164)
- `CODECHECK_BADGE_TEXT` falls back to the localised
  `plugins.generic.codecheck.badge.textOnly` when cleared, so a journal showing text
  instead of a badge never shows nothing. `CODECHECK_BADGE_TEXT_COLOR` falls back to
  `CODECHECK_BADGE_TEXT_COLOR_DEFAULT` for anything that is not a six-digit hex
  colour — it is written into a `style` attribute on a public page, so it is
  validated both on save and on read, as OJS's own theme colour option is
  (pkp/pkp-lib#11974). That rule is `Constants::normalizeBadgeTextColor()`, the
  same shape as the height's: it was four copies — this pattern twice and a
  weaker `?:` fallback twice, which disagreed about a stored non-colour

**Those two texts are stored per locale** (#164), as an array keyed by locale
from a `multilingual=true` FBV field, so a multilingual journal is not made to
pick one language for every reader. `Constants::localizedText()` is the one
reader rule: the reader's locale, then the journal's primary locale, then
`null` for the caller to replace with its `__()` default. That is the first two
steps of PKP's `getLocalizedData()` order and deliberately not its third ("any
language that has a value"): a journal with no wording in its primary language
gets the plugin's localised default, not a stray other language.
`Constants::cleanLocalizedText()` is the write rule: trimmed, empty languages
dropped so they fall back, and only the form's locales taken from the post.
`SettingsForm::localizedTextToSave()` keeps what is stored for any *other*
locale, because a reader language need not be a form language and a save must
not drop wording the form never showed. Such wording cannot be edited until the
language is a form language again — deliberately so, since OJS keeps a journal's
own texts the same way (`SchemaDAO::updateObject()` deletes a locale only when
it is sent as null). **Anything but an array reads as
nothing stored.** Both
were a plain string until #164, but never in a release, so there is no migration
and the old shape is deliberately not read — reading both would give one row two
meanings, which is #154 again.

The form offers exactly the journal's form languages: `PKP\form\Form` takes
`Locale::getSupportedFormLocales()` unless told otherwise, and FBV renders a
field for each, the non-primary ones in a popover. None is required. Readers'
languages (`supportedLocales`) may be ones the form does not offer; the fallback
covers them. `Badge` and `ArticleAvailability::getHeading()` take the journal's
`Context` and an optional reader locale, falling back to `Locale::getLocale()`;
unit tests always pass the locale, because the facade is not bound under the
PHPUnit bootstrap.

**A multilingual FBV field has no stable id.** FBV appends a generated suffix
and, with several languages, the locale, so e2e specs find it by name —
`[name="codecheckBadgeText[en]"]` — and a `<label for>` cannot point at it.
**Nor give it a `placeholder`**: FBV writes ours into every language's input
ahead of its own language-name placeholder, and the browser keeps the first, so
the empty German field would announce the plugin default while a German reader
actually gets the primary language's wording. The field's description says what
the default is instead.

**A new setting holding text a reader sees is multilingual from the start.**
The audit for #164 found no other: the register labels, organisation and
repository are identifiers in GitHub, `orcidCity` goes into an ORCID deposit
that holds one city, and the rest are values. The one borderline case is
`CODECHECK_BADGE_CUSTOM_URL` — OJS keeps its own logos per locale, and a badge
image with words in it could want the same; left single-valued until someone
asks.

**`CODECHECK_GITHUB_SIGNATURE` is the deliberate exception**: text a reader
sees, and single-valued. It closes what the plugin posts to the register, which
is one shared place read in English, so a post must not change language with
the editor who happened to trigger it. Its reader rule is in
`CodecheckPostOrigin::signature()` — a cleared or unset value is the English
default in `CODECHECK_GITHUB_SIGNATURE_DEFAULT` — so it is not in
`CODECHECK_SETTING_DEFAULTS` either, for the badge text's reason.

`CODECHECK_ENABLED_CONFIG_VERSIONS` defaults to `CODECHECK_DEFAULT_CONFIG_VERSIONS`
— the current stable specification alone, not every known version.

**The plugin knows `2.0` and nothing else, and never `latest`.** `latest` was on
the list until the specification moved it from 1.0 to 2.0 (2026-09-11), and
every record on it then declared a version it had not been filled in against,
one that makes more fields mandatory. **Only concrete versions belong in
`CODECHECK_CONFIG_VERSIONS`.** 1.0 was dropped at the same time, since no
journal ran the plugin in production yet; the setting stays as the way a later
version is offered next to 2.0. **1.0 is not special**: it is handled as any
version the plugin does not know, a `2.1` before it is implemented included.
`Constants::isKnownConfigVersion()` is the one question. **A version posted to
`saveMetadata()` that is not known is refused with a 400**, before anything is
written, so it cannot be selected or created; an absent `version` keeps the
stored one. **A known version the journal does not offer is refused as well**,
unless the record is already on it (`Constants::isConfigVersionAllowed()`: the
form keeps the loaded version selectable, so a record on a version the journal
has since stopped offering still saves). The controller passes the journal's
list to `saveMetadata()`, which requires it. The stored version is compared as
`GET metadata` answers it, resolved, so a record the upgrade missed still saves.
With a single known version the offered list can never exclude it, so only the
pure rule is tested until a second one exists — then a case belongs in
`config-version.cy.js`: untick it in the settings, post it, expect 400, post the
stored one, expect 200.

A version *stored* that is not known reads as the default
(`Constants::resolveConfigVersion()`), on the `GET metadata` response, in
`buildYaml()` and in `CodecheckSubmission`, so a record the upgrade missed
degrades rather than fails. A journal row still holding `['1.0']` needs no
migration: `narrowConfigVersions()` narrows it to nothing, which resolves to the
default. That function keeps strings only, because the settings form hands it
whatever the browser posted.

**What a version requires is warned about, never enforced.**
`resources/js/configSpec.js` lists, per version, the fields the specification
makes mandatory, and the metadata form and the YAML preview name the ones a
record lacks. Nothing refuses a save or a publish on them, because several come
from OJS rather than from the form — the authors' ORCID iDs, the DOI, which is
often assigned just before publication — so a refusal would stop a check at a
point nobody in the form can resolve. The rules judge what `buildYaml()` writes —
the certificate as stored, an author's iD through `normalizeOrcid()` — and
`saveMetadata()` trims the certificate so the two agree. A new version with no
entry there requires nothing the form knows of. The locale keys are named through a local `tk()`,
which is what puts them into `registry/uiLocaleKeysBackend.json`: the extractor
only sees literal keys, so a key assembled from a field name would reach the
browser untranslated.

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

### DOI deposits (`classes/DoiDeposit/`)

With `CODECHECK_DOI_DEPOSIT_LINKS` on, an opted-in article whose status is a
*published certificate* gets the certificate DOI (`report`) as Crossref
`hasReview` / DataCite `IsReviewedBy` (`Report`), and every public web-address
repository as `isSupplementedBy` / `IsSupplementedBy` (#19). OJS 3.5 only;
what 3.6 changes is collected on #187.

- **The hooks are `Filter::execute()`'s**, raised with the finished document
  before OJS validates and deposits it, so PKP's Crossref and DataCite plugins
  are untouched. Deposits are queued jobs, and a CLI worker loads every generic
  plugin with no journal, so the hooks are registered outside `getEnabled()`
  and **everything is resolved from the document**: Crossref by its DOI (one
  join over `dois`, `publications`, `submissions`), DataCite by OJS's
  `publisherId` alternate identifier, because test mode rewrites the DOI's
  prefix. `CodecheckPlugin::isDoiDepositLinksEnabled($contextId)` is the one
  reader and asks `getEnabled()` for that journal too; the opt-in is read from
  `submission_settings`, since `codecheckOptIn` is on the submission schema
  only where the plugin registered it.
- **A job run at the end of a web request for another journal, or a site
  page, deposits without links**: that request loads only its own journal's
  enabled plugins, so this plugin is never registered (pkp-lib#9345). Nothing
  in the plugin reaches it.
- **Nothing is validated at deposit time.** Both schemas take any text as the
  related identifier and everything else written is fixed, so a per-deposit
  check could only repeat the unit tests — while fetching every schema file a
  second time (about 13 s for Crossref, in a job limited to 30 s by
  `job_runner_max_execution_time`). `DepositSchemaUnitTest` validates what the
  writers add against copies of the published schemas in
  `tests/DoiDepositUnitTests/schemas/` (refresh them with a new schema version);
  the full record is what OJS validates, and an editor can export it from the
  DOI list to see the result.
- `CODECHECK_DOI_REDEPOSIT` marks the current publication's DOI stale when the
  links a deposit would carry change: `linksBeforeChange()` before the write,
  `redepositIfChanged()` after it, around the status insert in
  `CodecheckStatusHandler::updateStatus()` and the record write in
  `CodecheckMetadataHandler::saveMetadata()`. So a certificate DOI entered after
  the status, or a status taken back, is deposited too. `markStale()` touches
  only a submitted or registered DOI, and only automatic deposit re-sends it.
  The author's wizard save is not wrapped: before publication there is nothing
  deposited to refresh.

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
tests/                       PHPUnit (37 test classes, 385 tests)
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
                               index-to-flag conversion, tested without a database),
                               I185_MoveRecordsToConfigSpec2 (how the column default
                               reads back; the rows and the second run are
                               `make check-migration`)
  SettingsUnitTests/           Actions, Manage
  OrcidUnitTests/              OrcidDepositService (which codecheckers a deposit
                               run is for — the rule that stands between a
                               publish and a request to ORCID, including the
                               bare-versus-URI iD shapes), and
                               PeerReviewPayloadBuilder (the group id the
                               payload and the registration must agree on) — #182
  SubmissionUnitTests/         AvailabilityStatementField, CodecheckCodecheckers,
                               CodecheckRepositories,
                               CodecheckSubmissionDAO, CodecheckSubmission, Schema
  WorkflowUnitTests/           CodecheckMetadataDestinations, CodecheckMetadataHandler,
                               CodecheckPublicationValidator,
                               CodecheckStatusRegisterUpdate, CodecheckYamlValidator
  PluginsXmlUnitTest.php       the Plugin Gallery listing against OJS's plugins.xsd

cypress/
  support/component.js         mounts via @cypress/vue, imports css/codecheck.css
  support/pkp-mock.js          fake window.pkp (localize, modal, registry, const) — import
                               this first in every component spec
  support/e2e.js               login, API and settings-form commands (see "E2E tests"),
                               swallow uncaught exceptions
  support/component-index.html
  tests/component/*.cy.js      14 specs, 190 tests
  tests/e2e/*.cy.js            17 specs, 95 tests
                               yaml-generation, article-sidebar-setting,
                               issue-toc-setting, issue-toc-badge,
                               private-repository, publication-validation,
                               settings-roundtrip, status-handler
  tests/visual/ui-screenshots.cy.js  screenshot pass — `make screenshots`

dev/
  inspect.mjs                Playwright page inspector -> dev/out/
  social-preview/            GitHub social preview image (#41) -> dev/out/
```

### Component tests (the reliable suite)

`npm run test:component` — **passes locally with no OJS, no database, no build step**
(128/128, ~40 s). Cypress mounts the `.vue` sources directly through Vite and stubs the
API with `cy.intercept`.

Covered: metadata form load/render, manifest files add/remove/comment, repository list
add/remove + private flag, certificate identifier reservation, removal + labels, required-field
validation, YAML preview gating, codechecker modal, review display states, data &
software availability field, the config version selector and the author's availability
statement in the read-only panel; the escaping rules in `markup.js`, the ORCID
check, and the two dialogs that ask for something — including that an empty name
or a mistyped ORCID iD keeps the dialog open and adds nothing.

`cypress/support/pkp-mock.js` reads the real `locale/en/locale.po` and its `t()`
behaves in two ways on purpose:

- a message **without** placeholders resolves to the locale key, so specs assert on a
  stable identifier instead of English copy that changes whenever wording is edited
- a message **with** placeholders resolves to the translated text with the parameters
  substituted, and **throws** when the message and the call site disagree — a missing
  parameter, an extra one, or a plugin key with no entry in the `.po` at all. That is
  what stops a renamed placeholder from silently rendering `{$specLink}` to the user
- the substitution is **OJS's own**: one `String.replace` per parameter with the
  value as the replacement *string*, so `$&`, `$'` and `$$` in a value are
  patterns, not text. A call that passes user-supplied text — a paper title in
  the contact's `mailto:` subject — has to double its `$`, and the mock now
  fails a spec that does not (#28)

Keys outside `plugins.generic.codecheck.` come from OJS's own locale files
(`common.loading`), so they are passed through unchecked.

**The mock's `useModal` renders a real dialog into the document** —
`.pkp-mock-modal` with one `.pkp-mock-modal__action` button per action, and a
`bodyComponent` mounted inside it with `bodyProps` spread on, as OJS does — so a
confirmation can be answered, and a dialog with fields filled in, in a spec
rather than stubbed. It used to answer only the first half of
`const {useModal} = pkp.modules.useModal`, which made every modal path throw, so
no spec could reach one. `cypress/support/component.js` clears leftover dialogs
between tests, because `mount()` does not clear `document.body`.

There is no fallback to the browser's `alert()`/`confirm()`/`prompt()`: these
components only ever run inside OJS's backend, where `pkp.modules.useModal`
always exists, so a fallback is code no editor can reach and no test runs. A new
browser dialog here is a bug, not a shortcut.

Not covered: `CodecheckGithubIssueDisplay.vue`, the `storeExtend` wiring in
`main.js` (menu injection, dashboard column, file-manager columns), and the
wizard DOM-scraping classes. `CodecheckStatusForm.vue` is covered only through
its dialog body, `CodecheckStatusDialog.vue`.

### E2E tests

`make test-e2e` — 95 tests across 17 specs, driving a real OJS instance.

**Several specs share submission fixtures, and each must restore what it
changes.** Submissions 8 and 9 are written by `publication-validation`,
`reviewer-rights` and `status-handler`, and `publication-metadata-info` switches
submission 7 out of CODECHECK and back in, because every seeded submission is
opted in; submission 10 is deliberately untouched
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

**Specs reach OJS through the commands in `cypress/support/e2e.js`, never by
hand.** Each of these was once copied into up to six specs, so a change in PKP
broke all of them at once:

- `cy.openCodecheckSettings()` / `cy.saveCodecheckSettings()` — the plugin grid
  link and the settings modal. `cy.codecheckSettingsForm()` yields the form;
  its selector is written nowhere else
- `cy.setCodecheckFields({selector: value})` — opens, fills and saves in one go.
  Selectors are looked up inside the form; `true` checks, `false` unchecks a
  checkbox, anything else is set with `invoke('val')` plus a `change` event,
  since a colour input refuses typing and a multilingual field's other
  languages are hidden. `cy.setCodecheckSetting(fieldId, enabled)` is the
  one-checkbox form. Specs that change settings restore them in `after()`,
  reading first with `cy.getCodecheckSetting(fieldId)` where the value they
  found is not a fixed default
- `cy.ojsApi(method, path, body)` — a request against the journal, `path`
  relative to `/index.php/codecheck/`, with the CSRF token. It yields every
  status for the spec to assert on, and **needs a backend page open**: the
  token is read off that page's `pkp` object, and a restored `cy.session()`
  leaves the browser on `about:blank`, so visit one first. It fails at once
  when there is no token rather than sending a request that is refused
- `cy.publishedArticleId()` — a published submission's id, or `null` when
  there is none; a failed request fails the test rather than reading as none

- `yaml-generation.cy.js` — YAML preview vs. download parity, preview-button gating
- `article-sidebar-setting.cy.js` — the `showArticleSidebar` setting, driven through
  the real settings form; restores the setting afterwards so the rest of the suite is
  unaffected
- `private-repository.cy.js` — a repository flagged private is visible to editors in
  the workflow form and absent from the published article and the issue TOC
- `issue-toc-setting.cy.js` — the `showInTOC` setting, and that it is independent of
  the article sidebar
- `issue-toc-badge.cy.js` — what the badge renders: image variants, height, the
  text-only form with its configured wording and colour, and where it links.
  Each variant is also captured to `cypress/screenshots/`, so the settings can be
  looked at rather than inferred
- `publication-validation.cy.js` — the CODECHECK gate on publishing, driven
  through the endpoint OJS publishes with. **It switches both deposits off
  around the one test that really publishes**: the register deposit reaches for
  GitHub, and the ORCID deposit reaches for ORCID — asking it to register the
  journal's group id *before* it checks whether there is anything to deposit, so
  having no certificate and no codechecker token does not save you.
  `make db-credentials` leaves ORCID switched off, so this is the backstop and
  not the guarantee — it covers an instance where someone turned it on by hand.
  `orcidEnabled` is read with `cy.getCodecheckSetting()` and put back.
  **The e2e suite must make no external call** — anything new that fires on
  `Publication::publish` belongs in that list. Most of it uses submission 8, in
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
- `config-version.cy.js` — the metadata endpoint refuses a config version the
  plugin does not know (`latest`, 1.0, a later one, a non-string) with a 400 and
  leaves the stored version alone. Only refusals are posted, so nothing is restored
- `settings-roundtrip.cy.js` — every field the settings form renders keeps its value
  across a save. Derives the field list from the rendered form, so a setting added
  without being wired into `readInputData()`/`execute()` fails here automatically
- `codechecker-orcid.cy.js` — the ORCID rule around the metadata endpoint: a
  mistyped iD refused with a reason and nothing written, one pasted as an
  address stored bare, and one shape of iD in the generated `codecheck.yml`
- `codechecker-dialog.cy.js` — the "add codechecker" dialog against a real OJS:
  that a refusal keeps it open **and usable**, that a mistyped ORCID iD is
  refused and a pasted orcid.org address accepted, and that cancelling adds
  nothing. It is here rather than only in the component suite because the mock
  decides how a dialog behaves, and what this is about is how OJS's does
- `availability-statement-editing.cy.js` — the availability statement on OJS's
  publication Metadata form: that the field reaches the form OJS serves to the
  workflow, sits in a group the renderer draws (a field whose `groupId` is not one
  of the form's groups is silently dropped), carries the recorded value, saves
  through the form's own endpoint and reaches the article page — and that it is
  not on the other publication forms. Which form the field lands on is unit
  tested; the round trip belongs to OJS, which is why it is pinned here
- `publication-metadata-info.cy.js` — the CODECHECK panel on Publication ›
  Metadata (#34): that the journal config reaches the dashboard with nothing
  but booleans and public addresses in the destinations, that the panel sits
  above OJS's own form, previews the `codecheck.yml`, moves the workflow to the
  CODECHECK tab, and that a submission taking no part is explained in the same
  words there and on the CODECHECK tab — submission 7, switched out through the
  REST API and back in `after()`
- `doi-deposit-links.cy.js` — the CODECHECK links in the Crossref and
  DataCite records (#19), set up and exported through OJS's own DOI API
  (`dois/submissions/assignDois`, `contexts/{id}/registrationAgency`,
  `dois/submissions/export`), which is the DOI list's Export button: no links
  while the setting is off or the certificate unpublished, the links once both
  hold, DataCite's test mode found by OJS's identifier rather than the
  rewritten DOI, and a registered DOI marked stale when the links change.
  Nothing is deposited: both agencies stay in test mode with credentials that
  are not real. Submission 4 is left at *completed* — "pending" cannot be
  recorded, only be the absence of a row

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

`make test-php` — 385 tests, green, none skipped.

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
What a form hands to a rule can still be pinned where the rule is a static
function: `CodecheckPlugin::narrowConfigVersions()` is what the settings form
applies to the config versions on save, and is unit tested directly. The wiring
that calls it is not covered while a single version is known, since a stored
selection and the default are then the same value; the pending multi-version test
in `settings-roundtrip.cy.js` takes over when a second one is added.

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
`status-handler.cy.js` instead. `OrcidDepositService` is that shape too, and the
way in was to make the one rule worth pinning pure: `depositTargets()` decides
whether there is anything to deposit, which is what now stands between a publish
and a request to ORCID (#182), and it is unit tested. **The ordering it enforces
is covered by nothing** — the live ORCID test that would exercise it is blocked
on Member API credentials, so do not read `dev/live-orcid-tests.md` as coverage.

`IssueTOC` is covered up to the point where it needs the database: the setting and
opt-in gates are unit tested with a stub application in `PKP\core\Registry`, and what
it renders is covered by `issue-toc-badge.cy.js`. `TemplateManager` cannot be mocked in
these tests — it extends Smarty, whose class body requires plugin files off a relative
include path that only resolves inside a booted OJS, so a hand-written stand-in is used
instead.

### CI (`.github/workflows/tests.yml`)

Three jobs on push/PR to `main`:

1. **PHPUnit** — checks out `pkp/ojs@stable-3_5_0` + plugin into
   `ojs/plugins/generic/codecheck`, MySQL 8 service, runs `runTests.sh` twice
   (plain + coverage), uploads `tests/results/`.
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

**It applies the credentials and leaves ORCID switched off.** Having the secrets
on file is what saves re-typing them after a reset; ORCID being *enabled* is a
separate decision, and one that makes publishing deposit to ORCID, so a rebuilt
database would have the e2e suite calling the ORCID sandbox. Every test run
therefore starts with it off, and a live ORCID test begins by turning it on in
the plugin settings (`dev/live-orcid-tests.md`). The script writes the switch
with the values the settings form uses — `on` and the empty string, not `1` —
so a setting written by either cannot be told from the other.

`dev/db-credentials.php` does the reading and writing rather than the Makefile,
for two reasons worth keeping: **phpdotenv parses the file** rather than a
second parser written in `sed`, so a quoted value, an escape or a `#` inside a
password is read the way the format says and a malformed `.env` fails there with
a message rather than halfway through writing; and **prepared statements write
it**, because whether a backslash in a secret survives a hand-escaped SQL
literal depends on the server's `sql_mode`.

**That phpdotenv is the OJS installation's, not the plugin's.** It was a plugin
dependency until #50 removed it — nothing at runtime reads `.env` any more — and
the script went on requiring `vendor/autoload.php` for a class that was no
longer there, so `make db-load` and `make db-reset` died at the credentials step
for anyone who had a `.env`: the dataset loaded and then the target failed,
which looked like a broken reset. Putting it back into the plugin's `require`
would place a development-only package in the `vendor/` Composer registers
*prepended* for every request that touches the plugin — the thing the
coding-standard note above is about — so the script takes OJS's copy instead.
`make db-credentials` already depends on `check-ojs`, and the Makefile passes
`OJS_ROOT` through; the script also accepts it from the environment and falls
back to the four-levels-up layout, as `tests/bootstrap.php` does.

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

**The live demo seed, `testData/demo/seed.sql`, follows the same rule.** It
is applied on top of the dump by `make demo-db` (`dev/live-demo.md`) and writes
`codecheck_metadata`, `codecheck_status` and whole OJS submissions by hand, so a
change to either the CODECHECK schema or OJS's submission tables can break it.
It is never loaded by a test suite, so nothing fails when it does.

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
- **Check where the symlink points before believing a green e2e run.**
  `ls -la ojs-350/plugins/generic/codecheck`. It is a single shared pointer, so a
  worktree someone repointed it to stays the tested code until it is put back,
  and the suite passes — against that worktree. A whole suite reported as
  validating a change has already been run against somebody else's branch this
  way.
- **PHPUnit is no longer affected by it.** `tests/bootstrap.php` prepends a PSR-4
  prefix for `APP\plugins\generic\codecheck\` pointing at the checkout the
  tests live in, so `make test-php` from here tests the classes beside it
  whatever the symlink says. It still needs `OJS_ROOT` for everything under
  `lib/pkp`. Two details: the prefix is longer than OJS's own `APP\plugins\`,
  which is why Composer tries it first, and the loader has to be recovered from
  `spl_autoload_functions()` because `require_once` on the autoloader answers
  `true` once PHPUnit has already loaded it. A warning on stderr says so if the
  loader cannot be reached, rather than silently testing the wrong tree.
- **The Makefile finds OJS beside the main checkout, from a worktree too.**
  `OJS_ROOT` defaults to `../ojs-350` relative to git's common directory, not
  to `$(CURDIR)`, which from `.claude/worktrees/<name>/` pointed into
  `.claude/worktrees/` and made `check-ojs` stop with "No OJS installation".
  `DATASET` stays relative to the checkout, so a worktree loads its own dump.
- **After repointing it, clear `cache/t_compile/` as well as `make clear-cache`.**
  Smarty keeps compiled templates there, keyed by the *path* — which does not
  change across the swap — and invalidates them by comparing the source's mtime,
  which does not change either. So the settings form served is the previous
  target's, with its fields, under its ids, indefinitely. `make clear-cache`
  empties `cache/opcache/` and `cache/_db/` and does not touch it.
  `rm -rf ojs-350/cache/t_compile/* ojs-350/cache/t_cache/*`.

  **It reads exactly like a coupling bug between specs.** Four specs failed in
  `make test-e2e-reverse` and passed individually, which is the signature the
  reverse target exists to detect — but the cause was a compiled `settings.tpl`
  from another worktree, whose version of the field is multilingual, so
  `#codecheckBadgeText` does not exist and `availabilityStatementHeading[en]`
  posts an array that the save stores as the string `Array`. Before believing an
  order-dependence failure, check
  `grep -c "multilingual'=>true" ojs-350/cache/t_compile/*codecheck.settings.tpl.php`
  — it should be 0 — and read the symlink again *after* the run as well as
  before, because a session working in a worktree may repoint it mid-suite.
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

### Throwaway instances, and DOI deposits to look at

Anything that writes — e2e, `db-reset`, a DOI export set-up — runs against a
throwaway OJS, never the shared `ojs-350`. `make throwaway-up THROWAWAY=<name>`
builds one beside it in ~20 s and no disk space: the OJS tree is **hard-linked**
(`cp -al`) from the shared install, with its own `config.inc.php`, `cache/`,
`files/` and `public/`, its own MariaDB in a Docker container
(`ojs-codecheck-db-<name>`, port 3307) and its own port (8352). Giving the same
`THROWAWAY=<name>` to any other target points it there — `make serve`,
`make test-e2e`, `make db-reset` — and `make throwaway-down` removes both.
Two at once need `THROWAWAY_PORT`/`THROWAWAY_DB_PORT`. Because the code is
hard-linked, **nothing may write into an OJS file in place** there: a write
through the link would change the shared install too (`sed -i` is safe, it
replaces the file). It also enables the journal: **the dataset ships it not
publicly enabled**, so every visitor not logged in is sent to the login page,
on the shared instance as well.

`make doi-test-config THROWAWAY=<name>` (`dev/doi-test-config.php`) sets the
journal up for DOI deposits that cannot reach anyone: DOIs for articles with
Crossref's documentation prefix **10.5555**, which Crossref refuses to
register; the Crossref and DataCite plugins in test mode with credentials that
are not real; DataCite's test DOI prefix **10.5072**, its retired test prefix,
which test mode rewrites every DOI to — the case the deposit hook must not look
an article up by DOI for. Crossref's schema requires a `registrant`, which is
the journal's publisher and empty in the dataset, so it sets one. `AGENCY=`
picks the registration agency, `REGISTERED=1` marks the DOIs registered (so a
change of the links can be seen marking them stale), and `CERTIFICATES="2 7"`
records those certificates as published (a status row written directly —
through the plugin it would comment on the register issue) and switches the
#19 and #183 settings on.

`make doi-export THROWAWAY=<name> ARTICLES="2 7" DOI_OUT=…` (`dev/doi-export.php`)
writes the Crossref and DataCite records OJS would deposit, validated by OJS's
exporters. **OJS 3.5's DOI exporters refuse its command-line tool**
(`DOIPubIdExportPlugin::supportsCLI()` is false), so the script boots OJS as a
command-line tool does and calls `exportXML()` itself — which is also the path
of a deposit job in a CLI worker, every generic plugin loaded with no journal.
**OJS never clears libxml's error buffer between exports**, so the script
clears it before each one, or a second record reports the first one's errors.

### Inspecting the UI

Two paths, both needing `make serve`:

- `make screenshots` — Cypress pass over settings, dashboard column, workflow
  CODECHECK tab, the publication Metadata panel, article sidebar, issue TOC and
  info page; full-page PNGs into
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

### Social preview image

`make social-preview` renders `dev/social-preview/social-preview.html` to
`dev/out/social-preview.png` at 1280x640, the size GitHub recommends (#41). The
article page in it is **a hand-written mock of OJS's default theme, not a
screenshot**, so the authors (Eglen, Nüst, Ostermann), the abstract (the
CODECHECK paper's, Nüst & Eglen 2021) and the sidebar status are plain text in
the file. The logo and badge are read from `assets/img/` by relative path. If
the plugin's sidebar block changes appearance, the mock does not follow on its
own. Uploading is manual (Settings → Social preview): GitHub has no API for it.

Host toolchain: PHP 8.2.31 (+ xdebug, mysqli, intl, gd), Node 18.20.8,
npm 10.8.2, Composer 2.8.12, MariaDB 10.6 on 3306, Docker 29.6 / Compose v5.2,
Chrome + Chromium + Firefox, Cypress 14.5.4, Playwright 1.61.

## Working agreements

### Work on the current branch; a worktree only when asked

**Implement in the main checkout, on the branch it is on.** Use a git worktree
only when told to. Ask before starting if one seems warranted, for example when
you know of another session working in this checkout at the same time. Do not
create one on your own initiative. A worktree is not a free isolation layer
here: OJS reaches the plugin through a single shared symlink, so code in a
worktree is not what PHPUnit's OJS classes, `make serve` or the e2e suite run
until that symlink is repointed (see "Local development environment").

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

Effort level, for `/code-review` — **scaled to the change, not fixed**:

| Change | Level |
|---|---|
| Small: 40–150 lines of ordinary domain or UI code | `medium` |
| Larger: over 150 lines, or anything in `api/v1/`, `classes/CodecheckRegister/`, `classes/migration/` or the `Publication::publish` / `validatePublish` hooks | `high` |
| Very critical work, where a quiet failure is expensive or cannot be undone: a migration that rewrites or drops data, the publication gate, the role sets or policies, GitHub token handling, a deposit that writes to a public repository | `max` |

`medium` is the floor, not `low`, because of what this codebase is. There is no compiler and no static analysis in CI (issue #43 is
still open), so a wrong array shape, a null context or a renamed key is found at
runtime or not at all. PHPUnit cannot reach the endpoint bodies, the migrations
or anything that touches the database, so a large share of the PHP has no test
that would catch a regression. And the failure modes here are quiet: hook
argument arrays carry references, the API handler `exit`s, and PKP swallows a
TypeError thrown inside a hook — which is exactly how `validatePublicationHook()`
went months without ever running, and how `setupAPIHandler()` left OJS answering
every plugin API call with a 404. Neither showed up as a failing test.

`max` is the exception rather than the habit: it fans out to about ten agents
at once, costs accordingly, and has been cut off by the usage limit before it
reported anything. A `high` review of each part of a large change is worth more
than a `max` run that does not finish; when a review dies on a limit, rerun it
at the level below rather than skipping it.

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
  section it fits (Frontend, Configuration, Under the hood, …), as one terse line
  stating the outcome. See "Writing the changelog" below
- Release process, packaging (`package-plugin.sh`) and the API-extension recipe are in
  `README.md`. **Three files state the release and must agree**: `version.xml`
  (`<release>`, `<date>`), `CITATION.cff` (`version`, `date-released`) and the
  heading in `CHANGELOG.md`. A release that moves one and not the others is the
  defect `version.xml` already had once, when it claimed `0.0.0.0` in a tagged
  release — see "Releases and citation metadata" below
- `.claude/ISSUE_CODE_IMPROVEMENTS.md` and `.claude/issue-65-update.md` hold earlier
  code-review findings; several are still open (dual storage, schema mismatch, dead DAO)

## Writing the changelog

**One line per change, stating how things are after it.** `CHANGELOG.md` is read by someone deciding whether to upgrade, not by someone reviewing the work. So:

- **Terse and factual: the final outcome only.** What the plugin does now. Not why it was decided, not which alternatives were rejected, not what was tried first, not what it did before, not how many places the rule used to live in. A fix says what now works (`Reserving a certificate identifier works when the journal keeps its authors anonymous`), not what was broken. The history of a fix is on the issue.
- **No manual line breaks.** One entry is one line, however long, and a paragraph is one line. Nothing is wrapped at a column, so a diff shows what changed rather than where a line happened to break. A sub-bullet, a heading, a blank line and the list markers are the only line structure. (This applies to `CHANGELOG.md`; the rest of this file is wrapped.)
- **No internal detail.** Class and method names, hook names, column names, which file a rule moved to: all of it belongs in the code, in `CLAUDE.md` or in the issue. A reader upgrading a journal cannot act on `Constants::normalizeBadgeHeight()`. Name a user-visible setting, a page or a file the journal handles (`codecheck.yml`, `register.csv`) instead.
- **Point at the issue, every time.** `(#154)` carries the whole story for anyone who wants it, at no cost to anyone who does not. Several issues on one entry is fine; entries that share an issue can be merged into one line.
- **Lessons learned go on the issue, not in the changelog.** What was established, what turned out false, what the next person should know: a comment on the issue it came from. Ask before posting one: an issue comment is published under the repository owner's name (see "Never create GitHub issues without confirmation").
- **Say so when something needs preserving and there is no obvious home.** A conclusion that shapes future work but fits neither the changelog nor an issue (a constraint discovered, a trap in OJS, a decision that will be re-litigated) belongs in `CLAUDE.md`. If it is not clear where it goes or how much of it to keep, **tell the user rather than guessing**: writing it in the wrong place is how one paragraph becomes three contradictory ones.

An entry that needs a second sentence is usually carrying a decision or a reason, and that belongs on the issue.

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

**`plugins.xml` is the Plugin Gallery listing, and it follows a release rather
than being part of one** (#157). A journal adds its raw URL on `main` to
`plugin_gallery_urls`, so a release is recorded there *after* its package is
uploaded, on `main` — step 14 of the release procedure in `README.md`, which
pastes the `<release>` block `package-plugin.sh` prints rather than copying
values by hand. Three properties to keep:

- **Releases are appended, never edited.** The `md5` pins the published
  package; OJS refuses an install whose download does not match it, so editing
  an entry or replacing an uploaded asset breaks every journal that installs it.
- **The file is never moved or renamed.** OJS fetches every gallery URL to draw
  the Plugin Gallery tab, and one that does not answer makes `loadXML('')` throw,
  which breaks the whole tab — official plugins included — not just this entry.
- **It is `export-ignore`d**: it describes packages and is never inside one.

OJS does not validate the listing when it reads it, so `PluginsXmlUnitTest` is
the only check: it validates against `lib/pkp/xml/schema/plugins.xsd` in
`OJS_ROOT` and ties each package URL to its version. It deliberately does *not*
compare the newest release with `version.xml`, which runs ahead of the listing
on a release branch.

**The Zenodo DOI is not in the file yet, and this is where it goes.** Issue #8
(beta release incl. Zenodo deposit) asks for it. The Zenodo GitHub integration
was switched on after v0.1.0.0 was published, and Zenodo archives only releases
made after that, so **the release after 0.1.0.0 is the first one with a DOI** —
README's release step 15 is the reminder. Once it exists, add it to
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
- Markup built as a string goes through `html` in `resources/js/markup.js`,
  which escapes what it interpolates — markup the plugin wrote itself has to be
  marked `raw()`. Concatenating instead puts the escaping back on the author,
  which is where #179's two defects came from. A dialog is opened through
  `resources/js/dialogs.js` and nothing else names `pkp.modules.useModal`
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
- An ORCID iD is checked where codecheckers are written, and the rule is
  `CodecheckCodecheckers` (PHP) mirrored by `resources/js/orcid.js`. It
  **computes the ISO 7064 MOD 11-2 check digit rather than calling
  `PKP\validation\ValidatorORCID`**: that resolves Laravel's `validator` out of
  the container, so it throws unless the application is fully booted and no test
  in this suite can reach it, and it insists on the full `https://orcid.org/…`
  form while the plugin stores the bare one. The class docblock has the whole
  argument; the point to keep is that a dependency nothing can test was judged
  the worse trade, not that duplication is fine.
  **One shape reaches the `codecheck.yml`**: OJS stores an author's iD as the
  URI (its own templates use the stored value as an `href`) and a codechecker's
  is bare, so `buildYaml()` normalises both. The dataset gives author 4 of
  submission 2 Josiah Carberry's iD — ORCID's published test identifier — stored
  as the URI, which is the only reason that is observable.
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
