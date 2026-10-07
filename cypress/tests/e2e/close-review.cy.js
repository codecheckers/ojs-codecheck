/**
 * Closing a codechecker's review once the check is completed (#13).
 *
 * ccodechecker's review of submission 9 is the one closed. OJS cannot reopen a
 * submitted review, so the spec puts it back itself, through the
 * `snapshotReviewAssignment` and `restoreReviewAssignment` tasks
 * (cypress/plugins/reviewAssignmentTasks.js), which need `CYPRESS_DB_*`. The
 * status is put back as recorded; the event log keeps its entries. A run
 * interrupted between closing and restoring leaves the review closed, which
 * the next run refuses to start from: `make db-reset`.
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
const close = () =>
  api('POST', `codecheckers/reviews/close?submissionId=${SUBMISSION}`, { reviewAssignmentId: CODECHECKER_REVIEW });

describe("Closing a codechecker's review", () => {
  let snapshot = null;

  before(() => {
    cy.task('snapshotReviewAssignment', CODECHECKER_REVIEW).then((taken) => {
      expect(taken.dateCompleted, 'the dataset leaves the review open').to.eq(null);
      snapshot = taken;
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  // The status first: a failing task must not leave the check "completed" for
  // the specs that follow.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    recordStatus(ASSIGNED);
    if (snapshot) {
      cy.task('restoreReviewAssignment', snapshot);
    }
  });

  it('is not offered before the check is completed', () => {
    recordStatus(ASSIGNED);
    openReviews().should('deep.equal', []);
    close().its('status').should('eq', 400);
  });

  it("is offered from completed on, for the codecheckers' reviews alone, and closes the review once", () => {
    recordStatus(COMPLETED);
    // rreviewer's review is not a codechecker's, and is never offered.
    openReviews().should('deep.equal', [{ reviewAssignmentId: CODECHECKER_REVIEW, userId: 8, name: 'Cora Codechecker', round: 1 }]);

    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial?workflowSubmissionId=${SUBMISSION}&workflowMenuKey=codecheck`);
    cy.contains('.codecheck-codechecker-reviews__item', 'Cora Codechecker', { timeout: 20000 })
      .find('button').click();
    cy.contains('[data-cy=dialog] button', 'Yes').click();
    cy.get('.codecheck-codechecker-reviews').should('not.exist');

    openReviews().should('deep.equal', []);
    close().then((response) => {
      expect(response.status, 'a closed review is not closed again').to.eq(400);
      expect(response.body.error).to.contain('cannot be closed');
    });

    cy.task('snapshotReviewAssignment', CODECHECKER_REVIEW).then((closed) => {
      expect(closed.dateCompleted, 'completed').not.to.eq(null);
      expect(closed.recommendation, '"See comments"').to.eq(6);
      expect(closed.step, 'past the last step').to.eq(4);
    });
  });

  it('is not offered to the codechecker themselves', () => {
    cy.ojsLogin('ccodechecker', 'ccodechecker');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
    api('GET', `codecheckers/reviews?submissionId=${SUBMISSION}`).its('status').should('eq', 401);
  });
});
