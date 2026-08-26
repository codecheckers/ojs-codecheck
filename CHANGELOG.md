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

- Initial plugin structure (Issue #2)
- OJS 3.5.x compatibility
- Documentation: [README.md](README.md) (Issue #4), [CONTRIBUTING.md](CONTRIBUTING.md) (Issue #3), [CHANGELOG.md](CHANGELOG.md) (Issue #5)
- Color scheme documentation in [README.md](README.md)
- Mock-Ups in Issue descriptions (Issue #26)

#### Submission

- CODECHECK opt-in checkbox on the submission start form, and a journal-wide mode
  setting choosing whether codechecking is opt-in, opt-out or mandatory for authors
  (Issue #128)
- CODECHECK section in the submission wizard for repositories, the manifest of
  expected outputs, and a data and software availability statement, shown in the
  wizard's review step. What the author enters goes straight into the CODECHECK
  record the codechecker works on, rather than into a separate copy (Issue #152)
- Public CODECHECK information page explaining the process to authors, linked from
  the opt-in checkbox

#### Editorial workflow

- CODECHECK tab in the editorial workflow with a metadata form covering the paper
  reference, codecheckers, manifest, repositories, summary, report and certificate
  (Issue #64)
- Certificate identifier reservation: the next free `YYYY-NNN` identifier is taken
  from the CODECHECK register and a register issue is opened for it (Issues #11, #48)
- The register issue is kept up to date as the CODECHECK progresses; which of title,
  body and status are updated is configurable (Issue #132)
- CODECHECK status with a full history per submission, editable from the workflow and
  restricted by user role (Issues #61, #141, #142)
- Multiple repositories per submission, with one marked as containing the
  `codecheck.yml` file (Issue #146)
- Import of existing CODECHECK metadata from a repository — GitHub, GitLab, Zenodo and
  OSF, including DOI resolution (Issue #145)
- Repositories and manifest entries can be hidden. Hidden entries stay visible to
  editors and codecheckers but are excluded from the generated `codecheck.yml` and
  from everything readers see (Issue #134)
- Repositories and manifest entries the submitting author provided are marked
  "Submitted by author". A codechecker can edit them or hide them, but cannot
  remove them, so what the author supplied cannot silently disappear from the
  record (Issue #152)
- Output file names in the manifest are editable, so a codechecker can correct a
  path without removing the entry and adding it again (Issue #152)
- A "Now" link beside "Time the check was completed" fills in the current date
  and time
- The metadata form opens with a short explanation of what the form produces,
  linking to the CODECHECK config file specification for the details. The link
  follows the selected config version, so it points at the specification that
  actually governs the fields below it
- Validation warnings and errors for repository metadata shown directly in the
  repositories field (Issue #144)
- `codecheck.yml` generation with preview and download, and file upload/download for
  manifest entries
- CODECHECK column in the editorial dashboard showing the certificate identifier, or a
  link to start a CODECHECK (Issue #30)

#### Publication

- Publication is blocked while the CODECHECK is incomplete: the status must be one the
  journal has approved for publication, and the generated `codecheck.yml` must parse
  (Issues #12, #32, #122, #139)
- Optional extended validation that additionally fetches the `codecheck.yml` from the
  selected repository and checks the paper title against the submission (Issue #143)
- A `register.csv` row is deposited to the CODECHECK Register as a pull request when a
  CODECHECK-opted-in article is published. Failures never block publication (Issue #10)

#### Published articles

- CODECHECK certificate displayed in the article sidebar with the badge, codecheckers
  and their ORCIDs, certificate link, check date, summary, repositories and manifest
- CODECHECK badge in the issue table of contents (Issue #27)
- Configurable badge: CODE WORKS badge, CODECHECK logo, a custom image or text only,
  with a configurable height (Issue #27)
- Setting to show or hide the badge in issue tables of contents, independent of the
  article sidebar, so a journal can use either display on its own
- The author's data and software availability statement is shown below the abstract
  on the article landing page. An article whose author provided no statement says so
  instead of staying silent. Three settings: hide the section entirely, rename its
  heading, or leave the section out of articles with no statement (Issue #152)

#### Configuration

- Setting for where the CODECHECK badge links to: the certificate's page in the
  CODECHECK register, or its DOI. The badge on the article landing page is now a
  link as well, and both badges follow the same setting. Until now the link was
  built only for a `CODECHECK-YYYY-NNN` certificate while identifiers are stored as
  `YYYY-NNN`, so every badge and the article page's certificate link pointed nowhere

- Plugin settings for the GitHub personal access token, the register organisation and
  repository, custom issue labels, author anonymity in register issues, and which
  CODECHECK statuses permit publication
- Setting to enable or disable the register deposit, with a warning when the configured
  register repository has no `register.csv` (Issue #156)
- The metadata form shows the author's data and software availability statement in the
  read-only paper metadata, so a codechecker can see what the author said about where
  the materials are. The three fields that moved into the editable repository and
  manifest lists are no longer repeated above them
- The data and software availability settings are grouped into their own section on
  the plugin settings page, alongside the submission, GitHub and publication groups
- The badge / logo settings are shown in the same bordered group as every other
  setting instead of loose at the end of the page
- The statuses that permit publication are chosen from a plain list of checkboxes
  rather than a hover menu, matching the other multiple-choice settings
- The text shown in place of the badge, when a journal chooses "No badge", is
  configurable, as is the colour it is written in; cleared, the text falls back to
  "CODECHECK" and the colour to the CODECHECK green
- The destructive "Clear / Reset DB" action sits at the very bottom of the settings
  page instead of between the submission and GitHub settings
- Setting listing which CODECHECK config versions codecheckers can choose from in the
  metadata form. Only the enabled versions are offered, and the selector is inactive
  when a journal has settled on a single version. Journals offer version 1.0 until
  they choose otherwise, so a check records the specification it was done against
  rather than a moving target. The generated `codecheck.yml` declares the version
  recorded for the check instead of always claiming 1.0

#### Under the hood

- CODECHECK publication validation works again. It asked the router for the page
  handler to find the submission, but publishing goes through the REST API where
  there is none, so every publish attempt threw inside the hook; OJS logged
  "failed to handle the hook" and published anyway. The submission now comes from
  the hook itself, so a status the journal does not accept blocks publication as
  it was meant to

- Custom API under `api/v1/codecheck` with CSRF and role-based access control
- Database schema managed by an install migration with versioned upgrade steps, run
  when the plugin is enabled (Issue #94)
- `WARNING` log level in `CodecheckLogger`, alongside the existing `DEBUG`, `INFO` and
  `ERROR` levels. Use it for conditions that are unexpected but recoverable, such as
  stored data in an unexpected format that is skipped rather than treated as fatal.
  Log lines are prefixed `[codecheck][warning]`.
- Test suites: PHPUnit unit tests, Cypress component tests for the Vue components,
  Cypress end-to-end tests, and a screenshot pass over every UI surface
- Local development environment driven by a `Makefile`, and a Playwright page inspector
  for debugging rendered pages

### Changed

- The `codecheckEnabled` setting is now `showArticleSidebar`. It never enabled or
  disabled the plugin — it only controls whether the CODECHECK block appears in the
  sidebar of published articles, which is what its label now says.
- Repositories are stored as a list of objects carrying the URL and the private flag,
  replacing the earlier comma-separated string.
- The bundled test dataset carries the complete CODECHECK schema, so loading it
  produces a working instance without a separate repair step.

### Security

- Updated dependencies to clear 16 advisories reported by `composer audit`, affecting
  `guzzlehttp/guzzle` (9, high and medium), `guzzlehttp/psr7` (4, medium) and
  `symfony/yaml` (3, low). All were resolved within the existing version constraints,
  so no dependency requirement changed.
### Removed

- `CodecheckMetadataDAO` and `schema.xml`. Neither was reachable: the DAO queried
  columns that do not exist and was referenced only by its own unit test, and OJS 3.5
  installs plugin schemas through the migration rather than an ADODB schema file. Both
  described table shapes that disagreed with the one the plugin actually creates.
- The `codecheckApiEndpoint` and `codecheckApiKey` settings. Both were written on
  every save but no field ever rendered them and nothing ever read them, so they
  could not be set and had no effect.

### Fixed

- Rows in the manifest table line up again. Entries submitted by the author were
  taller than the others, and the output file and description inputs sat on
  different lines in every row because the file cell also carries the file size.

- Saving the plugin settings no longer makes a GitHub request every time. The
  register repository is only checked for its `register.csv` when the configured
  organisation or repository actually changes, instead of on every save.

- Viewing a published article could fail with a fatal error when the stored repository
  data was not in the expected format, because the logger had no `warning()` method.
- The `LoadHandler` hook claimed any page whose operation was `info`, not just the
  CODECHECK information page, and overwrote the requested page name on every request
  that did not match.
- `codecheck.yml` genre creation failed outside a web request, and could create a
  duplicate genre on journals whose primary locale is not English.
- Loading the GitHub register client no longer requires a `.env` file to be present.
- The editorial metadata form silently showed an empty repository list, because it only
  handled repository data in string form while the API returns it already decoded.

## [1.0.0] - 2025-??-??

[unreleased]: https://github.com/codecheckers/ojs-codecheck/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codecheckers/ojs-codecheck/v0.0.0...v1.0.0