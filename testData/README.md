# CODECHECK Plugin Test Dataset

> **This dump is self-contained.** It carries the full CODECHECK schema —
> `codecheck_metadata` (including the `issue` column), `codecheck_status`,
> `codecheck_issue_labels` and `codecheck_orcid_tokens` — plus the
> `showArticleSidebar` plugin setting, so loading it produces a working
> instance with no repair step. The plugin's migration is not involved.
>
> The shipped `config.inc.php` is not usable as-is: `files_dir` points at a
> MAMP path and the database block assumes `root`/`root` on a database named
> `ojs`. `make ojs-config` rewrites these.

## Keeping this dataset in sync

This dump is a fixture that CI's e2e job and every local development
environment load. **When you change the shape of anything stored in
`codecheck_metadata` — including the JSON blobs inside it — update this dump in
the same commit.** The plugin's migrations only run when the plugin is enabled through the UI;
they are not applied when this dump is loaded, so schema changes have to be
written into it by hand — the table definitions here must be kept in step with
`classes/migration/install/CodecheckSchemaMigration.php`.

A format change that skips this step fails silently: the seeded articles keep
loading, the tests keep passing, and the affected field just renders as empty.
After editing, run `make db-reset && make test-e2e && make screenshots` and look
at the resulting images.

## Recommended: use the Makefile

From the plugin root, `make setup` loads this dataset and wires up an OJS
installation in one step. See "Local development environment"
in the [main README](../README.md).

The manual procedure below remains valid for other setups.

## Apply the CODECHECK Test Dataset to an OJS instance

- Download [OJS 3.5](https://pkp.sfu.ca/software/ojs/download/) and install it
- Clone the [PKP Datasets Repository](https://github.com/pkp/datasets)
    - Place it somewhere on your local machine — there is no need for it to be in the direcrtory of the beforehand installed OJS instance
- Copy the directory [stable-3_5_0-codecheck](stable-3_5_0-codecheck) into `/datasets/ojs` of the PKP Datasets Repository
- `cd /ojs` in your OJS installation directory
    - `mkdir files`
    - Set environment variables (adjust credentials to match your MySQL setup):
    ```bash
    export DBTYPE=MySQL
    export DBTYPE_SYMBOLIC=mysql
    export DBUSERNAME=root
    export DBPASSWORD=root
    export DBNAME=ojs_dump
    export DBHOST=localhost
    export APP=ojs
    export BRANCH=stable-3_5_0-codecheck
    ```
    - `/path/to/datasets/tools/load.sh`
- Update `config.inc.php` database settings:
    ```
    driver = mysqli
    host = localhost
    username = root
    password = root
    name = ojs_dump
    ```
- `php -S localhost:8000` (Username: `admin`, Password: `admin`)

---

## Dataset Contents

**Journal:** CODECHECK Demo Journal (path: `codecheck`)

**Users:** All passwords follow the pattern `username` repeated twice (except `admin`)

| Username | Password | Role |
|---|---|---|
| admin | admin | Administrator |
| jmanager | jmanager | Journal Manager, Editor |
| seglen | seglen | Author |
| dnuest | dnuest | Author |
| fostermann | fostermann | Author |
| rreviewer | rreviewer | Reviewer |

**Articles:** 8 submissions from the [CODECHECK register](https://codecheck.org.uk/register/)
- 5 published (3 in Issue 1, 2 in Issue 2)
- 2 under review
- 1 submitted

Demonstrates:
- Article with two codecheckers (2020-002: Eglen + Nüst)
- Three different codecheckers (Eglen, Nüst, Ostermann)
- Multiple conference venues (AGILE, GigaScience, J Geogr Syst)
- A private repository: submission 7 (certificate 2022-009) carries one public
  and one private repository, so the "Keep private" flag can be exercised
  end to end — visible to editors in the workflow, filtered out of everything
  readers see. Covered by `cypress/tests/e2e/private-repository.cy.js`; keep
  both entries if you regenerate the dump.