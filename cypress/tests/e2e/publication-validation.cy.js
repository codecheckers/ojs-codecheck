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

/** The distinctive part of the hidden-repository message (issue #169). */
const PRIVATE_REPOSITORY_REFUSAL = 'name that repository in the public CODECHECK Register';

/**
 * Submission 8's repository list as the test found it, so the `after()` hook can
 * put it back even when an assertion aborts the test that changed it.
 */
let originalRepositories = null;
let originalMetadata = null;

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

/** The stored CODECHECK metadata of a submission, as the workflow form reads it. */
const getMetadata = (submissionId) =>
  api('GET', `api/v1/codecheck/metadata?submissionId=${submissionId}`).then((response) => {
    expect(response.status, 'the metadata has to be readable').to.eq(200);
    return response.body.codecheck;
  });

/**
 * Rewrites the repository list of a submission, leaving the rest of the record
 * as it was found.
 *
 * `POST metadata` replaces the whole row, so every other field has to be sent
 * back with it — and the read and write shapes do not use the same key names
 * for two of them, which is why they are mapped one by one rather than spread.
 */
function setRepositories(submissionId, codecheck, repositories) {
  return api('POST', `api/v1/codecheck/metadata?submissionId=${submissionId}`, {
    version: codecheck.version,
    publication_type: codecheck.publicationType,
    manifest: codecheck.manifest,
    repository: { repositories },
    source: codecheck.source,
    codecheckers: codecheck.codecheckers,
    certificate: codecheck.certificate,
    issue: codecheck.issue,
    check_time: codecheck.check_time,
    summary: codecheck.summary,
    report: codecheck.report,
    additional_content: codecheck.additionalContent,
  }).then((response) => {
    expect(response.status, 'the repository list has to be writable').to.eq(200);
  });
}

/**
 * The repository list with entry 0 marked as holding the codecheck.yml, hidden
 * or not, and every other entry unmarked.
 */
const markedFirst = (entries, hidden) =>
  entries.map((entry, index) =>
    index === 0
      ? {...entry, hidden, containsCodecheckYaml: true}
      : {...entry, containsCodecheckYaml: false}
  );

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

    // Submission 8 is shared with other specs, and the repository test cannot
    // restore it itself: a failing assertion aborts the chain before any
    // command queued after it. Restoring here is also idempotent, which
    // restoring inside the test is not — a second run would otherwise read the
    // mutated blob back and "restore" that.
    if (originalRepositories) {
      setRepositories(SUBMISSION, originalMetadata, originalRepositories);
    }

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

  it('refuses to publish while the repository holding the codecheck.yml is private', () => {
    // Issue #169. Publishing writes that repository into the `Repository`
    // column of the register.csv row, which is committed to a public pull
    // request — so a repository the editor marked "Keep private" would be
    // published by the act of publishing the article, having been withheld
    // from the article page, the issue TOC, the codecheck.yml and the
    // register issue. The editor is told before publication rather than
    // finding out from the public register.
    allowOnlyStatuses([FULL_REPRODUCTION]);
    setStatus(FULL_REPRODUCTION);
    // The gate only exists where the disclosure does (#177).
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', true);

    getMetadata(SUBMISSION).then((codecheck) => {
      const original = codecheck.repository.repositories;
      expect(original, 'the submission has a repository to mark').to.have.length.of.at.least(1);

      // Hand the record to after(), which restores it whatever happens here.
      originalMetadata = codecheck;
      originalRepositories = original;

      setRepositories(SUBMISSION, codecheck, markedFirst(original, true));
      attemptPublish().then((errors) => {
        expect(errors).to.contain(PRIVATE_REPOSITORY_REFUSAL);
      });

      // The same record with that repository public passes the check, so the
      // refusal above is about the hidden mark and nothing else.
      setRepositories(SUBMISSION, codecheck, markedFirst(original, false));
      attemptPublish().then((errors) => {
        expect(errors).to.not.contain(PRIVATE_REPOSITORY_REFUSAL);
        expect(errors).to.contain('must be assigned to an issue');
      });
    });
  });

  /**
   * Issue #177. The gate exists because publishing names the repository in
   * the public register. A journal that does not deposit publishes it
   * nowhere, so the same record must go through — otherwise a journal
   * codechecking embargoed material cannot publish at all, and the message
   * names a consequence that cannot occur.
   */
  it('does not refuse a hidden repository when the journal does not deposit', () => {
    allowOnlyStatuses([FULL_REPRODUCTION]);
    setStatus(FULL_REPRODUCTION);
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', false);

    getMetadata(SUBMISSION).then((codecheck) => {
      const original = codecheck.repository.repositories;
      expect(original, 'the submission has a repository to mark').to.have.length.of.at.least(1);

      // Never overwrite a snapshot an earlier test took: this list has already
      // been changed by the test above, so recording it here would make
      // after() "restore" that change and the fixture would drift for good.
      originalMetadata ??= codecheck;
      originalRepositories ??= original;

      setRepositories(SUBMISSION, codecheck, markedFirst(original, true));

      attemptPublish().then((errors) => {
        expect(errors).to.not.contain(PRIVATE_REPOSITORY_REFUSAL);
        // Still refused, by OJS, for its own reasons — so the absence above
        // is the gate standing down rather than the request not happening.
        expect(errors).to.contain('must be assigned to an issue');
      });

      // And the plugin is demonstrably still running: OJS's own errors would
      // be there even if the hook had thrown and contributed nothing, which is
      // the failure this spec exists to catch. A status the journal does not
      // accept has to come back from CODECHECK in the same configuration.
      setStatus(ASSIGNED_CODECHECKER);
      attemptPublish().then((errors) => {
        expect(errors).to.contain(STATUS_REFUSAL);
        expect(errors).to.not.contain(PRIVATE_REPOSITORY_REFUSAL);
      });
    });
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
