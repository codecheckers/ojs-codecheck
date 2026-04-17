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