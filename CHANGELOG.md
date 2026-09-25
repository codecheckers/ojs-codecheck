# Changelog

All notable changes to the [ojs-codecheck](https://github.com/codecheckers/ojs-codecheck) project are documented in this file.

This CHANGELOG.md is based on and adapted from [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).<br />
The [ojs-codecheck](https://github.com/codecheckers/ojs-codecheck) project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).<br />
Therefore version names are of the format `x.y.z(.0)` and incremented as follows:
- `x`: **Major** version change (e.g. when we adapt a new OJS version)
- `y`: **Minor** version change (e.g. when we add functionality or enhancements)
- `z`: **Patch** version change (e.g. when we make backward compatible bug fixes)
- `.0`: *Occasionally* appended when needed for **PKP/OJS style**

## [Unreleased]

### Added

#### Project

- Initial plugin structure and OJS 3.5.x compatibility (#2)
- [README.md](README.md) (#4), [CONTRIBUTING.md](CONTRIBUTING.md) (#3),
  [CHANGELOG.md](CHANGELOG.md) (#5), colour scheme documentation, mock-ups in the
  issues (#26)
- `CITATION.cff`, so the plugin can be cited and GitHub renders a "Cite this
  repository" entry. The Zenodo DOI follows the beta release (#24, #8)
- What a repository used as the CODECHECK register has to provide, and which
  access the personal access token needs, in [README.md](README.md) (#129)

#### Submission

- CODECHECK opt-in checkbox, and a journal-wide setting making codechecking
  opt-in, opt-out or mandatory (#128)
- CODECHECK step in the submission wizard — repositories, expected outputs and a
  data and software availability statement — shown in the review step and written
  straight into the CODECHECK record (#152)
- Public CODECHECK information page, linked from the opt-in checkbox

#### Editorial workflow

- CODECHECK tab with a metadata form for the paper reference, codecheckers,
  manifest, repositories, summary, report and certificate (#64)
- Certificate identifiers are reserved from the CODECHECK register, which opens a
  register issue (#11, #48). The issue is kept up to date as the check progresses,
  and each journal chooses which parts are updated (#132). A register holding no
  identifier yet is asked about before its first issue is opened, and one that
  cannot be read — no `id assigned` label, no readable identifiers in its issue
  titles, or unreachable — is refused with the reason, since reserving there
  would duplicate an identifier already recorded (#129, #130). The register's own
  development issues are not read as certificates (#130)
- CODECHECK status with a full history per submission, editable by role (#61,
  #141, #142). Each status change is also commented under the register issue (#150)
- Several repositories per submission, one of them marked as holding the
  `codecheck.yml` (#146)
- Existing CODECHECK metadata can be imported from GitHub, GitLab, Zenodo or OSF,
  including DOI resolution (#145)
- Repositories and manifest entries can be hidden from readers while staying
  visible to editors and codecheckers (#134)
- Entries the author submitted are marked as theirs and cannot be removed by a
  codechecker, only edited or hidden (#152)
- Repository validation messages appear in the field that caused them (#144)
- `codecheck.yml` preview and download from the form
- CODECHECK column in the editorial dashboard (#30)
- ORCID deposition for codecheckers, automatic on publication or on demand, with a
  per-codechecker status and a credentials test (#16)
- CODECHECK documentation section in the reviewer's Download & Review tab, so a
  codechecker needs no editorial access (#16)
- Editors can correct the data and software availability statement on the
  publication Metadata form (#167)
- Confirmations in the CODECHECK form — removing a manifest file, a repository or
  a codechecker, and reserving the register's first certificate identifier — use
  OJS's own dialog rather than the browser's, and the label reminder beside them
  is translatable (#130)

#### Publication

- Publication is blocked until the CODECHECK status is one the journal accepts and
  the generated `codecheck.yml` parses (#12, #32, #122, #139)
- Optional extended validation fetches the `codecheck.yml` from the selected
  repository and checks its paper title against the submission (#143)
- A `register.csv` row is deposited to the CODECHECK Register as a pull request on
  publication. A failed deposit never blocks publishing (#10)
- Publication is blocked while the repository holding the `codecheck.yml` is hidden,
  and the metadata form refuses to create that state. No other repository is
  substituted in the register (#169)

#### Published articles

- CODECHECK block in the article sidebar: badge, codecheckers and their ORCIDs,
  certificate link, check date, summary, repositories and manifest
- CODECHECK badge in the issue table of contents, switchable independently of the
  sidebar (#27)
- Configurable badge — CODE WORKS badge, CODECHECK logo, a custom image or text —
  with a height, a colour and a link target (#27)
- The author's data and software availability statement below the abstract, with
  settings to hide it, rename its heading, or omit it where there is none (#152)

#### Configuration

- GitHub personal access token, register organisation and repository, custom issue
  labels, author anonymity in register issues, and the statuses that permit
  publication
- Register deposit switch, with a warning when the configured repository has no
  `register.csv` (#156), one when it has no `id assigned` label — the label
  certificate identifiers are found by — and one when it cannot be read at all
  (#129)
- The CODECHECK config versions codecheckers may choose from. Journals offer
  version 1.0 until they choose otherwise, and the generated `codecheck.yml`
  declares the version recorded for the check
- ORCID: whether deposition is enabled, sandbox or production Member API, client ID
  and secret, and the publisher city. The secret is never rendered back (#16)
- Settings page reorganised into groups, with checkbox lists in place of hover menus

#### Under the hood

- PSR-12 plus PKP's own rules, enforced by `make lint`, a pre-commit hook, VS Code
  and a GitHub Actions workflow that also runs `php -l` (#43)
- `make test-e2e-shuffle` runs the e2e specs in a seeded random order, so a
  coupling between specs is caught and can be replayed
- Custom API under `api/v1/codecheck`, on a PKP controller with PKP's own
  authorization policies and CSRF middleware (#50)
- Database schema managed by an install migration with versioned upgrade steps,
  run when the plugin is enabled (#94)
- `WARNING` level in `CodecheckLogger`
- PHPUnit, Cypress component and end-to-end suites, a screenshot pass, a `Makefile`
  development environment and a Playwright page inspector
- The release package installs: a single top-level directory, `vendor/` included,
  development files excluded. 28 MB to 912 KB (#50)
- `version.xml` declares the release it actually is, and every file header states
  the licence the repository carries (#50)
- Each setting with a non-obvious default records it in one place and is read
  through one reader — the availability statement, dashboard column and TOC badge
  (#177, #178), the badge height and text colour (#178)
- Dropped the `vlucas/phpdotenv` dependency; credentials come from the plugin
  settings and nothing read an environment variable (#50)
- `codecheck_metadata.version` is `spec_version`, which is what it holds (#93)
- Removed an unused second way to write a CODECHECK record, which enforced neither
  of the rules the real write paths apply

### Changed

- The `codecheckEnabled` setting is `showArticleSidebar`: it never enabled the
  plugin, only the sidebar block
- Repositories are stored as a list of objects rather than a comma-separated string
- The bundled test dataset carries the complete CODECHECK schema, so loading it
  produces a working instance

### Security

- ORCID authorisation requires a logged-in user who may act on the submission, and
  finishing one acts as the person who started it. It was reachable anonymously for
  any submission id, so any ORCID account could be bound to any submission and
  credited on publication (advisory GHSA-4p3r-qgp4-g74r, #50)
- ORCID API requests verify the server's TLS certificate. They carried the client
  secret and codecheckers' access tokens with verification disabled (#16)
- A CODECHECK status change is recorded against the user who made the request, and
  the status is checked against the known ones (#50)
- Whether a user may set the status is answered from the session rather than from
  the request body (#50)
- Opening and updating register issues is restricted to a journal editor or an
  administrator (#173)
- Writing CODECHECK data is restricted to editors and the reviewer assigned to that
  submission. Any reviewer in the journal could previously rewrite any submission's
  record, its status and the public register entry (#173)
- Removed the `GET download` and `POST upload` API endpoints. Download read any
  file under the OJS installation for any account with a read role, including
  `config.inc.php`; upload wrote a caller-named file under the document root.
  Neither had a caller (#50)
- Hidden repositories are no longer published in the register issue, and the issue
  is only updated once the metadata has been saved (#154)
- A certificate, report or repository address that is not an `http(s)` URL is not
  turned into a link, and is refused when the metadata is saved. `filter_var()`
  accepts `javascript:` URLs, and these reach the article page, the register issue
  and `register.csv` (#50, #154)
- The submission wizard's review panel, the YAML preview window, the config version
  in the specification link and the log escape what they render (#50)
- Repository links on the article page carry `rel="noopener noreferrer"` (#154)
- Updated dependencies to clear 16 advisories from `composer audit`, within the
  existing version constraints

### Removed

- `CodecheckMetadataDAO` and `schema.xml`, neither reachable and both describing
  table shapes the plugin does not create
- The "Data repository" section of the article sidebar, which could never appear
  once the repository list replaced the single repository pair (#154)
- The `codecheckApiEndpoint` and `codecheckApiKey` settings, which nothing read
- The settings form's "Clear / Reset DB" button, which dropped every CODECHECK
  table. Use `make db-reset` in development (#131)

### Fixed

- Publishing an article no longer fails for journals with ORCID enabled, and a
  failed ORCID deposit no longer logs a PHP warning (#175)
- Depositing one codechecker's activity to ORCID no longer rewrites every
  codechecker's record (#175)
- The ORCID authorisation round trip completes on a journal-scoped install; the
  return leg was a bare 404 (#176)
- Reserving a certificate identifier works when the journal keeps its authors
  anonymous, which is the default (#150)
- Reserving a certificate identifier automatically works at all, and a register
  that holds no identifier yet no longer answers a bare server error (#130)
- Reserving before the register organisation and repository are configured says
  so, instead of answering a bare server error (#129)
- Linking an existing certificate identifier reports the register issue it
  linked, instead of an identifier and issue nobody had opened (#130)
- Errors from the register and from CODECHECK metadata import are reported as
  errors: they carried a status that is not an HTTP status, which left the
  browser with an empty server error and no reason to show (#130)
- Reserving an identifier by opening a new register issue no longer answers a
  server error, and a journal with the deposit enabled but no GitHub credentials
  skips it with a log line instead of abandoning the publication hook (#177)
- CODECHECK publication validation runs at all. It threw inside the hook on every
  publish, which OJS swallowed, so nothing was ever blocked — and a journal that
  had never saved the settings form had every check disabled the same way
- What the author enters in the wizard reaches the server, is shown again when they
  return, and is never emptied by a save that could not read it first. Invalid
  addresses are refused with the reason under the field, and only an address a save
  introduces is judged (#170)
- Saving the CODECHECK metadata without a repository list no longer empties it
- Which repository holds the `codecheck.yml` is recorded on the repository itself.
  As a position in the list it silently moved to a repository nobody chose, which
  decides what the article page shows, what validation fetches and what is
  deposited (#154)
- Several repositories are written to the generated `codecheck.yml` as a list, as
  the specification asks, and listed one link each on the article page (#154)
- The `repository` column holds the JSON list it was given; as `varchar(500)` four
  GitHub addresses already overflowed it, which read back as no repositories (#154)
- The generated `codecheck.yml` keeps values as the strings they were entered as.
  Stripping quotes changed their YAML type, and a title such as `[a, b]` made a
  submission unpublishable (#50)
- Checking which repository holds the `codecheck.yml` works, and a row selected
  before an address is typed no longer records a choice nothing else honours (#154)
- `locale/en/locale.po` parses, so it can be handed to a translation tool. CI
  compiles it on every push (#172)
- Viewing a published article no longer fails fatally when stored repository data
  is not in the expected format
- The CODECHECK information page no longer claims every page whose operation is
  `info`
- The `codecheck.yml` genre is created outside a web request, and only once on
  journals whose primary locale is not English
- The editorial metadata form shows the repository list the API returns
- Saving the plugin settings only calls GitHub when the register organisation or
  repository changed
- Manifest table rows line up again

## [1.0.0] - 2025-??-??

[unreleased]: https://github.com/codecheckers/ojs-codecheck/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codecheckers/ojs-codecheck/v0.0.0...v1.0.0