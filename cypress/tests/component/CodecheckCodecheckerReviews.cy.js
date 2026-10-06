import '../../support/pkp-mock.js';
import CodecheckCodecheckerReviews from '../../../resources/js/Components/CodecheckCodecheckerReviews.vue';

/**
 * The codecheckers' open reviews on the CODECHECK tab, each with a button
 * that closes it once the check is completed (#13).
 */
describe('CodecheckCodecheckerReviews', () => {
  const REVIEW = { reviewAssignmentId: 2, userId: 7, name: 'Cora Codechecker', round: 1 };
  const LIST = { method: 'GET', pathname: '/api/v1/codecheck/codecheckers/reviews' };
  const CLOSE = { method: 'POST', pathname: '/api/v1/codecheck/codecheckers/reviews/close' };

  const mountReviews = () =>
    cy.mount(CodecheckCodecheckerReviews, { props: { submission: { id: 9 }, status: 'plugins.generic.codecheck.status.completed.fullReproduction' } });

  it('shows nothing while no review is open', () => {
    cy.intercept(LIST, { success: true, reviews: [] }).as('list');
    mountReviews();
    cy.wait('@list');
    cy.get('.codecheck-codechecker-reviews').should('not.exist');
  });

  it('shows nothing to an editor who is refused', () => {
    cy.intercept(LIST, { statusCode: 401, body: { success: false } }).as('list');
    mountReviews();
    cy.wait('@list');
    cy.get('.codecheck-codechecker-reviews').should('not.exist');
  });

  it('closes a review once the editor confirms', () => {
    cy.intercept(LIST, { success: true, reviews: [REVIEW] }).as('list');
    cy.intercept(CLOSE, { success: true, reviews: [] }).as('close');
    mountReviews();

    cy.contains('.codecheck-codechecker-reviews__item', 'Cora Codechecker, review round 1')
      .find('button').click();
    cy.get('.pkp-mock-modal__action').contains('common.yes').click();

    cy.wait('@close').its('request.body').should('deep.equal', { reviewAssignmentId: 2 });
    cy.get('.codecheck-codechecker-reviews').should('not.exist');
  });

  it('closes nothing when the editor declines', () => {
    cy.intercept(LIST, { success: true, reviews: [REVIEW] }).as('list');
    cy.intercept(CLOSE, cy.spy().as('close'));
    mountReviews();

    cy.get('.codecheck-codechecker-reviews__item button').click();
    cy.get('.pkp-mock-modal__action').contains('common.no').click();

    cy.get('@close').should('not.have.been.called');
    cy.get('.codecheck-codechecker-reviews__item').should('have.length', 1);
  });

  it('says why when the server refuses', () => {
    cy.intercept(LIST, { success: true, reviews: [REVIEW] }).as('list');
    cy.intercept(CLOSE, { statusCode: 400, body: { success: false, error: 'This review cannot be closed.' } });
    mountReviews();

    cy.get('.codecheck-codechecker-reviews__item button').click();
    cy.get('.pkp-mock-modal__action').contains('common.yes').click();
    cy.get('.pkp-mock-modal').should('contain', 'This review cannot be closed.');
  });
});
