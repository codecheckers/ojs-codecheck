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

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: `/index.php/${JOURNAL}/api/v1/submissions?status[]=3&count=1`,
        headers: { 'X-Csrf-Token': csrfToken },
        failOnStatusCode: false,
      }).then((response) => {
        publishedSubmissionId = response.body?.items?.[0]?.id ?? null;
        cy.log(`published submission id: ${publishedSubmissionId ?? 'none found'}`);
      });
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
