/**
 * Screenshot pass over every surface the plugin renders.
 *
 * This is not a regression suite — it asserts only that each page loads and
 * that the CODECHECK element it is supposed to carry is present. Its purpose is
 * to make the UI inspectable without a browser: run `make screenshots` and read
 * the PNGs in cypress/screenshots/.
 *
 * Needs a running dev server with the test dataset loaded:
 *   make serve      (in one terminal)
 *   make screenshots
 *
 * Each surface is its own `it` so one broken page does not hide the rest.
 */

const JOURNAL = 'codecheck';

/** Discovered once in before(); shared by the workflow and article specs. */
let publishedSubmissionId;

function shoot(name) {
  // Let async Vue islands (metadata form, dashboard cells) settle first.
  cy.wait(1500);
  cy.screenshot(name, { capture: 'fullPage', overwrite: true });
}

describe('CODECHECK UI surfaces', () => {
  before(() => {
    cy.ojsLogin('admin', 'admin');

    // A page must be loaded before the CSRF token can be read off window.pkp —
    // cy.session() restores cookies but does not navigate anywhere.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    cy.publishedArticleId().then((id) => {
      publishedSubmissionId = id;
      cy.log(`published submission id: ${id ?? 'none found'}`);
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('plugin settings form', () => {
    cy.visit(`/index.php/${JOURNAL}/management/settings/website#plugins`);
    cy.contains('CODECHECK', { timeout: 15000 }).should('exist');
    shoot('01-plugin-list');
  });

  /** The settings that add the CODECHECK links to Crossref and DataCite deposits (#19). */
  it('settings: DOI deposits', () => {
    cy.openCodecheckSettings();
    cy.codecheckSettingsForm()
      .contains('.pkp_form_title', 'DOI Deposits')
      .closest('.section')
      .scrollIntoView()
      .screenshot('01a-settings-doi-deposits', { overwrite: true });
  });

  /** How the journal lists the certificate among an article's references (#183). */
  it('settings: certificate in the references', () => {
    cy.openCodecheckSettings();
    cy.codecheckSettingsForm()
      .contains('.pkp_form_title', 'Certificate in the References')
      .closest('.section')
      .scrollIntoView()
      .screenshot('01b-settings-certificate-reference', { overwrite: true });
  });

  /** How often the venue list and the lists of codecheckers are refreshed (#65). */
  it('settings: refresh of the CODECHECK lists', () => {
    cy.openCodecheckSettings();
    cy.codecheckSettingsForm()
      .find('[name="codecheckListsRefresh"]')
      .first()
      .closest('.section')
      .scrollIntoView()
      .screenshot('01c-settings-lists-refresh', { overwrite: true });
  });

  it('editorial dashboard with the CODECHECK column', () => {
    // "Assigned to me" is empty in the dataset; the published view has rows, so
    // the CODECHECK cells actually render.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial?currentViewId=published`);
    cy.get('table tbody tr', { timeout: 20000 }).should('have.length.greaterThan', 0);
    shoot('02-dashboard-column');
  });

  it('workflow CODECHECK tab', function () {
    if (!publishedSubmissionId) {
      this.skip();
    }

    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${publishedSubmissionId}` +
      `&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
    shoot('03-workflow-codecheck-tab');
  });

  /**
   * The add-codechecker dialog with the journal's directory and an offered
   * GitHub username (#186). Both answers are stubbed: the directory is empty on
   * a fresh dataset, and the real offer comes from GitHub, which no capture
   * pass reaches. Nothing is added.
   */
  it('add-codechecker dialog with an offered GitHub username', function () {
    if (!publishedSubmissionId) {
      this.skip();
    }

    cy.intercept(
      { method: 'GET', pathname: `/index.php/${JOURNAL}/api/v1/codecheck/codecheckers` },
      { success: true, codecheckers: [{ name: 'Stephen J. Eglen', orcid: '0000-0001-8607-8025', github: 'sje30' }] }
    );
    cy.intercept(
      { method: 'GET', pathname: `/index.php/${JOURNAL}/api/v1/codecheck/codecheckers/lookup` },
      { success: true, github: 'nuest', source: 'community' }
    );
    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${publishedSubmissionId}` +
      `&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
    cy.contains('.field-label', /codechecker/i).parent().find('.btn-add').click();
    cy.get('input[id^=codecheck-checker-name]').type('Daniel Nüst');
    cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-0024-5046').blur();
    cy.get('.codecheck-github-suggestion').should('be.visible');
    shoot('03a-add-codechecker-dialog');
    cy.get('body').type('{esc}');
  });

  /**
   * The CODECHECK tab's button that lists the certificate among the
   * references, and what it says it added (#183). The journal's mode and the
   * endpoint's answer are stubbed: the dataset has the mode off, and pressing
   * it for real would edit the article. Nothing is written. Submission 2,
   * because the button waits for a certificate, which not every published
   * article in the dataset has.
   */
  it('certificate reference button and the line it adds', () => {
    cy.intercept(
      { method: 'GET', pathname: `/index.php/${JOURNAL}/api/v1/codecheck/metadata` },
      (req) => req.continue((res) => {
        res.body.settings = { ...res.body.settings, certificateReferenceMode: 'button' };
      })
    );
    cy.intercept(
      { method: 'POST', pathname: `/index.php/${JOURNAL}/api/v1/codecheck/references` },
      {
        success: true,
        changed: true,
        line: 'Eglen, S. J. (2020). CODECHECK certificate 2020-002. Zenodo. https://doi.org/10.5281/zenodo.3750741',
      }
    );
    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      '?currentViewId=published&workflowSubmissionId=2&workflowMenuKey=codecheck'
    );
    cy.get('.certificate-reference', { timeout: 20000 }).scrollIntoView();
    shoot('03c-certificate-reference-button');
    cy.get('.certificate-reference button').click();
    cy.get('.certificate-reference-line').should('be.visible');
    shoot('03d-certificate-reference-added');
    cy.get('body').type('{esc}');
  });

  it('workflow publication Metadata page with the CODECHECK panel', function () {
    if (!publishedSubmissionId) {
      this.skip();
    }

    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${publishedSubmissionId}` +
      `&workflowMenuKey=publication_metadata`
    );
    cy.get('.codecheck-publication-info', { timeout: 20000 }).should('exist');
    // Open the preview so the capture shows the codecheck.yml as well.
    cy.get('.codecheck-publication-info__yaml summary').click();
    shoot('03b-workflow-publication-metadata');
  });

  it('published article page with the CODECHECK sidebar', function () {
    if (!publishedSubmissionId) {
      this.skip();
    }

    cy.visit(`/index.php/${JOURNAL}/article/view/${publishedSubmissionId}`);
    cy.get('.obj_article_details, .page_article', { timeout: 15000 }).should('exist');
    shoot('04-article-sidebar');
  });

  it('issue table of contents with the CODECHECK badge', () => {
    // The archive only lists issue titles; the per-article badge lives on an
    // issue's table of contents.
    cy.visit(`/index.php/${JOURNAL}/issue/archive`);
    cy.get('a[href*="/issue/view/"]', { timeout: 15000 }).first().click();
    cy.get('.obj_article_summary, .article_summary', { timeout: 15000 }).should('exist');
    shoot('05-issue-toc-badge');
  });

  it('CODECHECK info page', () => {
    cy.visit(`/index.php/${JOURNAL}/codecheck/info`);
    cy.get('body', { timeout: 15000 }).should('exist');
    shoot('06-codecheck-info-page');
  });

  it('submission wizard details step', () => {
    cy.visit(`/index.php/${JOURNAL}/submissions`);
    cy.get('body', { timeout: 15000 }).should('exist');
    shoot('07-submissions-list');
  });
});
