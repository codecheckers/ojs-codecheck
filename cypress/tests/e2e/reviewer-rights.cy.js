/**
 * What a reviewer may and may not do with CODECHECK data (issue #173).
 *
 * The API handler's role check asks whether a user holds a role anywhere in the
 * journal. Reviewers are invited per submission, so for them that question is the
 * wrong one, and `CodecheckSubmissionAccess` answers the per-submission half
 * beside it. Two rules are pinned here:
 *
 *   - anything that reaches the public CODECHECK register is for a journal
 *     editor or an administrator alone — not a reviewer, and not the codechecker;
 *   - the record, its status and an ORCID deposit are for editors, or the
 *     reviewer assigned to *that* submission.
 *
 * rreviewer is assigned to submission 9 and to nothing else.
 */

const JOURNAL = 'codecheck';
const ASSIGNED = 9;
const NOT_ASSIGNED = 8;

const ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';

/** Who the fixture says these accounts are, so the recorded actor can be checked. */
const RREVIEWER_ID = 6;
const ADMIN_ID = 1;

/** A user id the caller is not, posted to prove the body cannot set the actor. */
const SOMEONE_ELSE = 4;

const api = (path) => `/index.php/${JOURNAL}/api/v1/codecheck/${path}`;

/** A POST that expects to be refused or allowed, without throwing on 4xx. */
const post = (path, body, csrfToken) =>
  cy.request({
    method: 'POST',
    url: api(path),
    headers: { 'X-Csrf-Token': csrfToken, 'Content-Type': 'application/json' },
    body,
    failOnStatusCode: false,
  });

/**
 * Put the two submissions back where the suite expects them.
 *
 * Both tests that are *allowed* to write record a status, and submissions 8 and
 * 9 are shared: `publication-validation` writes to 8 before this spec and
 * `status-handler` asserts on both 8 and 9 after it. The status log is
 * append-only with no delete endpoint, so the only cleanup possible is to
 * record the status the dataset ships with — which is what `status-handler`
 * does in its own `after()`.
 *
 * This is easy to miss: before the status key in this spec was corrected, those
 * writes were refused as an unknown status and wrote nothing, so the spec looked
 * self-contained while it was not.
 */
function restoreStatuses() {
  cy.ojsLogin('admin', 'admin');
  cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  cy.getCsrfToken().then((csrfToken) => {
    [ASSIGNED, NOT_ASSIGNED].forEach((submissionId) => {
      post(`status/update?submissionId=${submissionId}`, {
        submissionId,
        status: ASSIGNED_CODECHECKER,
        userId: ADMIN_ID,
      }, csrfToken);
    });
  });
}

describe('A reviewer assigned to a submission', () => {
  beforeEach(() => {
    cy.ojsLogin('rreviewer', 'rreviewer');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  after(restoreStatuses);

  it('may record the check on the submission they are assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`status/update?submissionId=${ASSIGNED}`, {
        submissionId: ASSIGNED,
        status: ASSIGNED_CODECHECKER,
        // Deliberately not this caller: the actor is taken from the session,
        // so what the body claims must make no difference.
        userId: SOMEONE_ELSE,
      }, csrfToken).then((response) => {
        expect(response.status, 'the write is allowed').to.eq(200);
        expect(response.body.success).to.eq(true);
        expect(response.body.statusRecord.status).to.eq(ASSIGNED_CODECHECKER);
        expect(
          response.body.statusRecord.user_id,
          'the reviewer who asked is recorded, not the id they sent'
        ).to.eq(RREVIEWER_ID);
      });
    });
  });

  it('may not touch a submission they are not assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`metadata?submissionId=${NOT_ASSIGNED}`, {
        version: '1.0',
        repository: { repositories: [] },
      }, csrfToken).then((response) => {
        // 401, not 403: the refusal now comes from PKP's SubmissionAccessPolicy
        // (`user.authorization.roleBasedAccessDenied`) rather than the plugin's
        // own check, which answered 403. The request is refused either way, and
        // nothing in the UI branches on the code — but it is a visible change
        // to what the API returns, so it is asserted rather than loosened.
        expect(response.status).to.eq(401);
        expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
      });
    });
  });

  it('may not reserve a certificate identifier — that reaches the public register', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`identifier?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }, csrfToken)
        .then((response) => {
          // 401 since the register routes moved to the PKP controller: the
          // refusal is PKP's roleAuthorizer rather than the plugin's own role
          // check, which answered 400 or 403. Still refused, and nothing in the
          // UI branches on the code.
          expect(response.status).to.eq(401);
          expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
        });
    });
  });

  it('may not write the public register issue either', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`issue?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }, csrfToken)
        .then((response) => {
          // 401 since the register routes moved to the PKP controller: the
          // refusal is PKP's roleAuthorizer rather than the plugin's own role
          // check, which answered 400 or 403. Still refused, and nothing in the
          // UI branches on the code.
          expect(response.status).to.eq(401);
          expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
        });
    });
  });

  it('may still read CODECHECK data — the reviewer tab shows it', () => {
    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: api(`metadata?submissionId=${ASSIGNED}`),
        headers: { 'X-Csrf-Token': csrfToken },
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.status).to.eq(200);
      });
    });
  });
});

describe('An editor', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  after(restoreStatuses);

  it('may write a submission they were never assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`status/update?submissionId=${NOT_ASSIGNED}`, {
        submissionId: NOT_ASSIGNED,
        status: ASSIGNED_CODECHECKER,
        userId: SOMEONE_ELSE,
      }, csrfToken).then((response) => {
        expect(response.status, 'an editor is not limited to their assignments').to.eq(200);
        expect(response.body.success).to.eq(true);
        expect(response.body.statusRecord.status).to.eq(ASSIGNED_CODECHECKER);
        expect(
          response.body.statusRecord.user_id,
          'the editor who asked is recorded, not the id they sent'
        ).to.eq(ADMIN_ID);
      });
    });
  });
});

/**
 * The ORCID OAuth routes (advisory GHSA-4p3r-qgp4-g74r).
 *
 * These are OJS *page* handlers, not API endpoints, so none of the checks the
 * tests above exercise applies to them: no CSRF header, no role check. They were
 * reachable by an anonymous visitor because a page handler that declares no
 * authorization policy is permitted — PKP's page router uses a blacklist.
 *
 * `startAuth` answers the authorization question before it answers whether the
 * journal has ORCID configured, which is why these tests need no settings: an
 * allowed caller gets as far as the "not enabled" message, a refused one never
 * does.
 */
const orcid = (op, query = '') =>
  `/index.php/${JOURNAL}/codecheck/orcid/${op}${query}`;

const REFUSED = 'may connect an ORCID account to it';

describe('The ORCID authorisation routes', () => {
  it('are closed to a visitor who is not logged in', () => {
    cy.clearCookies();

    cy.request({ url: orcid('startAuth', `?submissionId=${ASSIGNED}`) }).then((response) => {
      expect(response.redirects.join(' '), 'startAuth ends at the login page').to.contain('login');
      expect(response.body).not.to.contain(REFUSED);
    });

    // The site-level path ORCID itself is sent back to, which carries no
    // journal. `failOnStatusCode: false` so that a 404 is reported as a 404:
    // this route exists only while the plugin is enabled at *site* level, and
    // a bare throw here would look like an authorisation failure instead.
    cy.request({
      url: '/index.php/index/codecheck/orcid/callback?code=x&state=y',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status, 'the callback route is reachable at all').to.eq(200);
      expect(response.redirects.join(' '), 'the callback ends at the login page')
        .to.contain('login');
      expect(response.body, 'the handler never ran')
        .not.to.contain('Invalid ORCID callback');
    });
  });

  it('refuse a reviewer the submission they are not assigned to', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({ url: orcid('startAuth', `?submissionId=${NOT_ASSIGNED}`) })
      .then((response) => {
        expect(response.body).to.contain(REFUSED);
      });
  });

  it('let a reviewer start on the submission they are assigned to', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({ url: orcid('startAuth', `?submissionId=${ASSIGNED}`) }).then((response) => {
      expect(response.body, 'past the authorisation check').not.to.contain(REFUSED);
      // ORCID is not configured in the test journal, so this is where an
      // allowed caller stops. That it is *this* message and not the refusal is
      // the point.
      expect(response.body).to.contain('ORCID integration is not enabled');
    });
  });

  it('refuse a submission id that does not exist', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({ url: orcid('startAuth', '?submissionId=99999') }).then((response) => {
      expect(response.body).to.contain('Submission not found');
    });
  });
});
