/**
 * Reading what OJS sent, from Mailpit. Only the specs under cypress/tests/mail/
 * import this; see CLAUDE.md, "Mail tests". Mailpit's address comes from
 * CYPRESS_MAILPIT_URL.
 */

const mailpit = (path) => `${Cypress.env('MAILPIT_URL') || 'http://localhost:8025'}/api/v1/${path}`;

/** Delete every message, so a spec only sees what it caused. */
Cypress.Commands.add('clearMail', () => {
  cy.request('DELETE', mailpit('messages')).its('status').should('eq', 200);
});

/**
 * The messages to an address whose subject is the given one, or matches a
 * RegExp, with their text and HTML. An email is sent within the request that
 * caused it, so no waiting is needed.
 */
Cypress.Commands.add('mailTo', (address, subject) => {
  const exact = typeof subject === 'string';
  const matches = exact ? (s) => s === subject : (s) => subject.test(s);
  // Mailpit's search matches words, so it only narrows; the matcher decides.
  const query = encodeURIComponent(`to:"${address}"` + (exact ? ` subject:"${subject}"` : ''));
  return cy.request(mailpit(`search?query=${query}&limit=500`)).then((response) => {
    const messages = [];
    response.body.messages
      .filter((summary) => matches(summary.Subject))
      .forEach((summary) => cy.request(mailpit(`message/${summary.ID}`)).then((message) => messages.push(message.body)));
    return cy.wrap(messages);
  });
});
