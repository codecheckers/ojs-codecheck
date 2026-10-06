/**
 * Closing a codechecker's review once the check is completed (#13).
 *
 * ccodechecker's review of submission 9 is the one closed. OJS has no way to
 * reopen a submitted review, so this is the one change the suite cannot put
 * back: after a run, the review stays closed until the dataset is reloaded
 * (`make db-reset`), and a second run checks the closed state and skips the
 * closing itself. Nothing else in the suite needs that review open: a closed
 * review is still the codechecker's assignment in force. The status is put
 * back as recorded.
 */

const JOURNAL = 'codecheck';
const SUBMISSION = 9;
const CODECHECKER_REVIEW = 2;
const ADMIN_ID = 1;

const ASSIGNED = 'plugins.generic.codecheck.status.assignedCodechecker';
const COMPLETED = 'plugins.generic.codecheck.status.completed.fullReproduction';

const api = (method, path, body) => cy.ojsApi(method, `api/v1/codecheck/${path}`, body);
const recordStatus = (status) =>
  api('POST', `status/update?submissionId=${SUBMISSION}`, { submissionId: SUBMISSION, status, userId: ADMIN_ID })
    .its('status').should('eq', 200);
const openReviews = () => api('GET', `codecheckers/reviews?submissionId=${SUBMISSION}`).its('body.reviews');

describe("Closing a codechecker's review", () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    recordStatus(ASSIGNED);
  });

  it('is not offered before the check is completed', () => {
    recordStatus(ASSIGNED);
    openReviews().should('deep.equal', []);
    api('POST', `codecheckers/reviews/close?submissionId=${SUBMISSION}`, { reviewAssignmentId: CODECHECKER_REVIEW })
      .its('status').should('eq', 400);
  });

  /**
   * One test, so no order of the suite can close the review elsewhere: on a
   * dataset where an earlier run closed it, only the refusal is checked.
   */
  it("is offered from completed on, for the codecheckers' reviews alone, closes the review once", () => {
    const closeAgain = () =>
      api('POST', `codecheckers/reviews/close?submissionId=${SUBMISSION}`, { reviewAssignmentId: CODECHECKER_REVIEW })
        .then((response) => {
          expect(response.status, 'a closed review is not closed again').to.eq(400);
          expect(response.body.error).to.contain('cannot be closed');
        });

    recordStatus(COMPLETED);
    openReviews().then((reviews) => {
      // rreviewer's review is not a codechecker's, and is never offered.
      expect(reviews.map((review) => review.userId)).not.to.include(6);
      if (reviews.length === 0) {
        cy.log('The codechecker\'s review was closed by an earlier run; reload the dataset to close it again.');
        closeAgain();
        return;
      }
      expect(reviews).to.deep.equal([{ reviewAssignmentId: CODECHECKER_REVIEW, userId: 7, name: 'Cora Codechecker', round: 1 }]);

      cy.visit(`/index.php/${JOURNAL}/dashboard/editorial?workflowSubmissionId=${SUBMISSION}&workflowMenuKey=codecheck`);
      cy.contains('.codecheck-codechecker-reviews__item', 'Cora Codechecker', { timeout: 20000 })
        .find('button').click();
      cy.contains('[data-cy=dialog] button', 'Yes').click();
      cy.get('.codecheck-codechecker-reviews').should('not.exist');

      openReviews().should('deep.equal', []);
      closeAgain();
    });
  });

  it('is not offered to the codechecker themselves', () => {
    cy.ojsLogin('ccodechecker', 'ccodechecker');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
    api('GET', `codecheckers/reviews?submissionId=${SUBMISSION}`).its('status').should('eq', 401);
  });
});
