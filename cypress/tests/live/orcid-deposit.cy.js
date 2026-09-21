/**
 * A LIVE test: it talks to the real ORCID sandbox and writes to a real sandbox
 * record. It is not part of any suite — `specPattern` is `cypress/tests/e2e/**`,
 * so this file is only ever run on purpose:
 *
 *     make test-orcid-live
 *
 * See `dev/live-orcid-tests.md` for the whole procedure, including why the base
 * URL cannot be localhost and what has to be registered with ORCID.
 *
 * What it exercises, end to end and for real:
 *
 *   1. the configured client id and secret, against ORCID's own token endpoint;
 *   2. the OAuth round trip — consent on sandbox.orcid.org and back, which is
 *      the only way to obtain a token and the only way to exercise the sealed
 *      `state` against a real redirect;
 *   3. depositing the check as a peer-review activity on that sandbox record;
 *   4. reading the record back through ORCID's public sandbox API, so the
 *      assertion is what ORCID stored rather than what the plugin reported.
 *
 * None of it can be tested against a stub: the point of each step is that ORCID
 * accepted something.
 *
 * **This leaves data behind.** Step 3 writes a peer-review item to the sandbox
 * record and nothing here deletes it; reruns add more. That is why it runs
 * against a sandbox account created for the purpose.
 */

const JOURNAL = 'codecheck';

const SUBMISSION = Number(Cypress.env('liveSubmissionId') || 9);

const ORCID_USER = Cypress.env('orcidUserEmail');
const ORCID_PASS = Cypress.env('orcidUserPassword');
const ORCID_ID = Cypress.env('orcidUserId');

const SANDBOX = 'https://sandbox.orcid.org';
const SANDBOX_PUBLIC_API = 'https://pub.sandbox.orcid.org/v3.0';

const api = (path) => `/index.php/${JOURNAL}/api/v1/codecheck/${path}`;

describe('Live: ORCID deposition', () => {
  before(function () {
    if (!Cypress.env('live')) {
      // Guard rather than a silent pass: running this by accident writes to a
      // real ORCID record.
      throw new Error(
        'This test writes to a real ORCID sandbox record. Run it with ' +
        '`make test-orcid-live`, which sets CYPRESS_live=1.'
      );
    }
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('accepts the configured client credentials', () => {
    cy.visit(`/index.php/${JOURNAL}/submissions`);

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: api('orcid-test'),
        headers: { 'X-Csrf-Token': csrfToken },
        failOnStatusCode: false,
      }).then((response) => {
        // The body carries `step` so a failure says which half broke: the
        // journal metadata the deposit needs, or the credentials themselves.
        expect(
          response.body.success,
          `orcid-test failed at step "${response.body.step}": ${response.body.error}`
        ).to.eq(true);
      });
    });
  });

  it('completes the OAuth round trip and records a token', function () {
    if (!ORCID_PASS) {
      // Not a silent pass: say why, because this is the step that proves the
      // redirect URI registration and the sealed state actually work.
      this.skip();
    }

    cy.visit(`/index.php/${JOURNAL}/codecheck/orcid/startAuth?submissionId=${SUBMISSION}`);

    // The consent screen is ORCID's own origin.
    cy.origin(SANDBOX, { args: { ORCID_USER, ORCID_PASS } }, ({ ORCID_USER, ORCID_PASS }) => {
      cy.get('#username-input, input[name="username"]', { timeout: 30000 })
        .first()
        .type(ORCID_USER);
      cy.get('#password-input, input[name="password"]').first().type(ORCID_PASS, { log: false });
      cy.get('#signin-button, button[type="submit"]').first().click();

      // Already-granted access skips straight through, so the authorise button
      // is optional rather than expected.
      cy.get('body', { timeout: 30000 }).then(($body) => {
        const authorise = $body.find('#authorize-button, button[name="authorize"]');
        if (authorise.length) {
          cy.wrap(authorise.first()).click();
        }
      });
    });

    // Back on the journal: the popup page reports success to its opener, and the
    // token is what the status endpoint should now show.
    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'GET',
        url: api(`orcid-status?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken },
      }).then((response) => {
        const authorised = (response.body.codecheckers || []).filter((c) => c.orcidId);
        expect(authorised, 'a token was recorded for at least one codechecker').to.have.length.greaterThan(0);

        if (ORCID_ID) {
          expect(authorised.map((c) => c.orcidId)).to.include(ORCID_ID);
        }
      });
    });
  });

  it('deposits the check and ORCID has it on the record', function () {
    if (!ORCID_ID) {
      // Without the iD there is nothing to read the record back by, and
      // asserting only on the plugin's own answer would prove nothing.
      this.skip();
    }

    cy.visit(`/index.php/${JOURNAL}/submissions`);

    cy.getCsrfToken().then((csrfToken) => {
      cy.request({
        method: 'POST',
        url: api(`orcid-deposit?submissionId=${SUBMISSION}`),
        headers: { 'X-Csrf-Token': csrfToken, 'Content-Type': 'application/json' },
        body: { submissionId: SUBMISSION, orcidId: ORCID_ID },
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.body.success, JSON.stringify(response.body)).to.eq(true);

        const mine = (response.body.results || []).filter((r) => r.orcidId === ORCID_ID);
        expect(mine, 'the deposit reported a result for this iD').to.have.length(1);
        expect(mine[0].putCode, `deposit failed: ${mine[0].error}`).to.be.ok;

        // The assertion that matters: ORCID says so, not the plugin.
        cy.request({
          method: 'GET',
          url: `${SANDBOX_PUBLIC_API}/${ORCID_ID}/peer-reviews`,
          headers: { Accept: 'application/json' },
        }).then((record) => {
          const groups = record.body['group'] || [];
          const putCodes = groups.flatMap((g) =>
            (g['peer-review-group'] || []).flatMap((prg) =>
              (prg['peer-review-summary'] || []).map((s) => String(s['put-code']))
            )
          );

          expect(putCodes, 'the put-code the plugin was given is on the ORCID record')
            .to.include(String(mine[0].putCode));
        });
      });
    });
  });
});
