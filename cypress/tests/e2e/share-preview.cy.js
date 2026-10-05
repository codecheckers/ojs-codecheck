/**
 * The "Share Preview" help next to the report field against a real OJS, where
 * the translations and the dialog are OJS's own (#39).
 *
 * Nothing here saves, and the dialog stores and sends nothing, so submission 10,
 * which the rest of the suite leaves alone, is unchanged either way.
 */
describe('The share-preview help beside the report field', () => {
  const submissionId = 10;

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(
      '/index.php/codecheck/dashboard/editorial' +
      `?workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
  });

  const shareButton = () =>
    cy.contains('.field-label', 'Report URL').parent().contains('button', 'Share Preview');

  it('explains how to share a draft certificate and links the two guides', () => {
    shareButton().click();

    cy.get('[data-cy=dialog]').within(() => {
      cy.contains('Share a preview of the certificate').should('exist');
      cy.get('a[href="https://help.zenodo.org/docs/share/link-sharing/"]')
        .should('have.attr', 'target', '_blank')
        .and('have.attr', 'rel')
        .and('include', 'noopener');
      cy.get('a[href="https://docs.pkp.sfu.ca/learning-ojs/editorial-workflow/en/dashboard#discussions"]')
        .should('exist');
      cy.contains('button', 'Close').click();
    });

    cy.get('[data-cy=dialog]').should('not.exist');
  });

  it('leaves the report field as it was', () => {
    cy.contains('.field-label', 'Report URL').parent().find('input[type="url"]').invoke('val').then((before) => {
      shareButton().click();
      cy.get('[data-cy=dialog]').contains('button', 'Close').click();
      cy.contains('.field-label', 'Report URL').parent().find('input[type="url"]').should('have.value', before);
    });
  });
});
