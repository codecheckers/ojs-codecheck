/**
 * The CODECHECK status of a submission, through the API that owns it.
 *
 * CodecheckStatusHandler is every line a database query, so there is nothing to
 * unit test without faking a connection and testing the fake. What matters is
 * the behaviour around the table: an unrecorded submission reads as pending,
 * the newest record wins, the history is append-only and newest first, and the
 * automatic update picks its status from whether a codechecker is assigned.
 * That status then gates publication and is embedded in the register issue, so
 * it is worth holding still.
 *
 * These tests write rows that nothing deletes — the table is an append-only
 * log with no delete endpoint. They restore the *current* status of the
 * submissions they touch, which is what the rest of the plugin reads; the extra
 * history rows stay. CI loads the dump fresh, and `make db-reset` does locally.
 */

const JOURNAL = 'codecheck';
const API = `/index.php/${JOURNAL}/api/v1/codecheck`;

// All three are in review with codecheckers recorded, and none is published, so
// no reader-facing spec depends on their status. 8 is kept for the automatic
// update, which behaves differently on a submission with no history yet.
const SUBMISSION = 9;
const AUTOMATIC_SUBMISSION = 8;
const UNTOUCHED_SUBMISSION = 10;

const NEEDS_CODECHECKER = 'plugins.generic.codecheck.status.needsCodechecker';
const ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';
const STALLED_AUTHOR = 'plugins.generic.codecheck.status.stalled.author';
const PENDING = 'plugins.generic.codecheck.status.pending';

/** Calls the plugin API with the session's CSRF token. */
function api(method, path, body) {
  return cy.getCsrfToken().then((csrfToken) =>
    cy.request({
      method,
      url: `${API}/${path}`,
      headers: { 'X-Csrf-Token': csrfToken },
      body,
      failOnStatusCode: false,
    })
  );
}

const getStatus = (submissionId) => api('GET', `status?submissionId=${submissionId}`);
const getHistory = (submissionId) => api('GET', `status/history?submissionId=${submissionId}`);
const updateStatus = (submissionId, status, userId) =>
  api('POST', `status/update?submissionId=${submissionId}`, { status, userId });

describe('CODECHECK status', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    // cy.getCsrfToken() reads the token off the current page's pkp object, so
    // there has to be a page: a restored session alone leaves us on about:blank.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  // Leave the submission where the suite found it: the status drives the
  // dashboard column and publication validation.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    // A page first, for the same reason as in beforeEach.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    updateStatus(SUBMISSION, ASSIGNED_CODECHECKER, 1);
    updateStatus(AUTOMATIC_SUBMISSION, ASSIGNED_CODECHECKER, 1);
  });

  it('reads as pending until something is recorded', () => {
    // Nothing is written for this submission anywhere in the suite, so it keeps
    // showing the default the handler substitutes for a missing row.
    getStatus(UNTOUCHED_SUBMISSION).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.statusRecord.status).to.eq(PENDING);
    });
  });

  it('has no history before anything is recorded', () => {
    getHistory(UNTOUCHED_SUBMISSION).then((response) => {
      // An empty history is reported as a failure with a null history rather
      // than as an empty list.
      expect(response.status).to.eq(400);
      expect(response.body.success).to.be.false;
      expect(response.body.statusHistory).to.be.null;
    });
  });

  it('records a status and reads it back', () => {
    updateStatus(SUBMISSION, NEEDS_CODECHECKER, 1).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.success).to.be.true;
      expect(response.body.statusRecord.status).to.eq(NEEDS_CODECHECKER);
    });

    getStatus(SUBMISSION).then((response) => {
      expect(response.body.statusRecord.status).to.eq(NEEDS_CODECHECKER);
    });
  });

  it('lets the newest record win and keeps the older ones', () => {
    updateStatus(SUBMISSION, NEEDS_CODECHECKER, 1);
    updateStatus(SUBMISSION, STALLED_AUTHOR, 1);

    getStatus(SUBMISSION).then((response) => {
      expect(response.body.statusRecord.status).to.eq(STALLED_AUTHOR);
    });

    getHistory(SUBMISSION).then((response) => {
      expect(response.status).to.eq(200);
      const history = response.body.statusHistory;

      expect(history.length, 'the log is append-only').to.be.at.least(2);
      expect(history[0].status, 'newest first').to.eq(STALLED_AUTHOR);
      expect(history[1].status).to.eq(NEEDS_CODECHECKER);
    });
  });

  it('records who set the status', () => {
    updateStatus(SUBMISSION, NEEDS_CODECHECKER, 1);

    getHistory(SUBMISSION).then((response) => {
      expect(response.body.statusHistory[0].user_id).to.eq(1);
    });
  });

  it('rejects a status that is not a string or a user that is not an id', () => {
    updateStatus(SUBMISSION, 42, 1).then((response) => {
      expect(response.status).to.eq(400);
      expect(response.body.success).to.be.false;
    });

    updateStatus(SUBMISSION, NEEDS_CODECHECKER, 'admin').then((response) => {
      expect(response.status).to.eq(400);
      expect(response.body.success).to.be.false;
    });
  });

  it('picks the status itself when the update comes from no user', () => {
    // userId -1 means the plugin set the status rather than a person. The
    // status sent along is ignored: the handler decides from the record, and
    // this submission has codecheckers, so the choice is "assigned".
    updateStatus(AUTOMATIC_SUBMISSION, STALLED_AUTHOR, -1).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.statusRecord.status).to.eq(ASSIGNED_CODECHECKER);
    });
  });

  it('leaves a status a person already set alone', () => {
    // Two records in and the automatic update stops deciding, so an editor's
    // choice is not overwritten the next time the metadata is saved.
    updateStatus(SUBMISSION, STALLED_AUTHOR, 1);
    updateStatus(SUBMISSION, STALLED_AUTHOR, 1);

    updateStatus(SUBMISSION, NEEDS_CODECHECKER, -1).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.statusRecord.status).to.eq(STALLED_AUTHOR);
    });

    getStatus(SUBMISSION).then((response) => {
      expect(response.body.statusRecord.status).to.eq(STALLED_AUTHOR);
    });
  });

  it('offers the whole status vocabulary alongside an update', () => {
    // The workflow form fills its dropdown from this list, so an endpoint that
    // stopped returning it would leave the form with nothing to offer.
    updateStatus(SUBMISSION, NEEDS_CODECHECKER, 1).then((response) => {
      expect(response.body.allStatuses).to.include(NEEDS_CODECHECKER);
      expect(response.body.allStatuses).to.include(ASSIGNED_CODECHECKER);
      expect(response.body.allStatuses).to.not.include(PENDING);
    });
  });
});
