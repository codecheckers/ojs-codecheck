import '../../support/pkp-mock.js';
import CodecheckReviewDisplay from '../../../resources/js/Components/CodecheckReviewDisplay.vue';

const NEEDS_CODECHECKER = 'plugins.generic.codecheck.status.needsCodechecker';

const serve = ({ status = NEEDS_CODECHECKER, codecheck = null } = {}) => {
  cy.intercept('GET', '**/codecheck/status*', { body: { statusRecord: { status } } }).as('getStatus');
  cy.intercept('GET', '**/codecheck/metadata*', { body: { success: true, codecheck } }).as('getMetadata');
};

const mount = (submission = { id: 1, codecheckOptIn: true }) =>
  cy.mount(CodecheckReviewDisplay, { props: { submission } });

/**
 * The review stage's CODECHECK panel shows the recorded status and the record
 * from the endpoints the CODECHECK tab reads (#65). It used to read a
 * `codecheckMetadata` field no submission carries, so only the status ever
 * appeared.
 */
describe('CodecheckReviewDisplay Component', () => {
  it('shows not opted in message when codecheckOptIn is false', () => {
    serve();
    mount({ id: 1, codecheckOptIn: false });

    cy.contains('plugins.generic.codecheck.warning.notOptedIn').should('exist');
    cy.get('.codecheck-info').should('not.exist');
  });

  it('shows the recorded status', () => {
    serve();
    mount();

    cy.wait('@getStatus');
    cy.contains(NEEDS_CODECHECKER).should('exist');
  });

  it('shows the record as the CODECHECK tab stores it', () => {
    serve({
      codecheck: {
        version: '2.0',
        publicationType: 'doi',
        certificate: '2024-001',
        check_time: '2024-01-15T10:00:00Z',
        manifest: [
          { file: 'figure1.png', comment: 'Main visualization' },
          { file: 'script.R', comment: '' },
        ],
        codecheckers: [{ name: 'John Doe', orcid: '0000-0001-2345-6789' }],
        repository: {
          repositories: [
            { url: 'https://github.com/test/repo', hidden: false },
            { url: 'javascript:alert(1)', hidden: false },
          ],
        },
        summary: 'Code executed successfully',
        report: 'https://doi.org/10.5281/zenodo.1',
      },
    });
    mount();

    cy.wait('@getMetadata');
    cy.contains('2024-001').should('exist');
    cy.contains('John Doe').should('exist');
    cy.contains('0000-0001-2345-6789').should('exist');
    cy.contains('figure1.png').should('exist');
    cy.contains('Main visualization').should('exist');
    cy.contains('Code executed successfully').should('exist');
    cy.get('a[href="https://github.com/test/repo"]').should('exist');
    cy.get('a[href="https://doi.org/10.5281/zenodo.1"]').should('exist');
    // A stored address that is not a web address is not made a link.
    cy.contains('javascript:alert(1)').should('not.exist');
  });

  it('shows only the status while nothing is recorded', () => {
    serve({ codecheck: null });
    mount();

    cy.wait('@getMetadata');
    cy.contains(NEEDS_CODECHECKER).should('exist');
    cy.get('.info-section').should('not.exist');
  });

  it('says so when the record cannot be loaded', () => {
    serve();
    cy.intercept('GET', '**/codecheck/metadata*', { statusCode: 500, body: '<html>fatal</html>' }).as('getMetadata');
    mount();

    cy.wait('@getMetadata');
    cy.contains('plugins.generic.codecheck.loadError').should('exist');
    cy.get('.codecheck-info').should('not.exist');
  });

  it('opens the CODECHECK tab through the workflow store', () => {
    serve();
    const stores = window.pkp.registry._piniaInstance._s;
    const navigateToMenu = cy.stub().as('navigateToMenu');
    stores.set('workflow', { navigateToMenu });
    mount();

    cy.wait('@getStatus');
    cy.contains('plugins.generic.codecheck.viewFullMetadata').click();
    cy.get('@navigateToMenu').should('have.been.calledOnceWith', 'codecheck');
    cy.then(() => stores.delete('workflow'));
  });
});
