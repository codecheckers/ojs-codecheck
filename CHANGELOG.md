# Changelog

All notable changes to the [ojs-codecheck](https://github.com/codecheckers/ojs-codecheck) project are documented in this file.

This CHANGELOG.md is based on and adapted from [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The [ojs-codecheck](https://github.com/codecheckers/ojs-codecheck) project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Therefore version names are of the format `x.y.z(.0)` and incremented as follows:

- `x`: **Major** version change (e.g. when we adapt a new OJS version)
- `y`: **Minor** version change (e.g. when we add functionality or enhancements)
- `z`: **Patch** version change (e.g. when we make backward compatible bug fixes)
- `.0`: *Occasionally* appended when needed for **PKP/OJS style**

## [Unreleased]

### Added

#### Project

- Installable and upgradable from OJS's Plugin Gallery by adding the repository's `plugins.xml` to `plugin_gallery_urls` in `config.inc.php`, from OJS 3.5.0-4 on (#157)

#### CODECHECK register

- Everything the plugin posts to the register (the issue, each status comment, the `register.csv` pull request) ends with a signature naming the plugin and the journal, set by the new setting *Signature on GitHub posts*
- The register issue's JSON metadata records the journal's address and the OJS and plugin versions

#### Under the hood

- Five-minute live demo walkthrough with its own dataset (`make demo-db`) and screenshots of every view for backup slides (`make demo-screenshots`): [dev/live-demo.md](dev/live-demo.md)

### Changed

- The editorial CODECHECK form saves an unfinished check and refuses only invalid entries; the `codecheck.yml` preview still requires a complete record
- Checks are recorded against version 2.0 of the CODECHECK config file specification, the only version offered; `latest` and 1.0 are gone and existing records move to 2.0 on upgrade (#185)
- Saving a check with a config version the plugin does not support is refused with a reason, and the stored version is kept (#185)
- The metadata form and the YAML preview name the fields version 2.0 requires that a record still lacks, without blocking a save or publication; importing a `codecheck.yml` from a repository does not replace the paper's title, authors and DOI shown in the form (#185)

### Fixed

- The repositories and expected outputs an author enters in the submission wizard are saved for a new submission (#170)
- A comment on an expected output in the submission wizard is kept as the comment and survives reopening the draft
- A certificate identifier can be reserved before the CODECHECK settings have been saved
- The register issue's JSON metadata is valid JSON

## [0.1.0.0] - 2026-09-30

### Added

#### Project

- Initial plugin structure and OJS 3.5.x compatibility (#2)
- [README.md](README.md) (#4), [CONTRIBUTING.md](CONTRIBUTING.md) (#3), [CHANGELOG.md](CHANGELOG.md) (#5), colour scheme documentation, mock-ups in the issues (#26)
- `CITATION.cff`, rendered by GitHub as a "Cite this repository" entry; the Zenodo DOI follows the beta release (#24, #8)
- What a repository used as the CODECHECK register has to provide, and the access the personal access token needs, in [README.md](README.md) (#129)

#### Submission

- CODECHECK opt-in checkbox, and a journal-wide setting making codechecking opt-in, opt-out or mandatory (#128)
- CODECHECK step in the submission wizard (repositories, expected outputs, data and software availability statement), shown in the review step and written straight into the CODECHECK record (#152)
- Public CODECHECK information page, linked from the opt-in checkbox

#### Editorial workflow

- CODECHECK tab with a metadata form for the paper reference, codecheckers, manifest, repositories, summary, report and certificate (#64)
- Certificate identifiers are reserved from the CODECHECK register, which opens a register issue; the issue is kept up to date as the check progresses, and each journal chooses which parts are updated (#11, #48, #132)
- A register with no identifier yet is asked about before its first issue is opened; one that cannot be read (no `id assigned` label, no readable identifiers in its issue titles, unreachable) is refused with the reason (#129, #130)
- The register's own development issues are not read as certificates (#130)
- CODECHECK status with a full history per submission, editable by role (#61, #141, #142)
- Each status change is commented under the register issue and moves its labels: a check with a codechecker stops asking for one, a completed check is no longer marked in progress, and the issue is opened with the label its status asks for. Only those labels are touched; venue and other labels are left as they are (#150, #174)
- Several repositories per submission, one of them marked as holding the `codecheck.yml` (#146)
- CODECHECK metadata can be imported from GitHub, GitLab, Zenodo or OSF, including DOI resolution (#145)
- Repositories and manifest entries can be hidden from readers while staying visible to editors and codecheckers (#134)
- Entries the author submitted are marked as theirs; a codechecker can edit or hide them but not remove them (#152)
- Repository validation messages appear in the field that caused them (#144)
- `codecheck.yml` preview and download from the form
- CODECHECK column in the editorial dashboard (#30)
- ORCID deposition for codecheckers, automatic on publication or on demand, with a per-codechecker status and a credentials test (#16)
- CODECHECK documentation section in the reviewer's Download & Review tab, so a codechecker needs no editorial access (#16)
- Editors can correct the data and software availability statement on the publication Metadata form (#167)
- Confirmations in the CODECHECK form (removing a manifest file, a repository or a codechecker; reserving the register's first certificate identifier) use OJS's own dialog, and the label reminder beside them is translatable (#130)

#### Publication

- Publication is blocked until the CODECHECK status is one the journal accepts and the generated `codecheck.yml` parses (#12, #32, #122, #139)
- Optional extended validation fetches the `codecheck.yml` from the selected repository and checks its paper title against the submission (#143)
- A `register.csv` row is deposited to the CODECHECK Register as a pull request on publication; a failed deposit never blocks publishing (#10)
- Publication is blocked while the repository holding the `codecheck.yml` is hidden, the metadata form refuses to create that state, and no other repository is substituted in the register (#169)

#### Published articles

- CODECHECK block in the article sidebar: badge, codecheckers and their ORCIDs, certificate link, check date, summary, repositories and manifest
- CODECHECK badge in the issue table of contents, switchable independently of the sidebar (#27)
- Configurable badge (CODE WORKS badge, CODECHECK logo, a custom image or text) with a height, a colour and a link target (#27)
- The author's data and software availability statement below the abstract, with settings to hide it, rename its heading or omit it where there is none (#152)
- The availability statement heading and the text shown instead of a badge are set per journal language, falling back to the primary language's wording and then the default (#164)
- An article without an availability statement says "Not provided for this work." (#164)

#### Configuration

- GitHub personal access token, register organisation and repository, custom issue labels, author anonymity in register issues, and the statuses that permit publication
- Register deposit switch, with a warning when the configured repository has no `register.csv` (#156), no `id assigned` label, or cannot be read (#129)
- The CODECHECK config versions codecheckers may choose from; the generated `codecheck.yml` declares the version recorded for the check
- ORCID: whether deposition is enabled, sandbox or production Member API, client ID and secret, and the publisher city; the secret is never rendered back (#16)
- Settings page organised into groups, with checkbox lists in place of hover menus

#### Under the hood

- PSR-12 plus PKP's own rules, enforced by `make lint`, a pre-commit hook, VS Code and a GitHub Actions workflow that also runs `php -l` (#43)
- `make test-e2e-shuffle` runs the e2e specs in a seeded random order that can be replayed
- Custom API under `api/v1/codecheck`, on a PKP controller with PKP's authorization policies and CSRF middleware (#50)
- Database schema managed by an install migration with versioned upgrade steps, run when the plugin is enabled (#94)
- `WARNING` level in `CodecheckLogger`
- PHPUnit, Cypress component and end-to-end suites, a screenshot pass, a `Makefile` development environment and a Playwright page inspector
- The release package installs with a single top-level directory and `vendor/` included, development files excluded; 912 KB (#50)
- `version.xml` declares the release it is, and every file header states the licence of the repository (#50)
- Settings with a non-obvious default record it in one place and are read through one reader: availability statement, dashboard column, TOC badge (#177, #178), badge height and text colour (#178)
- Credentials come from the plugin settings; the `vlucas/phpdotenv` dependency is gone (#50)
- `codecheck_metadata.version` is `spec_version` (#93)
- One way to write a CODECHECK record; the unused second one is gone
- Markup the plugin builds as a string (dialogs, status history, the wizard's review panel) is escaped by default, and every dialog is opened through one helper; the two dialogs that ask for something are Vue components (#179, #180)

### Changed

- The `codecheckEnabled` setting is `showArticleSidebar`
- Repositories are stored as a list of objects
- The bundled test dataset carries the complete CODECHECK schema and loads into a working instance

### Security

- ORCID authorisation requires a logged-in user who may act on the submission, and finishing one acts as the person who started it (advisory GHSA-4p3r-qgp4-g74r, #50)
- ORCID API requests verify the server's TLS certificate (#16)
- A CODECHECK status change is recorded against the user who made the request, and the status is checked against the known ones (#50)
- Whether a user may set the status is answered from the session (#50)
- Opening and updating register issues is restricted to a journal editor or an administrator (#173)
- Writing CODECHECK data is restricted to editors and the reviewer assigned to that submission (#173)
- The `GET download` and `POST upload` API endpoints are removed (#50)
- Hidden repositories are not published in the register issue, and the issue is updated only once the metadata has been saved (#154)
- A certificate, report or repository address that is not an `http(s)` URL is refused when the metadata is saved and is not turned into a link (#50, #154)
- The submission wizard's review panel, the YAML preview window, the config version in the specification link and the log escape what they render (#50)
- Repository links on the article page carry `rel="noopener noreferrer"` (#154)
- Dependencies updated to clear 16 advisories from `composer audit`, within the existing version constraints

### Removed

- `CodecheckMetadataDAO` and `schema.xml`
- The "Data repository" section of the article sidebar (#154)
- The `codecheckApiEndpoint` and `codecheckApiKey` settings
- The settings form's "Clear / Reset DB" button; use `make db-reset` in development (#131)

### Fixed

- Publishing an article succeeds for journals with ORCID enabled, and a failed ORCID deposit logs no PHP warning (#175)
- Publishing an article contacts ORCID only when there is something to deposit (#182)
- A codechecker can deposit their own activity to ORCID (#182)
- A skipped deposit, and a journal peer-review group ORCID refuses, are reported in the workflow (#182)
- Depositing one codechecker's activity to ORCID leaves the other codecheckers' records alone (#175)
- The ORCID authorisation round trip completes on a journal-scoped install (#176)
- Updating a register issue adds the labels the form offers and keeps the labels saying where the check stands and any a human added (#174)
- A journal that changes its register repository does not comment on or label issues carrying the same number there (#174)
- Reserving a certificate identifier works when the journal keeps its authors anonymous, which is the default (#150)
- Reserving a certificate identifier automatically works, and a register with no identifier yet is handled (#130)
- Reserving a certificate identifier or updating its register issue works for a submission with no authors yet (#130)
- Reserving before the register organisation and repository are configured says so (#129)
- Linking an existing certificate identifier reports the register issue it linked (#130)
- Removing a certificate identifier also forgets its register issue
- Errors from the register and from CODECHECK metadata import are reported with their reason (#130)
- Reserving an identifier by opening a new register issue works, and a journal with the deposit enabled but no GitHub credentials skips it with a log line (#177)
- CODECHECK publication validation runs, including for a journal that has never saved the settings form
- What the author enters in the wizard reaches the server, is shown again on return and is not emptied by a save that could not read it; invalid addresses are refused with the reason under the field, and only an address a save introduces is judged (#170)
- Adding a codechecker without a name says so in the dialog and keeps it open; an ORCID iD is checked before it is stored, in the browser and on the server (#180)
- The generated `codecheck.yml` writes every ORCID iD the same way (#180)
- Answering a CODECHECK confirmation with Escape or by clicking outside it counts as declining (#179)
- A refused CODECHECK status change reports the reason in the dialog that asked for it, and a status the server did not record is not shown as current (#180)
- Every CODECHECK confirmation offers the same buttons in the same order, translated in every language OJS has (#179)
- A confirmation that removes something (a repository, a manifest entry, a codechecker, a reserved identifier) is marked as destructive (#179)
- The CODECHECK status history opens with one editor lookup per editor rather than per row (#179)
- "Current status" in the CODECHECK status history is translated, and the `codecheck.yml` preview is laid out like every other CODECHECK dialog (#179)
- Saving the CODECHECK metadata without a repository list keeps the stored one
- User names, file names and translations in the CODECHECK dialogs and the status history are shown as text, and the dialogs' markup is closed
- The repository holding the `codecheck.yml` is recorded on the repository itself, which decides what the article page shows, what validation fetches and what is deposited (#154)
- Several repositories are written to the generated `codecheck.yml` as a list and shown one link each on the article page (#154)
- The `repository` column holds the full JSON list (#154)
- The generated `codecheck.yml` keeps values as the strings they were entered as (#50)
- Checking which repository holds the `codecheck.yml` works, and a row selected before an address is typed records no choice (#154)
- `locale/en/locale.po` parses, and CI compiles it on every push (#172)
- Viewing a published article does not fail when stored repository data is not in the expected format
- The CODECHECK information page claims only its own page
- The `codecheck.yml` genre is created outside a web request, and once on journals whose primary locale is not English
- The editorial metadata form shows the repository list the API returns
- Saving the plugin settings calls GitHub only when the register organisation or repository changed
- `make db-reset` and `make db-load` finish for a development install with a `.env`, restoring the ORCID credentials with ORCID itself switched off
- Manifest table rows line up

[unreleased]: https://github.com/codecheckers/ojs-codecheck/compare/v0.1.0.0...HEAD
