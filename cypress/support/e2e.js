// Ignore all uncaught JS exceptions from OJS in CI
Cypress.on('uncaught:exception', () => {
  return false;
});

// Global before hook
before(() => {
  cy.clearCookies();
  cy.clearLocalStorage();
});

// Custom command for OJS login
Cypress.Commands.add('ojsLogin', (username = 'admin', password = 'admin') => {
  cy.session([username, password], () => {
    cy.visit('/index.php/codecheck/login');
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
 * Set a CODECHECK plugin checkbox setting through the real settings form.
 *
 * Driving the form rather than writing the setting directly is deliberate: the
 * wiring between form field, stored setting and the code that reads it is the
 * part that breaks.
 *
 * @param {string} fieldId  the checkbox id, e.g. 'showArticleSidebar'
 * @param {boolean} enabled desired state
 * @param {string} journal  journal path, defaults to 'codecheck'
 */
Cypress.Commands.add('setCodecheckSetting', (fieldId, enabled, journal = 'codecheck') => {
  cy.visit(`/index.php/${journal}/management/settings/website`);

  // Every settings tab is rendered into the DOM up front, so the plugin grid is
  // present without switching tabs; the row's action links are collapsed, hence
  // the forced click.
  cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
    .first()
    .click({ force: true });

  cy.get(`#${fieldId}`, { timeout: 20000 }).then(($checkbox) => {
    if ($checkbox.is(':checked') !== enabled) {
      cy.wrap($checkbox).click();
    }
  });

  cy.get(`#${fieldId}`).should(enabled ? 'be.checked' : 'not.be.checked');

  cy.get('form#codecheckSettings').find('button[type="submit"]').first().click();

  // The modal closes once the form has been saved.
  cy.get(`#${fieldId}`, { timeout: 20000 }).should('not.exist');
});