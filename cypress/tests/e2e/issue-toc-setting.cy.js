/**
 * The "CODECHECK badge in issue tables of contents" setting (showInTOC) gates
 * the badge IssueTOC renders next to each completed article.
 *
 * It is independent of showArticleSidebar: a journal can show the full CODECHECK
 * block on the article page without the badge in listings, or the other way
 * round. Before this setting existed the badge could not be turned off at all.
 */

const JOURNAL = 'codecheck';
const BADGE = '.codecheck-badge';

function visitAnIssue() {
  cy.visit(`/index.php/${JOURNAL}/issue/archive`);
  cy.get('a[href*="/issue/view/"]').first().click();
  cy.get('.obj_article_summary, .article_summary').should('exist');
}

describe('Issue table of contents badge setting', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  // Leave the journal as the rest of the suite expects it, even after a failure.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.setCodecheckSetting('showInTOC', true);
  });

  it('shows the badge when enabled', () => {
    cy.setCodecheckSetting('showInTOC', true);

    visitAnIssue();
    cy.get(BADGE).should('exist');
  });

  it('hides the badge when disabled', () => {
    cy.setCodecheckSetting('showInTOC', false);

    visitAnIssue();
    cy.get(BADGE).should('not.exist');
  });

  it('leaves the article sidebar alone when the badge is disabled', () => {
    cy.setCodecheckSetting('showArticleSidebar', true);
    cy.setCodecheckSetting('showInTOC', false);

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: `/index.php/${JOURNAL}/api/v1/submissions?status[]=3&count=1`,
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((response) => {
        const articleId = response.body.items[0].id;

        cy.visit(`/index.php/${JOURNAL}/article/view/${articleId}`);
        cy.get('[data-testid="codecheck-article-sidebar"]').should('exist');
      });
    });
  });
});
