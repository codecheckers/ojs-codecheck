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
 * POST to one of OJS's legacy grids (`$$$call$$$/grid/…`): they take the CSRF
 * token as a form field, not a header, and answer HTML. A change to PKP's
 * grids is then a fix in the grid helpers below only.
 *
 * @param {string} grid the grid's path, e.g. 'users/reviewer/reviewer-grid'
 * @param {string} op   the operation, e.g. 'update-reviewer'
 * @param {object} qs   the query, which identifies the submission and stage
 * @param {object} form the fields posted
 */
const gridUrl = (grid, op) => `/index.php/${JOURNAL}/$$$call$$$/grid/${grid}/${op}`;
const gridPost = (grid, op, qs, form) =>
  cy.getCsrfToken().then((csrfToken) =>
    cy.request({ method: 'POST', url: gridUrl(grid, op), qs, form: true, body: { csrfToken, ...form } })
  );

const PARTICIPANT_GRID = 'users/stage-participant/stage-participant-grid';
const REVIEWER_GRID = 'users/reviewer/reviewer-grid';

/**
 * Assign a user to a submission in a user group, as "Assign" in the
 * participants panel does, without a message: OJS sends no email of its own.
 */
Cypress.Commands.add('assignParticipant', (submissionId, stageId, userGroupId, userId) => {
  return gridPost(PARTICIPANT_GRID, 'save-participant', { submissionId, stageId }, { userGroupId, userId, assignmentId: '' })
    .its('body.status').should('eq', true);
});

/** Yields the ids of the submission's stage assignments in the given user groups. */
Cypress.Commands.add('participantAssignments', (submissionId, stageId, userGroupIds) => {
  // An empty list would match every group, and removeParticipants() remove all.
  expect(userGroupIds, 'the user groups to look in').not.to.be.empty;
  return cy.request({ url: gridUrl(PARTICIPANT_GRID, 'fetch-grid'), qs: { submissionId, stageId } }).then((response) => {
    // Rows are "…-category-<user group>-row-<assignment>"; the group's own
    // heading row is a .category row.
    const pattern = new RegExp(`-category-(?:${userGroupIds.join('|')})-row-(\\d+)$`);
    const rows = new DOMParser().parseFromString(response.body.content, 'text/html')
      .querySelectorAll('tr.gridRow:not(.category)');
    return [...rows].map((row) => row.id.match(pattern)?.[1]).filter(Boolean);
  });
});

/** Remove every assignment of the submission in the given user groups. */
Cypress.Commands.add('removeParticipants', (submissionId, stageId, userGroupIds) => {
  return cy.participantAssignments(submissionId, stageId, userGroupIds).each((assignmentId) =>
    gridPost(PARTICIPANT_GRID, 'delete-participant', { submissionId, stageId }, { assignmentId })
      .its('body.status').should('eq', true)
  );
});

/** `PKPReviewerGridHandler::REVIEWER_SELECT_ADVANCED_SEARCH`, the "Add Reviewer" search tab. */
const REVIEWER_SELECT_ADVANCED_SEARCH = 1;
/** `ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN`: codecheckers are never anonymous. */
const REVIEW_METHOD_OPEN = 3;

/** A date some days ahead, as the browser's local date (YYYY-MM-DD). */
const dateInDays = (days) => {
  const date = new Date(Date.now() + days * 86400000);
  return [date.getFullYear(), date.getMonth() + 1, date.getDate()].map((n) => String(n).padStart(2, '0')).join('-');
};

/**
 * Invite a reviewer as "Add Reviewer" does: the message is the template's
 * body as the form loads it (`fetchTemplateBody`), the subject the template's.
 *
 * @param {object} invitation {submissionId, stageId, reviewRoundId, reviewerId, template,
 *   reviewMethod} — the method defaults to open review, as for a codechecker
 */
Cypress.Commands.add('inviteReviewer', ({ submissionId, stageId, reviewRoundId, reviewerId, template, reviewMethod = REVIEW_METHOD_OPEN }) => {
  const qs = { submissionId, stageId, reviewRoundId };
  return cy.request({ url: gridUrl(REVIEWER_GRID, 'fetch-template-body'), qs: { ...qs, template } }).then((response) => {
    expect(response.body.status, `the template ${template}`).to.eq(true);
    return gridPost(REVIEWER_GRID, 'update-reviewer', qs, {
      selectionType: REVIEWER_SELECT_ADVANCED_SEARCH, reviewerId, template, reviewMethod,
      personalMessage: response.body.content,
      responseDueDate: dateInDays(7), reviewDueDate: dateInDays(28),
    }).its('body.status').should('eq', true);
  });
});

/**
 * Unassign a reviewer without an email. A review not yet confirmed is deleted,
 * so an invitation made by a spec leaves nothing behind but the event log.
 */
Cypress.Commands.add('unassignReviewer', ({ submissionId, stageId, reviewRoundId, reviewAssignmentId }) => {
  return gridPost(REVIEWER_GRID, 'update-unassign-reviewer', { submissionId, stageId, reviewRoundId, reviewAssignmentId }, { skipEmail: 1 })
    .its('body.status').should('eq', true);
});

/**
 * The journal's alternates to "Review Request" named as the plugin names its
 * "Invitation to codecheck" template (#13): one, unless a spec deleted it.
 * Needs a backend page open, as cy.ojsApi() does.
 */
Cypress.Commands.add('invitationTemplates', () => {
  return cy.ojsApi('GET', 'api/v1/emailTemplates?alternateTo=REVIEW_REQUEST').then((response) => {
    expect(response.status, 'the email templates API').to.eq(200);
    return response.body.items.filter((template) =>
      Object.values(template.name ?? {}).includes('Invitation to codecheck'));
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
