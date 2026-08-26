/**
 * The "CODECHECK information on published articles" setting (showArticleSidebar)
 * gates the CODECHECK block in the sidebar of published articles. It is separate
 * from the plugin's own enabled flag, so it is easy to have the plugin running,
 * metadata present, and still nothing rendered for readers.
 *
 * Drives the real settings form rather than writing the setting directly,
 * because the wiring between the form field, the stored setting and
 * ArticleDetails is exactly what regressed when the setting was renamed.
 */

const JOURNAL = 'codecheck';
const SIDEBAR = '[data-testid="codecheck-article-sidebar"]';

/** A published submission that has a CODECHECK certificate. */
let articleId;

describe('Article sidebar display setting', () => {
  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: `/index.php/${JOURNAL}/api/v1/submissions?status[]=3&count=1`,
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((response) => {
        articleId = response.body?.items?.[0]?.id;
        expect(articleId, 'a published submission to test against').to.exist;
      });
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  // Leave the journal in the state the rest of the suite expects, even if an
  // assertion above failed partway through.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.setCodecheckSetting('showArticleSidebar', true);
  });

  it('shows the CODECHECK sidebar on a published article when enabled', () => {
    cy.setCodecheckSetting('showArticleSidebar', true);

    cy.visit(`/index.php/${JOURNAL}/article/view/${articleId}`);
    cy.get(SIDEBAR).should('exist');
  });

  it('hides the CODECHECK sidebar when disabled', () => {
    cy.setCodecheckSetting('showArticleSidebar', false);

    cy.visit(`/index.php/${JOURNAL}/article/view/${articleId}`);
    cy.get(SIDEBAR).should('not.exist');
  });

  it('does not affect the issue table of contents badge', () => {
    // The two displays are controlled independently; showInTOC governs the badge.
    cy.setCodecheckSetting('showInTOC', true);
    cy.setCodecheckSetting('showArticleSidebar', false);

    cy.visit(`/index.php/${JOURNAL}/issue/archive`);
    cy.get('a[href*="/issue/view/"]').first().click();
    cy.get('.obj_article_summary, .article_summary').should('exist');
    cy.get('.codecheck-badge').should('exist');
  });
});
