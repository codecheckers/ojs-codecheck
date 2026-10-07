// Ignore all uncaught JS exceptions from OJS in CI
Cypress.on('uncaught:exception', () => {
  return false;
});

// Global before hook
before(() => {
  cy.clearCookies();
  cy.clearLocalStorage();
});

const JOURNAL = 'codecheck';

// Custom command for OJS login
Cypress.Commands.add('ojsLogin', (username = 'admin', password = 'admin') => {
  cy.session([username, password], () => {
    cy.visit(`/index.php/${JOURNAL}/login`);
    cy.get('input[name="username"]').type(username);
    cy.get('input[name="password"]').type(password);
    cy.get('button[type="submit"]').click();
    cy.url().should('not.include', '/login');
  });
});

// Custom command to get CSRF token
Cypress.Commands.add('getCsrfToken', () => {
  return cy.window().then((win) => {
    return win.pkp?.currentUser?.csrfToken;
  });
});

/**
 * Call the journal's API with the session's CSRF token. Yields the response
 * whatever its status, so the caller asserts on it. Needs a backend page open:
 * the token is read off that page's pkp object.
 *
 * @param {string} method HTTP method
 * @param {string} path   relative to the journal, e.g. 'api/v1/submissions/8'
 * @param {object} body   request body, if any
 * @param {object} options further `cy.request()` options, e.g. a longer `timeout`
 */
Cypress.Commands.add('ojsApi', (method, path, body, options = {}) => {
  return cy.getCsrfToken().then((csrfToken) => {
    expect(csrfToken, 'a CSRF token, from a backend page').to.exist;
    return cy.request({
      method,
      url: `/index.php/${JOURNAL}/${path}`,
      headers: { 'X-Csrf-Token': csrfToken },
      body,
      failOnStatusCode: false,
      ...options,
    });
  });
});

/**
 * Save a submission's CODECHECK record as the editorial form does, from the
 * record `GET metadata` answered, with its codechecker list replaced. Yields the
 * response whatever its status. Needs a backend page open, as cy.ojsApi() does.
 *
 * `GET metadata` answers `publicationType` and `additionalContent` while the
 * save takes snake case, so a payload copied key for key from the response
 * reset both fields — which is why this is written once.
 *
 * @param {number} submissionId
 * @param {object} stored       the `codecheck` object from `GET metadata`
 * @param {Array}  codecheckers the list to save
 */
Cypress.Commands.add('saveCodecheckRecord', (submissionId, stored, codecheckers) => {
  return cy.ojsApi('POST', `api/v1/codecheck/metadata?submissionId=${submissionId}`, {
    version: stored.version,
    publication_type: stored.publicationType,
    manifest: stored.manifest,
    repository: stored.repository,
    source: stored.source,
    codecheckers,
    certificate: stored.certificate,
    issue: stored.issue,
    check_time: stored.check_time,
    summary: stored.summary,
    report: stored.report,
    additional_content: stored.additionalContent,
  });
});

/**
 * Log in as admin and open a backend page, for the CSRF token cy.ojsApi()
 * reads. The dashboard reads neither CODECHECK list.
 */
Cypress.Commands.add('openBackend', () => {
  cy.ojsLogin('admin', 'admin');
  cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
});

/**
 * A review assignment as the dataset leaves it, open, to put back with
 * cy.reopenReview() once a spec has closed it: OJS cannot reopen a review.
 * Through cypress/plugins/reviewAssignmentTasks.js, which needs CYPRESS_DB_*.
 */
Cypress.Commands.add('snapshotOpenReview', (reviewAssignmentId) => {
  return cy.task('snapshotReviewAssignment', reviewAssignmentId).then((taken) => {
    expect(taken.dateCompleted, 'the dataset leaves the review open').to.eq(null);
    return taken;
  });
});

Cypress.Commands.add('reopenReview', (snapshot) => {
  return cy.task('restoreReviewAssignment', snapshot);
});

/**
 * Record a CODECHECK status for a submission, as admin, and expect it saved.
 * Needs a backend page open, as cy.ojsApi() does.
 */
Cypress.Commands.add('recordCodecheckStatus', (submissionId, status) => {
  return cy.ojsApi('POST', `api/v1/codecheck/status/update?submissionId=${submissionId}`, { status, userId: 1 })
    .its('status').should('eq', 200);
});

/**
 * OJS's participant grid on a submission's stage. A legacy grid: it takes the
 * CSRF token as a form field and answers HTML, so a change to PKP's grid is a
 * fix here only.
 */
const participantGrid = (submissionId, stageId, op) =>
  `/index.php/${JOURNAL}/$$$call$$$/grid/users/stage-participant/stage-participant-grid/${op}` +
  `?submissionId=${submissionId}&stageId=${stageId}`;

const participantGridPost = (submissionId, stageId, op, form) =>
  cy.getCsrfToken().then((csrfToken) =>
    cy.request({ method: 'POST', url: participantGrid(submissionId, stageId, op), form: true, body: { csrfToken, ...form } })
  );

/**
 * Assign a user to a submission in a user group, as "Assign" in the
 * participants panel does, without a message: OJS sends no email of its own.
 */
Cypress.Commands.add('assignParticipant', (submissionId, stageId, userGroupId, userId) => {
  return participantGridPost(submissionId, stageId, 'save-participant', { userGroupId, userId, assignmentId: '' })
    .its('body.status').should('eq', true);
});

/** Remove every assignment of the submission in the given user groups. */
Cypress.Commands.add('removeParticipants', (submissionId, stageId, userGroupIds) => {
  return cy.request(participantGrid(submissionId, stageId, 'fetch-grid')).then((response) => {
    // Rows are "…-category-<user group>-row-<assignment>"; the group's own
    // heading row is a .category row.
    const pattern = new RegExp(`-category-(?:${userGroupIds.join('|')})-row-(\\d+)$`);
    const rows = new DOMParser().parseFromString(response.body.content, 'text/html')
      .querySelectorAll('tr.gridRow:not(.category)');
    [...rows].map((row) => row.id.match(pattern)?.[1]).filter(Boolean)
      .forEach((assignmentId) =>
        participantGridPost(submissionId, stageId, 'delete-participant', { assignmentId })
          .its('body.status').should('eq', true)
      );
  });
});

/**
 * Yield the id of a published submission, or null when there is none. Needs a
 * backend page open, as cy.ojsApi() does.
 */
Cypress.Commands.add('publishedArticleId', () => {
  return cy.ojsApi('GET', 'api/v1/submissions?status[]=3&count=1').then((response) => {
    // A refused or failed request is not "nothing published".
    expect(response.status, 'the submissions API').to.eq(200);
    return response.body.items?.[0]?.id ?? null;
  });
});

/** Yield the CODECHECK settings form, once it is open. */
Cypress.Commands.add('codecheckSettingsForm', (options) => {
  return cy.get('form#codecheckSettings', options);
});

/**
 * Open the CODECHECK plugin settings form — Smarty, in a modal on the
 * journal's website settings. Every spec driving the form comes through here,
 * so a change to PKP's plugin grid is one fix.
 */
Cypress.Commands.add('openCodecheckSettings', () => {
  cy.visit(`/index.php/${JOURNAL}/management/settings/website`);

  // Every settings tab is rendered into the DOM up front, so the plugin grid is
  // present without switching tabs; the row's action links are collapsed, hence
  // the forced click.
  cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
    .first()
    .click({ force: true });

  cy.codecheckSettingsForm({ timeout: 20000 }).should('exist');
});

/** Save the open settings form. The modal closes once it has been saved. */
Cypress.Commands.add('saveCodecheckSettings', () => {
  cy.codecheckSettingsForm().find('button[type="submit"]').first().click();
  cy.codecheckSettingsForm({ timeout: 20000 }).should('not.exist');
});

/**
 * Set fields on the CODECHECK settings form, in order, and save. Each key is a
 * selector within the form; `true` checks a radio button or checkbox, `false`
 * unchecks a checkbox, anything else is the field's value.
 *
 * Driving the form rather than writing settings directly is deliberate: the
 * wiring between form field, stored setting and the code that reads it is the
 * part that breaks. Values are set rather than typed: a colour input rejects
 * typing, and a field may be hidden — the badge text unless the badge is the
 * text-only one, a multilingual field's other languages until it has focus.
 *
 * @param {object} fields selector => true | false | value
 */
Cypress.Commands.add('setCodecheckFields', (fields) => {
  cy.openCodecheckSettings();

  Object.entries(fields).forEach(([selector, value]) => {
    const field = () => cy.codecheckSettingsForm().find(selector);
    if (typeof value === 'boolean') {
      field().scrollIntoView();
      field()[value ? 'check' : 'uncheck']({ force: true });
      field().should(value ? 'be.checked' : 'not.be.checked');
    } else {
      field().invoke('val', value);
      field().trigger('change', { force: true });
    }
  });

  cy.saveCodecheckSettings();
});

/**
 * Set one CODECHECK checkbox setting through the settings form.
 *
 * @param {string} fieldId  the checkbox id, e.g. 'showArticleSidebar'
 * @param {boolean} enabled desired state
 */
Cypress.Commands.add('setCodecheckSetting', (fieldId, enabled) => {
  cy.setCodecheckFields({ [`#${fieldId}`]: enabled });
});

/**
 * Read a CODECHECK checkbox setting off the settings form, which is left open.
 *
 * For a spec that has to put a setting back the way it found it rather than
 * assume a default — `orcidEnabled` is on or off depending on whether the
 * developer has credentials in `.env`.
 *
 * @param {string} fieldId the checkbox id, e.g. 'orcidEnabled'
 * @returns {Cypress.Chainable<boolean>} whether it is currently ticked
 */
Cypress.Commands.add('getCodecheckSetting', (fieldId) => {
  cy.openCodecheckSettings();
  return cy.codecheckSettingsForm().find(`#${fieldId}`).then(($checkbox) => $checkbox.is(':checked'));
});
