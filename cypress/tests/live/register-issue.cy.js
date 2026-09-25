/**
 * A LIVE test: it talks to the real CODECHECK register on GitHub and leaves
 * something behind there. It is not part of any suite — `specPattern` is
 * `cypress/tests/e2e/**`, so this file is only ever run on purpose:
 *
 *     make test-live
 *
 * See `dev/live-register-tests.md` for the whole procedure, including which
 * register to point at and how to read the result.
 *
 * What it exercises, end to end and for real:
 *
 *   1. reserving a certificate identifier, which opens an issue in the register;
 *   2. recording a CODECHECK status, which comments on that issue (#150).
 *
 * Neither can be tested against a stub: the first asks the register which
 * identifiers are taken, and the second is only observable as a comment in
 * someone else's repository.
 */

const JOURNAL = 'codecheck';

// A submission with a CODECHECK record but no reserved identifier, so the
// reservation has something to attach to. Changing this changes which
// submission the register issue names.
const SUBMISSION = Cypress.env('liveSubmissionId') || 8;

const api = (path) => `/index.php/${JOURNAL}/api/v1/codecheck/${path}`;

describe('Live: the register issue', () => {
  before(function () {
    if (!Cypress.env('live')) {
      // Guard rather than a silent pass: running this by accident writes to a
      // public repository.
      throw new Error(
        'This test writes to the real CODECHECK register. Run it with `make test-live`, ' +
        'which sets CYPRESS_live=1, and only against a testing register.'
      );
    }
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  it('reserves an identifier and opens an issue in the register', () => {
    cy.getCsrfToken().then((csrfToken) => {
      // Reserving takes the next free identifier in the register and cannot be
      // undone, so a rerun must not spend another one. If this submission already
      // carries an issue, the reservation has been done and this step is over.
      cy.request({
        method: 'GET',
        url: api(`metadata?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((existing) => {
        const already = existing.body?.codecheck?.issue?.number;
        if (already) {
          cy.log(`submission ${SUBMISSION} already has register issue #${already} — not reserving again`);
          return;
        }

      // The endpoint composes the register issue from the whole form state, not
      // from a submission id: title, authors, repositories, codecheckers and the
      // labels all end up in the issue it opens. A thin payload is refused by
      // IdentifierParameterValidator with a 400 before GitHub is touched.
      cy.request({
        method: 'POST',
        url: api(`identifier?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken, 'Content-Type': 'application/json' },
        body: {
          reserveIdentifierMode: 'api',
          // A register with no identifier yet is answered with a confirmation
          // request rather than a reservation, and the testing register starts
          // out that way. Confirming blind is deliberate here: no
          // confirmedIdentifier is sent, so whatever the server computes is
          // accepted (#130).
          confirmFirstIdentifier: true,
          issue: { url: null, number: null, labelsSelected: [] },
          submission: {
            title: `Live test from the CODECHECK OJS plugin (submission ${SUBMISSION})`,
            authorString: 'CODECHECK OJS plugin live test',
          },
          repositories: [
            { url: 'https://github.com/codecheckers/testing-dev-register', hidden: false, containsCodecheckYaml: true },
          ],
          codecheckers: [],
        },
        failOnStatusCode: false,
        timeout: 60000,
      }).then((response) => {
        cy.log(`reserve identifier → ${response.status}`);
        cy.log(JSON.stringify(response.body).slice(0, 400));

        expect(response.status, 'the register accepted the reservation').to.eq(200);
        expect(response.body.success).to.eq(true);

        // Write down what was created, so the run can be found in the register
        // afterwards and so the next step has an issue to comment on.
        cy.writeFile('cypress/tests/live/.last-run.json', {
          submissionId: SUBMISSION,
          at: new Date().toISOString(),
          response: response.body,
        });

        // The endpoint opens the issue and hands the number back, but does not
        // store it: in the workflow it is the form that saves the record
        // afterwards. Without this, nothing knows which issue to comment on.
        cy.request({
          method: 'POST',
          url: api(`metadata?submissionId=${SUBMISSION}`),
          headers: { 'X-Csrf-Token': csrfToken, 'Content-Type': 'application/json' },
          body: {
            version: '1.0',
            publication_type: 'doi',
            manifest: [],
            repository: { repositories: [] },
            codecheckers: [],
            certificate: response.body.identifier,
            issue: {
              url: response.body.issueUrl,
              number: response.body.issueNumber,
              labelsSelected: [],
            },
          },
          timeout: 60000,
        }).then((saved) => {
          expect(saved.status, 'the reservation was recorded on the submission').to.eq(200);
        });
        });
      });
    });
  });

  it('records a status, which comments on that issue', () => {
    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'POST',
        url: api(`status/update?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken, 'Content-Type': 'application/json' },
        body: {
          submissionId: SUBMISSION,
          status: 'plugins.generic.codecheck.status.assignedCodechecker',
          // The endpoint takes the user id from the payload, not the session.
          userId: 1,
        },
        failOnStatusCode: false,
        timeout: 60000,
      }).then((response) => {
        expect(response.status, 'the status was recorded').to.eq(200);
      });
    });
  });

  it('shows the issue and its comment in the register', () => {
    // Read back what the plugin stored, then look at the real issue. The
    // assertion is on the register, not on the plugin's own answer: the point of
    // a live test is that the other end actually received it.
    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: api(`metadata?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((response) => {
        const issue = response.body?.codecheck?.issue;
        expect(issue, 'the submission records a register issue').to.exist;
        expect(issue.number, 'the issue has a number').to.exist;

        const token = Cypress.env('githubToken');
        expect(token, 'CYPRESS_githubToken must be set to read the register back').to.be.a('string');

        const organization = Cypress.env('registerOrganization') || 'codecheckers';
        const repository = Cypress.env('registerRepository') || 'testing-dev-register';

        cy.request({
          method: 'GET',
          url: `https://api.github.com/repos/${organization}/${repository}/issues/${issue.number}/comments`,
          headers: { Authorization: `Bearer ${token}`, 'User-Agent': 'codecheck-ojs-live-test' },
        }).then((comments) => {
          const bodies = comments.body.map((c) => c.body).join('\n');
          cy.log(`issue #${issue.number}: ${comments.body.length} comment(s)`);
          expect(bodies, 'the status change was commented on the issue').to.contain('CODECHECK status');
        });

        cy.log(`Register issue: https://github.com/${organization}/${repository}/issues/${issue.number}`);
      });
    });
  });
});
