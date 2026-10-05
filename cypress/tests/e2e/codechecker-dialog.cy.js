/**
 * The "add codechecker" dialog against a real OJS, which is the only place the
 * thing #180 is about can be seen.
 *
 * OJS's `Dialog` disables every one of its own action buttons on the first
 * click and never re-enables them, and draws no close X while a dialog has
 * actions — so a dialog that stays open to say why it refused would have
 * nothing left to press. The plugin therefore opens these with no actions and
 * the body draws its own buttons. `cypress/support/pkp-mock.js` now models the
 * disabling, but only a real dialog proves the arrangement works.
 */
describe('The add-codechecker dialog', () => {
  // Submission 10 is the one the rest of the suite leaves alone, and nothing
  // here saves, so the record it carries is unchanged either way.
  const submissionId = 10;

  beforeEach(() => {
    // Leaving the ORCID field asks OJS for a GitHub username, which reaches
    // the CODECHECK community list on GitHub; the e2e suite makes no external
    // call, so the answer is stubbed (#186).
    cy.intercept(
      { method: 'GET', pathname: '/index.php/codecheck/api/v1/codecheck/codecheckers/lookup' },
      { success: true, github: null }
    );
    cy.ojsLogin('admin', 'admin');
    cy.visit(
      '/index.php/codecheck/dashboard/editorial' +
      `?workflowSubmissionId=${submissionId}&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
  });

  const openDialog = () =>
    cy.contains('.field-label', /codechecker/i).parent().find('.btn-add').click();

  /**
   * The names are distinctive because the fixture carries codecheckers of its
   * own, and because nothing here saves: what these assert on is what the
   * unsaved form holds.
   */
  const codecheckers = () => cy.get('.codecheck-metadata-form');

  it('refuses an empty name and can still be corrected and submitted', () => {
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.contains('.modal-actions button', 'Add').click();
      cy.get('.modal-field-error').should('be.visible');

      // the whole point: the dialog is still usable after a refusal, where
      // OJS's own buttons would both be disabled for good by that first click
      cy.contains('.modal-actions button', 'Add').should('not.be.disabled');
      cy.get('input[id^=codecheck-checker-name]').type('Adalovelace Corrected');
      cy.contains('.modal-actions button', 'Add').click();
    });

    cy.get('[data-cy=dialog]').should('not.exist');
    codecheckers().should('contain', 'Adalovelace Corrected');
  });

  it('refuses an ORCID iD whose check digit does not agree', () => {
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.get('input[id^=codecheck-checker-name]').type('Adalovelace Badorcid');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0098');
      cy.contains('.modal-actions button', 'Add').click();
      cy.get('.modal-field-error').should('be.visible');
    });

    cy.get('[data-cy=dialog]').should('exist');
    codecheckers().should('not.contain', 'Adalovelace Badorcid');
  });

  it('accepts an ORCID iD copied out of the address bar', () => {
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.get('input[id^=codecheck-checker-name]').type('Adalovelace Pasted');
      cy.get('input[id^=codecheck-checker-orcid]').type('https://orcid.org/0000-0002-1825-0097');
      cy.contains('.modal-actions button', 'Add').click();
    });

    cy.get('[data-cy=dialog]').should('not.exist');
    codecheckers().should('contain', '0000-0002-1825-0097');
  });

  it('closes without adding anything when cancelled', () => {
    openDialog();

    cy.get('[data-cy=dialog]').within(() => {
      cy.get('input[id^=codecheck-checker-name]').type('Adalovelace Cancelled');
      cy.contains('.modal-actions button', /cancel/i).click();
    });

    cy.get('[data-cy=dialog]').should('not.exist');
    codecheckers().should('not.contain', 'Adalovelace Cancelled');
  });
});
