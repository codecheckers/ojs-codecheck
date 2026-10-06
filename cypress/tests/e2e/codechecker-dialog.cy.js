/**
 * The "add codechecker" dialog against a real OJS (#13, #180).
 *
 * A codechecker is a reviewer assigned to the submission, so the dialog offers
 * those reviewers who are not on the list yet. OJS's `Dialog` disables its own
 * action buttons on the first click and draws no close X while a dialog has
 * actions, so the dialog draws its own; only a real dialog proves that works.
 *
 * Nothing here saves: what is asserted is what the unsaved form holds.
 */
describe('The add-codechecker dialog', () => {
  const openCodecheckTab = (submissionId) => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(
      '/index.php/codecheck/dashboard/editorial' +
      `?workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
  };

  const openDialog = () =>
    cy.contains('.field-label', /codechecker/i).parent().find('.btn-add').click();

  /**
   * Submission 9 has two reviewers: ccodechecker, already its codechecker,
   * and rreviewer, on a double-anonymous review.
   */
  it('offers the reviewers who are not codecheckers yet, and adds the one chosen', () => {
    openCodecheckTab(9);
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.get('select[id^=codecheck-checker-reviewer] option')
        .then(($options) => [...$options].slice(1).map((option) => option.textContent.trim()))
        .should('deep.equal', ['Rosa Reviewer']);

      // The refusal keeps the dialog usable, where OJS's own buttons would
      // both be disabled for good by that first click.
      cy.contains('.modal-actions button', 'Add').click();
      cy.get('.modal-field-error').should('be.visible');
      cy.contains('.modal-actions button', 'Add').should('not.be.disabled');

      cy.get('select[id^=codecheck-checker-reviewer]').select('Rosa Reviewer');
      cy.get('.codecheck-double-anonymous').should('be.visible');
      cy.contains('.modal-actions button', 'Add').click();
    });

    cy.get('[data-cy=dialog]').should('not.exist');
    cy.get('.codecheckers-list').should('contain', 'Rosa Reviewer');
  });

  /**
   * Submission 10 was accepted without review: it has no review round, so
   * nobody can be assigned to it as a reviewer, and it gets no codechecker.
   */
  it('explains how to assign one where no reviewer is assigned', () => {
    openCodecheckTab(10);
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.get('.codecheck-no-reviewers').should('contain', 'Add Reviewer');
      cy.get('select').should('not.exist');
      cy.contains('.modal-actions button', 'Cancel').click();
    });
    cy.get('[data-cy=dialog]').should('not.exist');
  });
});
