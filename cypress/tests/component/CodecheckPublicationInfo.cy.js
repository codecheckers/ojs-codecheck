import '../../support/pkp-mock.js';
import CodecheckPublicationInfo from '../../../resources/js/Components/CodecheckPublicationInfo.vue';

const CONFIG = {
  badge: { url: '/badge.png', text: 'CODECHECKed', textColor: '#2d7f3e', style: 'height:24px; width:auto;' },
  destinations: [
    { id: 'registerIssue', enabled: true, url: 'https://github.com/codecheckers/register', authorNames: false },
    { id: 'registerCsv', enabled: false, url: 'https://github.com/codecheckers/register' },
    { id: 'orcid', enabled: true, sandbox: true },
    { id: 'articlePage', enabled: true },
    { id: 'availabilityStatement', enabled: true },
    { id: 'issueToc', enabled: false },
  ],
};

function mountOptedIn(config = CONFIG) {
  cy.mount(CodecheckPublicationInfo, {
    props: { submission: { id: 8, codecheckOptIn: true }, config },
  });
}

describe('CodecheckPublicationInfo', () => {
  beforeEach(() => {
    cy.intercept('GET', '**/codecheck/metadata*', {
      body: {
        success: true,
        codecheck: { certificate: '2026-007', issue: { url: 'https://github.com/codecheckers/register/issues/42' } },
      },
    }).as('getMetadata');
    cy.intercept('GET', '**/codecheck/status*', {
      body: { success: true, statusRecord: { status: 'plugins.generic.codecheck.status.assignedCodechecker' } },
    }).as('getStatus');
    cy.intercept('GET', '**/codecheck/yaml*', {
      body: { success: true, yaml: 'version: https://codecheck.org.uk/spec/config/1.0/\n', filename: 'codecheck.yml' },
    }).as('getYaml');
  });

  describe('a submission without a CODECHECK says why, and nothing else', () => {
    [
      { optIn: false, mode: 'opt-in', reason: 'plugins.generic.codecheck.warning.notOptedIn' },
      { optIn: false, mode: 'opt-out', reason: 'plugins.generic.codecheck.warning.optedOut' },
      { optIn: false, mode: 'mandatory', reason: 'plugins.generic.codecheck.warning.notOptedIn' },
      { optIn: null, mode: 'opt-in', reason: 'plugins.generic.codecheck.warning.noChoice' },
      { optIn: undefined, mode: 'opt-out', reason: 'plugins.generic.codecheck.warning.noChoice' },
    ].forEach(({ optIn, mode, reason }) => {
      it(`${String(optIn)} in an ${mode} journal`, () => {
        cy.mount(CodecheckPublicationInfo, {
          props: { submission: { id: 8, codecheckOptIn: optIn }, config: CONFIG, codecheckMode: mode },
        });

        cy.get('.codecheck-publication-info__not-opted-in').should('contain', reason);
        cy.get('.codecheck-publication-info__destinations').should('not.exist');
        cy.get('.codecheck-publication-info__yaml').should('not.exist');
      });
    });
  });

  it('shows the badge, the certificate, the status and the register issue', () => {
    mountOptedIn();

    cy.wait(['@getMetadata', '@getStatus']);
    cy.contains('plugins.generic.codecheck.publicationInfo.heading').should('exist');
    cy.get('.codecheck-publication-info__badge').should('have.attr', 'src', '/badge.png');
    cy.get('.codecheck-publication-info__facts')
      .should('contain', '2026-007')
      .and('contain', 'plugins.generic.codecheck.status.assignedCodechecker')
      .find('a').should('have.attr', 'href', 'https://github.com/codecheckers/register/issues/42');
  });

  it('shows the text instead when the journal has no badge image', () => {
    mountOptedIn({ ...CONFIG, badge: { url: null, text: 'Checked', textColor: '#123456' } });

    cy.get('.codecheck-publication-info__badge').should('not.exist');
    cy.get('.codecheck-publication-info__badge-text')
      .should('have.text', 'Checked')
      .and('have.attr', 'style').and('contain', 'color');
  });

  it('says when no identifier has been reserved', () => {
    cy.intercept('GET', '**/codecheck/metadata*', { body: { success: true, codecheck: null } }).as('getMetadata');
    mountOptedIn();

    cy.wait('@getMetadata');
    cy.get('.codecheck-publication-info__facts')
      .should('contain', 'plugins.generic.codecheck.publicationInfo.certificate.none')
      .find('a').should('not.exist');
  });

  it('lists every destination as on or off', () => {
    mountOptedIn();

    cy.get('.codecheck-publication-info__destinations li').should('have.length', 6);
    cy.get('[data-destination="registerIssue"]')
      .should('have.class', 'is-enabled')
      .and('contain', 'plugins.generic.codecheck.publicationInfo.destination.registerIssue')
      .and('contain', 'plugins.generic.codecheck.publicationInfo.destination.registerIssue.anonymous');
    cy.get('[data-destination="registerCsv"]')
      .should('have.class', 'is-disabled')
      .and('contain', 'plugins.generic.codecheck.publicationInfo.destination.off');
    cy.get('[data-destination="orcid"]')
      .should('contain', 'plugins.generic.codecheck.publicationInfo.destination.orcid.sandbox');
    cy.get('[data-destination="issueToc"]').should('have.class', 'is-disabled');
  });

  it('leaves out a destination it has no words for', () => {
    mountOptedIn({ ...CONFIG, destinations: [...CONFIG.destinations, { id: 'somethingNew', enabled: true }] });

    cy.get('.codecheck-publication-info__destinations li').should('have.length', 6);
    cy.get('[data-destination="somethingNew"]').should('not.exist');
  });

  it('fetches the codecheck.yml only when the preview is opened, and once', () => {
    let yamlRequests = 0;
    cy.intercept('GET', '**/codecheck/yaml*', (req) => {
      yamlRequests += 1;
      req.reply({ body: { success: true, yaml: 'paper:\n  title: A title\n' } });
    }).as('getYaml');
    mountOptedIn();

    cy.wait(['@getMetadata', '@getStatus']).then(() => expect(yamlRequests).to.equal(0));

    cy.get('.codecheck-publication-info__yaml summary').click();
    cy.wait('@getYaml');
    cy.get('.codecheck-publication-info__yaml pre').should('contain', 'title: A title');

    cy.get('.codecheck-publication-info__yaml summary').click();
    cy.get('.codecheck-publication-info__yaml summary').click();
    cy.get('.codecheck-publication-info__yaml pre').should('be.visible').then(() => expect(yamlRequests).to.equal(1));
  });

  it('links the register issue only when it is a web address', () => {
    cy.intercept('GET', '**/codecheck/metadata*', {
      body: { success: true, codecheck: { certificate: '2026-007', issue: { url: 'javascript:alert(1)' } } },
    }).as('getMetadata');
    mountOptedIn();

    cy.wait('@getMetadata');
    cy.get('.codecheck-publication-info__facts').should('contain', '2026-007').find('a').should('not.exist');
  });

  it('says there is nothing to preview when nothing has been recorded', () => {
    cy.intercept('GET', '**/codecheck/yaml*', {
      statusCode: 404, body: { success: false, error: 'No CODECHECK metadata found' },
    }).as('getYaml');
    mountOptedIn();

    cy.get('.codecheck-publication-info__yaml summary').click();
    cy.wait('@getYaml');
    cy.get('.codecheck-publication-info__yaml-empty')
      .should('contain', 'plugins.generic.codecheck.publicationInfo.yaml.none');
    cy.get('.codecheck-publication-info__yaml .codecheck-publication-info__error').should('not.exist');
  });

  it('reports an error page that is not JSON with its status', () => {
    cy.intercept('GET', '**/codecheck/status*', { statusCode: 500, body: '<html>Fatal error</html>' }).as('getStatus');
    mountOptedIn();

    cy.wait('@getStatus');
    cy.get('.codecheck-publication-info__error')
      .should('contain', 'plugins.generic.codecheck.loadError')
      .and('contain', 'HTTP 500');
  });

  it('leaves out the destinations when the journal sent none', () => {
    mountOptedIn({ badge: CONFIG.badge });

    cy.contains('plugins.generic.codecheck.publicationInfo.destinations.heading').should('not.exist');
    cy.get('.codecheck-publication-info__destinations').should('not.exist');
  });

  it('reports a failed load instead of the facts', () => {
    cy.intercept('GET', '**/codecheck/status*', { statusCode: 500, body: { success: false, error: 'boom' } }).as('getStatus');
    mountOptedIn();

    cy.wait('@getStatus');
    cy.get('.codecheck-publication-info__error').should('contain', 'boom');
    cy.get('.codecheck-publication-info__facts').should('not.exist');
  });

  it('opens the CODECHECK tab through the workflow store', () => {
    const stores = window.pkp.registry._piniaInstance._s;
    const navigateToMenu = cy.stub().as('navigateToMenu');
    stores.set('workflow', { navigateToMenu });
    mountOptedIn();

    cy.contains('button', 'plugins.generic.codecheck.publicationInfo.edit').click();
    cy.get('@navigateToMenu').should('have.been.calledOnceWith', 'codecheck')
      .then(() => stores.delete('workflow'));
  });
});
