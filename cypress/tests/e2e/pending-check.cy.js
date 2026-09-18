/**
 * The article sidebar has two shapes: a completed check with its certificate,
 * and a check that has a codechecker assigned but nothing certified yet.
 *
 * Every seeded submission used to carry a certificate, so the second shape —
 * `ArticleDetails::generateSidebarDisplay()`'s `hasAssignedChecker()` branch, and
 * the whole `codecheckStatus == 'pending'` half of article_codecheck.tpl — was
 * never rendered by any test or by any page of the demo journal. Submission 10 is
 * published with a codechecker and no certificate to cover it.
 */

const JOURNAL = 'codecheck';
const PENDING_SUBMISSION = 10;
const COMPLETED_SUBMISSION = 2;

describe('A check that is under way', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('says the check is in progress instead of showing a certificate', () => {
    cy.visit(`/index.php/${JOURNAL}/article/view/${PENDING_SUBMISSION}`);

    cy.get('[data-testid="codecheck-article-sidebar"]', { timeout: 15000 })
      .should('exist')
      .and('have.class', 'codecheck-pending');

    // No certificate exists yet, so nothing may link to one.
    cy.get('[data-testid="codecheck-article-sidebar"]')
      .find('a[href*="codecheck.org.uk/register/certs"]')
      .should('not.exist');

    cy.document().its('documentElement.outerHTML').should('not.contain', '2023-001');
  });

  it('still lists the repositories, through the same partial as a finished check', () => {
    cy.visit(`/index.php/${JOURNAL}/article/view/${PENDING_SUBMISSION}`);

    cy.get('[data-testid="codecheck-article-sidebar"] .codecheck-article-repositories a')
      .should('have.length', 1)
      .each(($a) => {
        expect($a.attr('href')).to.match(/^https?:\/\/[^\s,]+$/);
      });
  });

  it('is a different sidebar from the one a finished check gets', () => {
    cy.visit(`/index.php/${JOURNAL}/article/view/${COMPLETED_SUBMISSION}`);

    cy.get('[data-testid="codecheck-article-sidebar"]', { timeout: 15000 })
      .should('exist')
      .and('not.have.class', 'codecheck-pending');

    cy.get('[data-testid="codecheck-article-sidebar"]')
      .find('a[href*="codecheck.org.uk/register/certs"]')
      .should('exist');
  });
});
