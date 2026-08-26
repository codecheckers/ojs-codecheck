/**
 * The CODECHECK gate on publishing, through the endpoint OJS actually publishes
 * with.
 *
 * `Publication::validatePublish` collects errors from OJS and from every plugin;
 * anything in that array stops the publication. The plugin adds an error when
 * the submission's CODECHECK status is not one the journal accepts, so a paper
 * cannot go out while its check is unfinished.
 *
 * This ran silently broken until it was covered: the validator asked the
 * router's handler for the authorized submission, and under the API router —
 * which is how publishing happens — there is no handler. Every publish threw
 * inside the hook, PKP logged "failed to handle the hook" and carried on, and
 * the gate never closed. The unit tests hold the no-handler case; this holds the
 * behaviour a journal actually depends on.
 *
 * Most of it cannot publish anything: submission 8 is in review with no issue, so
 * OJS's own validation refuses it whatever CODECHECK says, and those tests assert
 * on which errors come back. The last test is the other half — a submission OJS
 * is willing to publish — and it really does publish, then puts it back.
 */

const JOURNAL = 'codecheck';
const SUBMISSION = 8;

// Already published, in the production stage and assigned to an issue, so OJS
// raises nothing of its own: the only thing that can block it is CODECHECK.
const PUBLISHABLE_SUBMISSION = 5;

const OJS_STATUS_QUEUED = 1;
const OJS_STATUS_PUBLISHED = 3;

const ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';
const FULL_REPRODUCTION = 'plugins.generic.codecheck.status.completed.fullReproduction';

/** The distinctive part of the plugin's status message. */
const STATUS_REFUSAL = 'not allowed for publication by the journal';

function api(method, path, body) {
  return cy.getCsrfToken().then((csrfToken) =>
    cy.request({
      method,
      url: `/index.php/${JOURNAL}/${path}`,
      headers: { 'X-Csrf-Token': csrfToken },
      body,
      failOnStatusCode: false,
    })
  );
}

const setStatusOf = (submissionId, status) =>
  api('POST', `api/v1/codecheck/status/update?submissionId=${submissionId}`, { status, userId: 1 });

const setStatus = (status) => setStatusOf(SUBMISSION, status);

const publish = (submissionId, publicationId) =>
  api('PUT', `api/v1/submissions/${submissionId}/publications/${publicationId}/publish`);

const unpublish = (submissionId, publicationId) =>
  api('PUT', `api/v1/submissions/${submissionId}/publications/${publicationId}/unpublish`);

/**
 * Publishes the submission if it is not published already.
 *
 * The test must not assume the state it finds: a run that failed part-way, or a
 * hand-run of a single test, can leave the article unpublished.
 */
function ensurePublished(submissionId) {
  allowOnlyStatuses([FULL_REPRODUCTION]);
  setStatusOf(submissionId, FULL_REPRODUCTION);

  return api('GET', `api/v1/submissions/${submissionId}`).then((submission) => {
    const publicationId = submission.body.currentPublicationId;

    if (submission.body.status === OJS_STATUS_PUBLISHED) {
      return publicationId;
    }

    return publish(submissionId, publicationId).then((response) => {
      expect(response.status, 'the article has to start out published').to.eq(200);
      return publicationId;
    });
  });
}

/** Attempts to publish and returns the collected validation errors. */
function attemptPublish() {
  return api('GET', `api/v1/submissions/${SUBMISSION}`).then((submission) =>
    api(
      'PUT',
      `api/v1/submissions/${SUBMISSION}/publications/${submission.body.currentPublicationId}/publish`
    ).then((response) => {
      expect(response.status, 'the submission is never actually published').to.eq(400);
      return Object.values(response.body).join('\n');
    })
  );
}

/** Ticks exactly the given CODECHECK statuses in the publication settings. */
function allowOnlyStatuses(statusKeys) {
  cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
  cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
    .first()
    .click({ force: true });
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('exist');

  // The ids carry the locale key, dots and all, so they are matched by
  // attribute rather than by a CSS id selector.
  cy.get('[name="codecheckStatusKeysSelected[]"]').each(($box) => {
    const wanted = statusKeys.includes($box.attr('value'));
    if ($box.is(':checked') !== wanted) {
      cy.wrap($box).click({ force: true });
    }
  });

  cy.get('form#codecheckSettings').find('button[type="submit"]').first().click();
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('not.exist');
}

describe('CODECHECK publication validation', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    // cy.getCsrfToken() reads the token off the page's pkp object.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    // Put the published article back even if a test above failed part-way, or
    // every spec that reads a published article breaks after this one.
    ensurePublished(PUBLISHABLE_SUBMISSION);

    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', true);
    // The dataset ships with no status accepted for publication.
    allowOnlyStatuses([]);
    setStatus(ASSIGNED_CODECHECKER);
  });

  it('refuses a submission whose status the journal does not accept', () => {
    allowOnlyStatuses([FULL_REPRODUCTION]);
    setStatus(ASSIGNED_CODECHECKER);

    attemptPublish().then((errors) => {
      expect(errors).to.contain(STATUS_REFUSAL);
      // The message names the status, so an editor can tell what to fix.
      expect(errors).to.contain('codechecker assigned');
    });
  });

  it('names the status that is in force, not a fixed one', () => {
    allowOnlyStatuses([ASSIGNED_CODECHECKER]);
    setStatus(FULL_REPRODUCTION);

    // Accepting "assigned" while the check has finished is an odd
    // configuration, but it shows the message follows the record.
    attemptPublish().then((errors) => {
      expect(errors).to.contain(STATUS_REFUSAL);
      expect(errors).to.contain('full reproduction');
    });
  });

  it('lets a submission through once its status is accepted', () => {
    allowOnlyStatuses([FULL_REPRODUCTION]);
    setStatus(FULL_REPRODUCTION);

    attemptPublish().then((errors) => {
      expect(errors).to.not.contain(STATUS_REFUSAL);
      // OJS still refuses for its own reasons, which is what keeps this test
      // from publishing the submission.
      expect(errors).to.contain('must be assigned to an issue');
    });
  });

  it('publishes a submission OJS is ready to publish once CODECHECK accepts it', () => {
    // The other tests can only show the gate closing. This one shows it opening:
    // a submission in the production stage, assigned to an issue, with nothing
    // for OJS to object to. It is unpublished first and published again at the
    // end, so the journal is left as it was found.
    //
    // The register deposit fires on Publication::publish and would reach for
    // GitHub, so it is switched off for the duration — this test is about
    // validation, not deposit.
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', false);

    ensurePublished(PUBLISHABLE_SUBMISSION).then((publicationId) => {
      unpublish(PUBLISHABLE_SUBMISSION, publicationId).then((response) => {
        expect(response.status, 'unpublishing is the setup, not the test').to.eq(200);
        expect(response.body.status).to.eq(OJS_STATUS_QUEUED);
      });

      // A status the journal does not accept blocks it even though OJS is happy,
      // so what follows cannot be OJS letting it through regardless.
      setStatusOf(PUBLISHABLE_SUBMISSION, ASSIGNED_CODECHECKER);
      publish(PUBLISHABLE_SUBMISSION, publicationId).then((response) => {
        expect(response.status).to.eq(400);
        expect(Object.values(response.body).join('\n')).to.contain(STATUS_REFUSAL);
      });

      setStatusOf(PUBLISHABLE_SUBMISSION, FULL_REPRODUCTION);
      publish(PUBLISHABLE_SUBMISSION, publicationId).then((response) => {
        expect(response.status, 'nothing is left blocking it').to.eq(200);
        expect(response.body.status).to.eq(OJS_STATUS_PUBLISHED);
      });

      // And the article is public again, which is how the suite found it.
      cy.visit(`/index.php/${JOURNAL}/article/view/${PUBLISHABLE_SUBMISSION}`);
      cy.get('[data-testid="codecheck-article-sidebar"]').should('exist');
    });

    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', true);
  });

  it('does not stop OJS from reporting its own reasons', () => {
    // The hook returns false so the rest of OJS and other plugins still run;
    // a CODECHECK error must never be the only thing an editor is told.
    allowOnlyStatuses([]);
    setStatus(ASSIGNED_CODECHECKER);

    attemptPublish().then((errors) => {
      expect(errors).to.contain(STATUS_REFUSAL);
      expect(errors).to.contain('must be assigned to an issue');
      expect(errors).to.contain('Copyediting or Production');
    });
  });
});
