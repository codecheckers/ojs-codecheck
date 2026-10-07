/**
 * Closing a codechecker's review (#13) emails the submission's editors, as
 * OJS does when a reviewer submits: OJS's *Review complete* email.
 *
 * ccodechecker's review of submission 9 is closed through the API (not the
 * CODECHECK tab, which reads the lists) and reopened afterwards, as in
 * e2e/close-review.cy.js. The status is put back as found; *pending* cannot be
 * recorded, so it becomes *codechecker assigned*, as close-review.cy.js
 * leaves it. Submission 9 has no editor, so jmanager is assigned for the spec
 * (after() removes every Journal editor on it, of which the dataset has none).
 */

import '../../support/mail.js';

const SUBMISSION = 9;
const REVIEW_STAGE = 3;
const CODECHECKER_REVIEW = 2;
const JOURNAL_EDITOR_GROUP = 3;
const JMANAGER = { id: 5, email: 'jmanager@mailinator.com' };

const PENDING = 'plugins.generic.codecheck.status.pending';
const ASSIGNED = 'plugins.generic.codecheck.status.assignedCodechecker';
const COMPLETED = 'plugins.generic.codecheck.status.completed.fullReproduction';

/** OJS's REVIEW_COMPLETE subject, "Review complete: … #<submission> …"; the mailbox is cleared first. */
const REVIEW_COMPLETE = new RegExp(`^Review complete: .*#${SUBMISSION}\\b`);

describe('Email: a closed codechecker review', () => {
  let snapshot = null;
  let statusToRestore = null;

  before(() => {
    cy.snapshotOpenReview(CODECHECKER_REVIEW).then((taken) => {
      snapshot = taken;
    });
  });

  // The status first: a failing task must not leave the check "completed".
  after(() => {
    cy.openBackend();
    if (statusToRestore) {
      cy.recordCodecheckStatus(SUBMISSION, statusToRestore);
    }
    cy.removeParticipants(SUBMISSION, REVIEW_STAGE, [JOURNAL_EDITOR_GROUP]);
    if (snapshot) {
      cy.reopenReview(snapshot);
    }
  });

  it("is sent to the submission's editors", () => {
    cy.openBackend();
    cy.ojsApi('GET', `api/v1/codecheck/status?submissionId=${SUBMISSION}`)
      .its('body.statusRecord.status')
      .then((status) => {
        statusToRestore = status === PENDING ? ASSIGNED : status;
      });
    cy.assignParticipant(SUBMISSION, REVIEW_STAGE, JOURNAL_EDITOR_GROUP, JMANAGER.id);
    cy.recordCodecheckStatus(SUBMISSION, COMPLETED);
    cy.clearMail();

    cy.ojsApi('POST', `api/v1/codecheck/codecheckers/reviews/close?submissionId=${SUBMISSION}`, {
      reviewAssignmentId: CODECHECKER_REVIEW,
    }).its('status').should('eq', 200);

    cy.mailTo(JMANAGER.email, REVIEW_COMPLETE).should('have.length', 1);
  });
});
