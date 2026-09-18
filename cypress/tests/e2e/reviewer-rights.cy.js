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

describe('A reviewer assigned to a submission', () => {
  beforeEach(() => {
    cy.ojsLogin('rreviewer', 'rreviewer');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  it('may record the check on the submission they are assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`status/update?submissionId=${ASSIGNED}`, {
        submissionId: ASSIGNED,
        status: 'plugins.generic.codecheck.status.codecheckerAssigned',
      }, csrfToken).then((response) => {
        // Whatever the endpoint makes of the payload, it must not be refused
        // for who is asking.
        expect(response.status).to.not.eq(403);
        expect(JSON.stringify(response.body)).to.not.contain('assigned to this submission');
      });
    });
  });

  it('may not touch a submission they are not assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`metadata?submissionId=${NOT_ASSIGNED}`, {
        version: '1.0',
        repository: { repositories: [] },
      }, csrfToken).then((response) => {
        expect(response.status).to.eq(403);
        expect(response.body.success).to.eq(false);
      });
    });
  });

  it('may not reserve a certificate identifier — that reaches the public register', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`identifier?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }, csrfToken)
        .then((response) => {
          expect(response.status).to.be.oneOf([400, 403]);
          expect(response.body.success).to.eq(false);
        });
    });
  });

  it('may not write the public register issue either', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`issue?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }, csrfToken)
        .then((response) => {
          expect(response.status).to.be.oneOf([400, 403]);
          expect(response.body.success).to.eq(false);
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

  it('may write a submission they were never assigned to', () => {
    cy.getCsrfToken().then((csrfToken) => {
      post(`status/update?submissionId=${NOT_ASSIGNED}`, {
        submissionId: NOT_ASSIGNED,
        status: 'plugins.generic.codecheck.status.codecheckerAssigned',
      }, csrfToken).then((response) => {
        expect(response.status).to.not.eq(403);
      });
    });
  });
});
