/**
 * A repository can be flagged "Keep private". Private repositories are part of
 * the CODECHECK record and must stay visible to editors and codecheckers in the
 * workflow, but must never reach readers: CodecheckSubmission::getRepositories()
 * filters them out of everything the frontend renders.
 *
 * Submission 7 in the test dataset carries one public and one private
 * repository specifically to cover this.
 */

const JOURNAL = 'codecheck';
const SUBMISSION_ID = 7;

const PUBLIC_URL = 'https://doi.org/10.6084/m9.figshare.19794289.v1';
const PRIVATE_URL = 'https://github.com/codecheckers/private-supplement-2022-009';

describe('Private repositories', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('exposes the private repository to editors in the workflow form', () => {
    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${SUBMISSION_ID}` +
      `&workflowMenuKey=codecheck`
    );

    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');

    // Both URLs are bound with v-model, so read the live input values.
    cy.get('.repository-item input[type="url"]')
      .should('have.length.at.least', 2)
      .then(($inputs) => {
        const urls = [...$inputs].map((input) => input.value);
        expect(urls, 'public repository').to.include(PUBLIC_URL);
        expect(urls, 'private repository').to.include(PRIVATE_URL);
      });
  });

  it('marks the private repository as private and leaves the public one unchecked', () => {
    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${SUBMISSION_ID}` +
      `&workflowMenuKey=codecheck`
    );

    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');

    // Guard against the assertions below passing vacuously on an empty list.
    cy.get('.repository-item').should('have.length.at.least', 2);

    cy.get('.repository-item').each(($item) => {
      const url = $item.find('input[type="url"]').val();
      const hidden = $item.find('.repo-hidden-checkbox').is(':checked');

      if (url === PRIVATE_URL) {
        expect(hidden, `${url} marked private`).to.be.true;
      } else if (url === PUBLIC_URL) {
        expect(hidden, `${url} not marked private`).to.be.false;
      }
    });
  });

  it('does not expose the private repository on the published article', () => {
    cy.visit(`/index.php/${JOURNAL}/article/view/${SUBMISSION_ID}`);

    cy.get('[data-testid="codecheck-article-sidebar"]', { timeout: 15000 }).should('exist');

    // The public repository is shown. The visible link text is truncated, so
    // assert on the href.
    cy.get('[data-testid="codecheck-article-sidebar"] a[href="' + PUBLIC_URL + '"]')
      .should('exist');

    // ... the private one appears nowhere on the page, not merely hidden.
    cy.document().its('documentElement.outerHTML').should('not.contain', PRIVATE_URL);
  });

  it('does not expose the private repository in the issue table of contents', () => {
    cy.visit(`/index.php/${JOURNAL}/issue/archive`);
    cy.get('a[href*="/issue/view/"]').first().click();
    cy.get('.obj_article_summary, .article_summary').should('exist');

    cy.document().its('documentElement.outerHTML').should('not.contain', PRIVATE_URL);
  });
});
