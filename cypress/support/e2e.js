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
 */
Cypress.Commands.add('ojsApi', (method, path, body) => {
  return cy.getCsrfToken().then((csrfToken) => {
    expect(csrfToken, 'a CSRF token, from a backend page').to.exist;
    return cy.request({
      method,
      url: `/index.php/${JOURNAL}/${path}`,
      headers: { 'X-Csrf-Token': csrfToken },
      body,
      failOnStatusCode: false,
    });
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
