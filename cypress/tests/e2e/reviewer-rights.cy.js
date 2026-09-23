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
 * journal has ORCID configured: an allowed caller gets as far as the "not
 * enabled" message, a refused one never does. That makes the *not enabled*
 * state part of what these tests assert, so they set it rather than assume it —
 * `make db-load` applies whatever ORCID credentials `.env` holds and switches
 * ORCID on, which on a freshly reloaded database sent an allowed caller to the
 * real ORCID sandbox instead.
 */
const orcid = (op, query = '') =>
  `/index.php/${JOURNAL}/codecheck/orcid/${op}${query}`;

const REFUSED = 'may connect an ORCID account to it';

describe('The ORCID authorisation routes', () => {
  before(() => {
    // Left off afterwards rather than put back: off is the state the dataset
    // ships and what the describe below also leaves behind, so the suite has
    // one answer for the journal's ORCID configuration however it is entered.
    cy.ojsLogin('admin', 'admin');
    cy.setCodecheckSetting('orcidEnabled', false);
  });

  it('close startAuth to a visitor who is not logged in', () => {
    cy.clearCookies();

    cy.request({ url: orcid('startAuth', `?submissionId=${ASSIGNED}`) }).then((response) => {
      expect(response.redirects.join(' '), 'startAuth ends at the login page').to.contain('login');
      expect(response.body).not.to.contain(REFUSED);
    });
  });

  /**
   * Read this one knowing what it does *not* prove.
   *
   * The journal in the test dataset is `enabled = 0`, and `PKPPageRouter`
   * bounces a logged-out visitor from a disabled journal to the login page
   * before `LoadHandler` runs — before this plugin has a handler at all. So an
   * anonymous request to a journal-scoped route redirects whatever the plugin
   * does, and asserting on that redirect would pin the dataset's journal flag
   * rather than any authorisation. It is `startAuth` above that is pinned by
   * the logged-in cases below, where the redirect cannot be the cause.
   *
   * The callback deliberately does *not* require a session: it is ORCID's
   * request, not the codechecker's, and its authority is the sealed `state`.
   * What has to hold is that a state this server did not mint is refused, and
   * that is what this asserts.
   */
  it('refuse a callback whose state this server did not mint', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({
      url: orcid('callback', '?code=x&state=not-a-sealed-state'),
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status, 'the callback route is reachable at all').to.eq(200);
      expect(response.body).to.contain('Security check failed');
      expect(response.body, 'no token exchange was attempted')
        .not.to.contain('token exchange');
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

/**
 * Where ORCID is told to send the codechecker back (issue #176).
 *
 * The redirect URI used to be the site-level `/index.php/index/...`, which no
 * request could reach unless the plugin was *also* enabled site-wide:
 * `CodecheckPlugin::register()` gates its hooks on `getEnabled()`, and with no
 * journal in the request that reads the `context_id IS NULL` setting, which
 * enabling the plugin for a journal never writes. The first leg worked and the
 * return leg was a bare 404, so the whole OAuth round trip failed on an
 * ordinarily configured journal.
 *
 * This pins the URI handed to ORCID rather than the 404, because the 404
 * depends on a `plugin_settings` row that no form exposes — and the test
 * dataset happens to carry it, which is why this went unnoticed.
 */
describe('The ORCID redirect URI', () => {
  /** Set the ORCID fields on the plugin settings form, which is Smarty, not Vue. */
  function openSettings() {
    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
      .first()
      .click({ force: true });
  }

  function saveSettings() {
    cy.get('form#codecheckSettings').find('button[type="submit"]').first().click();
    cy.get('#orcidEnabled', { timeout: 20000 }).should('not.exist');
  }

  before(() => {
    cy.ojsLogin('admin', 'admin');
    openSettings();
    cy.get('#orcidEnabled', { timeout: 20000 }).check();
    cy.get('input[name="orcidClientId"]').clear().type('APP-CYPRESS');
    cy.get('input[name="orcidClientSecret"]').clear().type('cypress-secret');
    saveSettings();
  });

  after(() => {
    // The secret field is write-only — an empty value means "keep", so the
    // dummy secret stays until the dataset is reloaded. Switching ORCID off is
    // what actually restores the journal's behaviour for the other specs.
    cy.ojsLogin('admin', 'admin');
    openSettings();
    cy.get('#orcidEnabled', { timeout: 20000 }).uncheck();
    cy.get('input[name="orcidClientId"]').clear();
    saveSettings();
  });

  it('sends the codechecker back to the journal, not to the site', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({
      url: orcid('startAuth', `?submissionId=${ASSIGNED}`),
      followRedirect: false,
    }).then((response) => {
      expect(response.status, 'an allowed caller is sent on to ORCID').to.eq(302);

      const redirectUri = decodeURIComponent(
        new URL(response.headers.location).searchParams.get('redirect_uri')
      );

      expect(redirectUri).to.contain(`/index.php/${JOURNAL}/codecheck/orcid/callback`);
      expect(redirectUri, 'not the site-level path').not.to.contain('/index.php/index/');
    });
  });
});
