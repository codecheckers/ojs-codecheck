import '../../support/pkp-mock.js';
import CodecheckStatusForm from '../../../resources/js/Components/CodecheckStatusForm.vue';

/**
 * Whether the form offers recording a status is the server's answer to `GET
 * status` (#127); the form asks nothing else, and `status/update` refuses the
 * rest either way.
 */
const CHANGE = 'plugins.generic.codecheck.status.buttons.change';

const mountForm = ({ canUpdate }) => {
  cy.intercept('GET', '**/codecheck/status?*', {
    statusCode: 200,
    body: {
      success: true,
      statusRecord: { status: 'plugins.generic.codecheck.status.pending' },
      allStatuses: ['plugins.generic.codecheck.status.pending'],
      ...(canUpdate === undefined ? {} : { canUpdate }),
    },
  }).as('status');
  cy.intercept('GET', '**/codecheck/status/history*', {
    statusCode: 200,
    body: { success: true, statusHistory: [{ user_id: 1, status: 'plugins.generic.codecheck.status.pending', timestamp: '2026-01-01' }] },
  }).as('history');

  cy.mount(CodecheckStatusForm, { props: { submission: { id: 1, codecheckOptIn: true } } });
  cy.wait(['@status', '@history']);
  // Rendered once the status has loaded, so what is asserted next is not met
  // before the form is.
  cy.get('.codecheck-info').should('exist');
};

describe('CodecheckStatusForm', () => {
  it('offers the change to someone the server allows', () => {
    mountForm({ canUpdate: true });
    cy.contains('button', CHANGE).should('exist');
  });

  it('offers no change to someone the server does not allow', () => {
    mountForm({ canUpdate: false });
    cy.contains('button', 'plugins.generic.codecheck.status.buttons.history').should('exist');
    cy.contains('button', CHANGE).should('not.exist');
  });

  it('offers no change when the server does not say', () => {
    mountForm({});
    cy.contains('button', CHANGE).should('not.exist');
  });
});
