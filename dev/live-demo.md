# Live demo

A five-minute walkthrough of the plugin on the local development instance. It
follows a paper through the workflow in order: the author submits it, the editor
gets it checked and records that in the CODECHECK register, and the result is
published for readers. Journal configuration comes last and is optional.

The demo runs on its own dataset, which is the test dataset plus
[`testData/demo/seed.sql`](../testData/demo/seed.sql). The seed adds the states a
demo needs and the test dataset deliberately does not have:

- status histories
- a draft submission waiting for its author
- a submission waiting for a codechecker
- a submission that did not opt in

**Loading the demo dataset drops the local database.** The e2e suites assert on
the test dataset as it ships, so run `make db-reset` before running them again.

## Setting up

Do this before the audience arrives. It takes about five minutes the first time
and two minutes after that.

1. **Do the one-off bring-up if it has not been done**:
   `make ojs-install && make setup`. See "Local development environment" in
   the [README](../README.md).

2. **Get a GitHub token for the testing register.** Use a fine-grained token
   for the `codecheckers` organisation, limited to
   `codecheckers/testing-dev-register`. It needs these permissions:
   - Issues: read and write
   - Contents: read
   - Pull requests: read and write

   [live-register-tests.md](live-register-tests.md) has the details. Without a
   token the register step (step 2) fails, and everything else still works.

3. **Load the demo dataset**:

   ```bash
   read -rs GITHUB_TOKEN && export GITHUB_TOKEN   # paste it: out of shell history
   make demo-db
   ```

   Pass the token through the environment as shown. On the make command line
   (`make demo-db GITHUB_TOKEN=…`) it would be visible in the process list.

   The command asks for confirmation before it drops the database; add
   `FORCE=1` to skip the question. The token is kept in `~/.codecheck-ojs-pat`,
   the same place `make db-reset` keeps it, so later runs of `make demo-db`
   need no `GITHUB_TOKEN`. It warns when no token ended up set, and clears
   OJS's caches and compiled templates.

4. **Check what will be shown, then start the server**:

   ```bash
   ls -la ../ojs-350/plugins/generic/codecheck   # must point at the checkout being demoed
   make build                                    # if resources/js changed since the last build
   make serve                                    # http://localhost:8350
   ```

   The plugin symlink is shared. If it points at a worktree, that worktree is
   what the audience sees.

5. **Open the windows the walkthrough uses**:

   | Window | Log in as | Start at |
   |---|---|---|
   | Author (private window) | `fostermann` / `fostermann` | <http://localhost:8350/index.php/codecheck/dashboard/mySubmissions> |
   | Editor | `admin` / `admin` | <http://localhost:8350/index.php/codecheck/dashboard/editorial> |
   | Reader (a second private window, not logged in) | — | <http://localhost:8350/index.php/codecheck/issue/archive> |
   | Register | GitHub, not logged in | <https://github.com/codecheckers/testing-dev-register/issues> |

6. **Rehearse, then reset.** Run through the walkthrough once, then
   `make demo-db FORCE=1` to put OJS back. The rehearsal's register issue stays
   on GitHub, because nothing deletes issues in the testing register; close it
   there by hand if it would confuse the audience. The identifier it reserved
   stays used.

## What the demo dataset contains

Journal "CODECHECK Demo Journal" (`codecheck`), two published issues. The
password of every account is its username.

| # | State | CODECHECK | Shows |
|---|---|---|---|
| 2 | published, issue 1 | certificate 2020-002, published | three repositories: the author's code, an archive with the code and data (Zenodo), and the codechecker's copy holding the `codecheck.yml`; two codecheckers |
| 3, 4, 5 | published, issues 1–2 | certificate published | complete status histories; 4 stalled on the author once on the way |
| 7 | published, issue 2 | certificate published | a private repository, which readers do not see |
| 8 | in review | completed, certificate 2022-018 | ready to publish; the repository is marked as holding the `codecheck.yml` |
| 9 | production, assigned to issue 2 | codechecker assigned, check running | publishing is refused by CODECHECK alone |
| 10 | published, issue 2 | stalled on the codechecker, no certificate | a published article whose check is not finished |
| 11 | **draft** by `fostermann` | opted in, nothing entered yet | the author's part of the walkthrough |
| 12 | submitted by `seglen` | needs a codechecker, no identifier | the fallback for the register step |
| 13 | submitted by `dnuest` | not opted in | a submission without CODECHECK: its tab says it has not opted in |

Submissions 11 to 13 use the titles, authors and abstracts of real, openly
published papers. The manuscript file of each is the dataset's sample PDF. The
seed also sets these plugin settings:

- the testing register is the register;
- the register issue follows status changes;
- only *completed* and *published* statuses may be published;
- the `register.csv` deposit is **off**, so publishing does not open a pull
  request in the register. Switch it on in the settings to show the deposit.

## Walkthrough

About five minutes: 0:30 + 1:00 + 1:30 + 1:00 + 1:00.

### 0. CODECHECK in 30 seconds

Open <http://localhost:8350/index.php/codecheck/codecheck/info>.

- **What it is.** A codechecker independently runs the authors' code and data
  and checks that it produces the results in the paper: the figures, tables and
  numbers. It checks that the results can be reproduced, not whether they are
  scientifically correct.
- **What comes out.** A certificate with its own DOI, listed in the public
  CODECHECK register under a `YYYY-NNN` identifier, and a `codecheck.yml` file
  describing the check in machine-readable form.
- **The principles.** Codecheckers record what they did but do not investigate
  further. The check is a conversation between people. Codecheckers get credit
  for the work. The process is open by default.
- **What the plugin adds.** All of this happens inside OJS: the author opts in,
  the editor manages the check and the register, and readers see the result on
  the article page.

### 1. The author submits (1:00)

In the author window, logged in as `fostermann`:

1. Open <http://localhost:8350/index.php/codecheck/submission> for a moment:
   the start form carries the checkbox "Yes, I want my paper to be
   codechecked". Do not start a new submission. The draft already has the box
   ticked.
2. Open the draft at <http://localhost:8350/index.php/codecheck/submission?id=11>.
   It opens at the Details step. Scroll to **CODECHECK Information** and enter:
   - repositories, the second after **+ Add URL**:
     `https://github.com/nuest/reproducible-research-giscience-longitudinal-study`
     and `https://doi.org/10.5281/zenodo.21097308`
   - an expected output: `outputs/AGILE_pre_post.png`, with the comment
     `Figure 1`
   - an availability statement, for example
     `Code and data are available on GitHub and archived on Zenodo.`
3. Continue to **Review**. The CODECHECK section is listed there with what was
   entered. Then **Submit**.

What to point out: an address that is not a web address (try `ftp://…`) is
refused under the field, and nothing already entered is lost.

### 2. The editor gets it checked, and the register follows (1:30)

In the editor window, logged in as `admin`:

1. On the dashboard,
   <http://localhost:8350/index.php/codecheck/dashboard/editorial>, the
   **CODECHECK** column shows the certificate identifier where one has been
   reserved, and an **Add** link straight into the CODECHECK tab where none has.
   11 has just arrived without one.
2. Open 11's CODECHECK tab:
   <http://localhost:8350/index.php/codecheck/dashboard/editorial?workflowSubmissionId=11&workflowMenuKey=codecheck>.
   The author's repositories and outputs are there, marked as the author's.
3. **Reserve the certificate identifier.** Under **Certificate Identifier**,
   choose a label under **GitHub Labels ⚙** (for example the venue type), then
   **Reserve Identifier Automatically**. The next free `YYYY-NNN` in the testing
   register is filled in. Then **Save**: until the form is saved, OJS does not
   know the issue number, and nothing can comment on the issue.
4. Switch to the register tab and reload. A new issue carries the identifier,
   the journal name and the label `id assigned`. Saving recorded the status
   *needs codechecker*, so the issue also has a comment saying so and the label
   `needs codechecker`.
5. Back in OJS, add a codechecker with **+ CODECHECKer**. Enter a mistyped
   ORCID iD first to show the dialog refusing it, then a correct one, and save.
   Because a codechecker is now assigned, saving records *codechecker assigned*.
   On GitHub a second comment appears, and `needs codechecker` is replaced by
   `work in progress`.
6. Optional: use the status form to record *stalled (author)* or
   *completed*, and open **CODECHECK Status History**. Each change is one more
   comment on the issue.

If step 1 was skipped or failed, use submission 12 instead:
<http://localhost:8350/index.php/codecheck/dashboard/editorial?workflowSubmissionId=12&workflowMenuKey=codecheck>.
Its history already holds one status, so after the codechecker is added, record
*codechecker assigned* through the status form rather than relying on the save.

### 3. Publishing with CODECHECK (1:00)

1. Open submission 8, where the check is complete:
   <http://localhost:8350/index.php/codecheck/dashboard/editorial?workflowSubmissionId=8&workflowMenuKey=codecheck>.
   **Preview CODECHECK metadata file** shows the `codecheck.yml` the plugin
   generates from the form.
2. Tick **Hide from public record** on its repository. The form refuses,
   because that repository holds the `codecheck.yml` and would be named in the
   public register.
3. Open submission 9, where the check is still running, at **Publication →
   Title & Abstract**, and press **Schedule For Publication**:
   <http://localhost:8350/index.php/codecheck/dashboard/editorial?workflowSubmissionId=9&workflowMenuKey=publication_titleAbstract>.
   OJS has nothing to object to, since the article is in an issue, and the
   publication is refused by CODECHECK alone because *codechecker assigned* is
   not an allowed status. Close the dialog without publishing.

### 4. What readers see (1:00)

In the reader window:

1. <http://localhost:8350/index.php/codecheck/issue/view/1>: every article in
   the table of contents carries the CODECHECK badge.
2. <http://localhost:8350/index.php/codecheck/article/view/2> shows the
   CODECHECK block in the sidebar: the certificate, the codecheckers with their
   ORCID iDs, the three repositories and the report. The badge links to the
   certificate's page in the register. Below the abstract is the data and
   software availability statement.
3. <http://localhost:8350/index.php/codecheck/article/view/7>: the check
   includes a private repository, and readers do not see it.
4. <http://localhost:8350/index.php/codecheck/article/view/10>: published while
   the check is still in progress. The sidebar says "Code verification is
   currently in progress." rather than showing a certificate.

### 5. Optional: journal configuration

In the editor window, open
<http://localhost:8350/index.php/codecheck/management/settings/website#plugins>,
then **CODECHECK → Settings**.

- **Badge.** Image or text, the text's wording per language and its colour,
  the height, and whether it links to the register page or to the DOI. Switch
  to text, save, and reload the issue in the reader window.
- **Display.** Switches for the article sidebar, the table-of-contents badge,
  the dashboard column and the availability statement, with its heading worded
  per language.
- **Register.** The register repository, what a register issue follows, and
  the `register.csv` deposit on publication.
- **Publication.** Which statuses may be published, and the optional extended
  check that fetches the `codecheck.yml` and compares the title.
- **ORCID.** Depositing the codechecker's review activity to ORCID. It is left
  off in the demo: it needs ORCID Member API credentials, see
  [live-orcid-tests.md](live-orcid-tests.md).

## Backup slides

`make demo-screenshots` plays the walkthrough in a headless browser and saves
one screenshot per view to `dev/out/demo/`, numbered by step (`00-info-page.png`
to `5a-settings.png`).

- **It changes the data.** The author submits 11 and statuses are recorded,
  exactly as in the live demo. So run it on a freshly loaded demo dataset, and
  run `make demo-db FORCE=1` again before presenting.
- **The register is optional.** `make demo-screenshots REGISTER=1` also
  reserves an identifier and captures the GitHub issue, and it costs a register
  issue like a rehearsal does. Without it, step 2 shows the reservation button
  and carries on without the register.
- **It reports what it could not capture.** Any view that fails is listed at
  the end and the others are still saved. A failure usually means the OJS
  markup changed.
