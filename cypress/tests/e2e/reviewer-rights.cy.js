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
 *   - the record, its status and an ORCID deposit are for editors, or a
 *     codechecker of *that* submission: a reviewer assigned to it whose
 *     account is linked to an entry of its codechecker list (#13).
 *
 * ccodechecker and rreviewer are both assigned to submission 9 and to nothing
 * else; only ccodechecker is linked, so rreviewer is a reviewer and nothing more.
 */

const JOURNAL = 'codecheck';
const ASSIGNED = 9;
const NOT_ASSIGNED = 8;

const ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';

/** Who the fixture says these accounts are, so the recorded actor can be checked. */
const RREVIEWER_ID = 6;
const CODECHECKER_ID = 7;
const ADMIN_ID = 1;

/** A user id the caller is not, posted to prove the body cannot set the actor. */
const SOMEONE_ELSE = 4;

/** A plugin API call; a refusal is yielded as a response, not thrown. */
const api = (method, path, body) => cy.ojsApi(method, `api/v1/codecheck/${path}`, body);
const post = (path, body) => api('POST', path, body);

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
  [ASSIGNED, NOT_ASSIGNED].forEach((submissionId) => {
    post(`status/update?submissionId=${submissionId}`, {
      submissionId,
      status: ASSIGNED_CODECHECKER,
      userId: ADMIN_ID,
    });
  });
}

describe('The codechecker of a submission', () => {
  beforeEach(() => {
    cy.ojsLogin('ccodechecker', 'ccodechecker');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  after(restoreStatuses);

  it('may record the check on the submission they are assigned to', () => {
    post(`status/update?submissionId=${ASSIGNED}`, {
      submissionId: ASSIGNED,
      status: ASSIGNED_CODECHECKER,
      // Deliberately not this caller: the actor is taken from the session,
      // so what the body claims must make no difference.
      userId: SOMEONE_ELSE,
    }).then((response) => {
      expect(response.status, 'the write is allowed').to.eq(200);
      expect(response.body.success).to.eq(true);
      expect(response.body.statusRecord.status).to.eq(ASSIGNED_CODECHECKER);
      expect(
        response.body.statusRecord.user_id,
        'the codechecker who asked is recorded, not the id they sent'
      ).to.eq(CODECHECKER_ID);
    });
  });

  it('may not touch a submission they are not assigned to', () => {
    post(`metadata?submissionId=${NOT_ASSIGNED}`, {
      version: '2.0',
      repository: { repositories: [] },
    }).then((response) => {
      // 401, not 403: the refusal now comes from PKP's SubmissionAccessPolicy
      // (`user.authorization.roleBasedAccessDenied`) rather than the plugin's
      // own check, which answered 403. The request is refused either way, and
      // nothing in the UI branches on the code — but it is a visible change
      // to what the API returns, so it is asserted rather than loosened.
      expect(response.status).to.eq(401);
      expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
    });
  });

  it('may not reserve a certificate identifier — that reaches the public register', () => {
    post(`identifier?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }).then((response) => {
      // 401 since the register routes moved to the PKP controller: the
      // refusal is PKP's roleAuthorizer rather than the plugin's own role
      // check, which answered 400 or 403. Still refused, and nothing in the
      // UI branches on the code.
      expect(response.status).to.eq(401);
      expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
    });
  });

  it('may not write the public register issue either', () => {
    post(`issue?submissionId=${ASSIGNED}`, { submissionId: ASSIGNED }).then((response) => {
      // 401 since the register routes moved to the PKP controller: the
      // refusal is PKP's roleAuthorizer rather than the plugin's own role
      // check, which answered 400 or 403. Still refused, and nothing in the
      // UI branches on the code.
      expect(response.status).to.eq(401);
      expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
    });
  });

  /**
   * The recorded codecheckers' ORCID iDs decide whose account may be credited
   * for the check, so a reviewer who could edit the list could name an account
   * they hold and connect it (GHSA-4p3r-qgp4-g74r). Their save still goes
   * through — the rest of the record stays theirs to save — and keeps the list
   * as stored, whatever it posted.
   */
  it('may save the record but not change who it names as codecheckers', () => {
    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck').then((stored) => {
      cy.saveCodecheckRecord(ASSIGNED, stored, [
        ...stored.codecheckers,
        { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097' },
      ]).its('status').should('eq', 200);

      api('GET', `metadata?submissionId=${ASSIGNED}`)
        .its('body.codecheck.codecheckers').should('deep.equal', stored.codecheckers);
    });
  });

  /**
   * The certificate identifier and the register issue it names are a journal
   * manager's (#65): the reviewer's form shows them read-only, and their save
   * keeps what is stored, as it does the codecheckers.
   */
  it('may neither manage the identifier nor change it by saving', () => {
    api('GET', 'labels').its('status').should('eq', 401);

    api('GET', `metadata?submissionId=${ASSIGNED}`).then(({ body }) => {
      expect(body.permissions.manageIdentifier).to.eq(false);
      const stored = body.codecheck;
      cy.saveCodecheckRecord(ASSIGNED, {
        ...stored,
        certificate: '1999-001',
        issue: { url: 'https://github.com/someone/else/issues/1', number: 1, labelsSelected: [] },
      }, stored.codecheckers).its('status').should('eq', 200);

      api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck').should((after) => {
        expect(after.certificate).to.eq(stored.certificate);
        expect(after.issue).to.deep.equal(stored.issue);
      });
    });
  });

  /** What the form offers follows what the endpoints accept (#127). */
  it('is offered writing the assigned submission, and little else', () => {
    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.permissions').should('include', {
      write: true,
      editCodecheckers: false,
      manageIdentifier: false,
      addCertificateReference: false,
    });
    api('GET', `status?submissionId=${ASSIGNED}`).its('body.canUpdate').should('eq', true);
    api('GET', `orcid-status?submissionId=${ASSIGNED}`).its('body.depositScope').should('eq', 'own');
  });

  it('may still read CODECHECK data — the reviewer tab shows it', () => {
    api('GET', `metadata?submissionId=${ASSIGNED}`).its('status').should('eq', 200);
  });

  it('is given the CODECHECK form on the reviewer page', () => {
    cy.visit(`/index.php/${JOURNAL}/reviewer/submission/${ASSIGNED}`);
    cy.window().its('codecheckReviewerData.submissionId').should('eq', ASSIGNED);
  });

  it('may not list the reviewers or link anyone (#13)', () => {
    api('GET', `codecheckers/reviewers?submissionId=${ASSIGNED}`).its('status').should('eq', 401);
  });
});

/**
 * Another reviewer of the same opted-in submission, assigned but not linked to
 * the codechecker list (#13): they give a regular review and nothing more.
 */
describe('A reviewer who is not a codechecker', () => {
  beforeEach(() => {
    cy.ojsLogin('rreviewer', 'rreviewer');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  it('may not record the check', () => {
    post(`status/update?submissionId=${ASSIGNED}`, {
      submissionId: ASSIGNED,
      status: ASSIGNED_CODECHECKER,
      userId: RREVIEWER_ID,
    }).then((response) => {
      expect(response.status).to.eq(401);
      expect(response.body.error).to.eq('user.authorization.roleBasedAccessDenied');
    });
  });

  it('may not save the record', () => {
    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck').then((stored) => {
      cy.saveCodecheckRecord(ASSIGNED, { ...stored, summary: 'Not theirs to write' }, stored.codecheckers)
        .its('status').should('eq', 401);
    });
  });

  it('is offered no writing, and no ORCID deposit', () => {
    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.permissions.write').should('eq', false);
    api('GET', `status?submissionId=${ASSIGNED}`).its('body.canUpdate').should('eq', false);
    api('GET', `orcid-status?submissionId=${ASSIGNED}`).its('body.depositScope').should('eq', 'none');
  });

  it('is not given the CODECHECK form on the reviewer page', () => {
    cy.visit(`/index.php/${JOURNAL}/reviewer/submission/${ASSIGNED}`);
    cy.window().its('codecheckReviewerData').should('be.undefined');
  });
});

describe('An editor', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
  });

  after(restoreStatuses);

  it('may manage the certificate identifier', () => {
    api('GET', `metadata?submissionId=${NOT_ASSIGNED}`).its('body.permissions.manageIdentifier').should('eq', true);
  });

  it('is offered every action of the form (#127)', () => {
    api('GET', `metadata?submissionId=${NOT_ASSIGNED}`).its('body.permissions').should('include', {
      write: true,
      editCodecheckers: true,
      manageIdentifier: true,
    });
    api('GET', `status?submissionId=${NOT_ASSIGNED}`).its('body.canUpdate').should('eq', true);
  });

  /**
   * A codechecker is a reviewer assigned to the submission, so a link to any
   * other account is refused, and a link to a reviewer is copied from their
   * account whatever the request said (#13).
   */
  describe('linking codecheckers', () => {
    let storedRecord = null;

    beforeEach(() => {
      api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck').then((stored) => {
        storedRecord ??= stored;
      });
    });

    after(() => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/submissions`);
      cy.saveCodecheckRecord(ASSIGNED, storedRecord, storedRecord.codecheckers).its('status').should('eq', 200);
    });

    it('lists the reviewers assigned to the submission, and how each review is held', () => {
      api('GET', `codecheckers/reviewers?submissionId=${ASSIGNED}`).its('body.reviewers').then((reviewers) => {
        const byId = Object.fromEntries(reviewers.map((reviewer) => [reviewer.userId, reviewer]));
        expect(Object.keys(byId).map(Number)).to.have.members([RREVIEWER_ID, CODECHECKER_ID]);
        expect(byId[CODECHECKER_ID]).to.include({ name: 'Cora Codechecker', orcid: '0000-0002-1694-233X', doubleAnonymous: false });
        expect(byId[RREVIEWER_ID].doubleAnonymous).to.eq(true);
      });
    });

    it('refuses a link to someone who is not a reviewer of the submission', () => {
      cy.saveCodecheckRecord(ASSIGNED, storedRecord, [
        ...storedRecord.codecheckers,
        { userId: SOMEONE_ELSE, name: 'Not a reviewer', orcid: '', github: '' },
      ]).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.error).to.contain('not a reviewer assigned to this submission');
      });
    });

    it('copies a linked reviewer from their account', () => {
      cy.saveCodecheckRecord(ASSIGNED, storedRecord, [
        ...storedRecord.codecheckers,
        { userId: RREVIEWER_ID, name: 'Someone else entirely', orcid: '0000-0002-1825-0097', github: 'octocat' },
      ]).its('status').should('eq', 200);

      api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck.codecheckers').then((codecheckers) => {
        expect(codecheckers.find((entry) => entry.userId === RREVIEWER_ID))
          .to.deep.equal({ userId: RREVIEWER_ID, name: 'Rosa Reviewer', orcid: '', github: '' });
      });
    });
  });

  it('may write a submission they were never assigned to', () => {
    post(`status/update?submissionId=${NOT_ASSIGNED}`, {
      submissionId: NOT_ASSIGNED,
      status: ASSIGNED_CODECHECKER,
      userId: SOMEONE_ELSE,
    }).then((response) => {
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

/**
 * Who may know the authors of a submission, and how to reach them (#28).
 *
 * A codechecker reaches a submission as an OJS reviewer, and the form offers
 * them the contact author's address — but only where the review method allows
 * them to know who the authors are. OJS hides them from a reviewer only in
 * double-anonymous review, and rreviewer's assignment on submission 9 is that
 * (`review_method` 2, OJS's default), so rreviewer sees neither the authors nor
 * the contact, in the form or in the generated `codecheck.yml`. The other
 * review methods are pinned in `CodecheckSubmissionAccessUnitTest`.
 */
describe('Who may know the authors', () => {
  /** The contact author the dataset gives submission 9. */
  const CONTACT_EMAIL = 'zliu@mailinator.com';

  it('an editor sees the authors and the contact author\'s address', () => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/submissions`);

    api('GET', `metadata?submissionId=${ASSIGNED}`).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.submission.authorsWithheld).to.eq(false);
      expect(response.body.submission.authors).to.not.be.empty;
      expect(response.body.submission.contact.email).to.eq(CONTACT_EMAIL);
    });
  });

  it('the submission\'s own author sees the authors and the contact', () => {
    // dnuest is stage-assigned to submission 9 as an author.
    cy.ojsLogin('dnuest', 'dnuest');
    cy.visit(`/index.php/${JOURNAL}/submissions`);

    api('GET', `metadata?submissionId=${ASSIGNED}`).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.submission.authorsWithheld).to.eq(false);
      expect(response.body.submission.contact.email).to.eq(CONTACT_EMAIL);
    });
  });

  it('the submission\'s own author is offered no writing (#127)', () => {
    cy.ojsLogin('dnuest', 'dnuest');
    cy.visit(`/index.php/${JOURNAL}/submissions`);

    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.permissions').should('deep.equal', {
      write: false,
      editCodecheckers: false,
      manageIdentifier: false,
      addCertificateReference: false,
    });
    api('GET', `status?submissionId=${ASSIGNED}`).its('body.canUpdate').should('eq', false);
    api('GET', `orcid-status?submissionId=${ASSIGNED}`).its('body.depositScope').should('eq', 'none');
  });

  it('a reviewer on a double-anonymous assignment sees neither, in the form or in the codecheck.yml', () => {
    // The names come from an editor first, so the assertions below cannot pass
    // merely because the fixture's authors were renamed.
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
    api('GET', `metadata?submissionId=${ASSIGNED}`).then((response) => {
      const names = response.body.submission.authors.map((author) => author.name);
      expect(names, 'the dataset gives submission 9 authors').to.not.be.empty;

      cy.ojsLogin('rreviewer', 'rreviewer');
      cy.visit(`/index.php/${JOURNAL}/submissions`);

      api('GET', `metadata?submissionId=${ASSIGNED}`).then((reviewerView) => {
        expect(reviewerView.status).to.eq(200);
        expect(reviewerView.body.submission.authorsWithheld).to.eq(true);
        expect(reviewerView.body.submission.authors).to.deep.eq([]);
        expect(reviewerView.body.submission.contact).to.eq(null);
        expect(JSON.stringify(reviewerView.body)).to.not.contain(CONTACT_EMAIL);
      });

      api('GET', `yaml?submissionId=${ASSIGNED}`).then((yamlView) => {
        expect(yamlView.status).to.eq(200);
        names.forEach((name) => expect(yamlView.body.yaml).to.not.contain(name));
        expect(yamlView.body.yaml).to.not.match(/^\s*authors:/m);
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

  it('refuse a reviewer of the submission who is not its codechecker (#13)', () => {
    cy.ojsLogin('rreviewer', 'rreviewer');

    cy.request({ url: orcid('startAuth', `?submissionId=${ASSIGNED}`) })
      .then((response) => {
        expect(response.body).to.contain(REFUSED);
      });
  });

  it('let a codechecker start on the submission they are assigned to', () => {
    cy.ojsLogin('ccodechecker', 'ccodechecker');

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
  // The record a test below changes, to be put back whether or not it passed.
  let storedRecord = null;

  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.openCodecheckSettings();
    cy.get('#orcidEnabled', { timeout: 20000 }).check();
    cy.get('input[name="orcidClientId"]').clear().type('APP-CYPRESS');
    cy.get('input[name="orcidClientSecret"]').clear().type('cypress-secret');
    cy.saveCodecheckSettings();
  });

  after(() => {
    if (storedRecord) {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
      cy.saveCodecheckRecord(ASSIGNED, storedRecord, storedRecord.codecheckers)
        .its('status').should('eq', 200);
    }

    // The secret field is write-only — an empty value means "keep", so the
    // dummy secret stays until the dataset is reloaded. Switching ORCID off is
    // what actually restores the journal's behaviour for the other specs.
    cy.ojsLogin('admin', 'admin');
    cy.openCodecheckSettings();
    cy.get('#orcidEnabled', { timeout: 20000 }).uncheck();
    cy.get('input[name="orcidClientId"]').clear();
    cy.saveCodecheckSettings();
  });

  it('sends the codechecker back to the journal, not to the site', () => {
    cy.ojsLogin('ccodechecker', 'ccodechecker');

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

  /**
   * An ORCID account is connected only for a codechecker whose iD is on record
   * (GHSA-4p3r-qgp4-g74r), so a submission whose codecheckers have no iD — here
   * rreviewer linked instead of ccodechecker, an account without one — is
   * refused before anyone is sent to ORCID. Which account the callback accepts
   * is pinned in `OrcidDepositServiceUnitTest` and `CodecheckCodecheckersUnitTest`.
   */
  it('sends nobody to ORCID while no codechecker has an iD on record', () => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck').then((stored) => {
      storedRecord = stored;
      cy.saveCodecheckRecord(ASSIGNED, stored, [{ userId: RREVIEWER_ID }]).its('status').should('eq', 200);
      api('GET', `metadata?submissionId=${ASSIGNED}`).its('body.codecheck.codecheckers')
        .should('deep.equal', [{ userId: RREVIEWER_ID, name: 'Rosa Reviewer', orcid: '', github: '' }]);

      cy.request({
        url: orcid('startAuth', `?submissionId=${ASSIGNED}`),
        followRedirect: false,
      }).then((response) => {
        expect(response.status, 'not sent on to ORCID').to.eq(200);
        expect(response.body).to.contain('No codechecker of this submission has an ORCID iD on record');
      });

      // Put back at once, not only in after(): the tests below need
      // ccodechecker linked again.
      cy.saveCodecheckRecord(ASSIGNED, stored, stored.codecheckers).its('status').should('eq', 200);
      cy.then(() => {
        storedRecord = null;
      });
    });
  });

  /**
   * With OJS's own ORCID integration depositing reviews (Member API), OJS
   * deposits the codechecker's closed review itself, so the plugin sends
   * nobody to ORCID and deposits nothing (#13). OJS's settings are put back
   * whether or not the assertion held.
   */
  describe("while OJS's own ORCID integration deposits reviews", () => {
    const CONTEXT = 'api/v1/contexts/1';
    const OJS_ORCID = ['orcidEnabled', 'orcidApiType', 'orcidClientId', 'orcidClientSecret'];
    let before_ = null;

    const putContext = (values) => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
      cy.ojsApi('PUT', CONTEXT, values).its('status').should('eq', 200);
    };

    before(() => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
      cy.ojsApi('GET', CONTEXT).its('body').then((context) => {
        before_ = Object.fromEntries(OJS_ORCID.map((name) => [name, context[name] ?? null]));
      });
      putContext({ orcidEnabled: true, orcidApiType: 'memberSandbox', orcidClientId: 'APP-OJS-CYPRESS', orcidClientSecret: 'ojs-cypress-secret' });
    });
    after(() => {
      if (before_) {
        putContext({ ...before_, orcidEnabled: Boolean(before_.orcidEnabled) });
      }
    });

    it('sends nobody to ORCID, and says OJS deposits the review', () => {
      cy.ojsLogin('ccodechecker', 'ccodechecker');
      cy.request({ url: orcid('startAuth', `?submissionId=${ASSIGNED}`), followRedirect: false }).then((response) => {
        expect(response.status, 'not sent on to ORCID').to.eq(200);
        expect(response.body).to.contain("OJS's own ORCID integration");
      });
    });

    it('refuses a deposit', () => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
      api('POST', `orcid-deposit?submissionId=${ASSIGNED}`, {}).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.error).to.contain("OJS's own ORCID integration");
      });
    });
  });
});
