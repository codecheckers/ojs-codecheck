/**
 * The CODECHECK panel on OJS's Publication › Metadata page (#34).
 *
 * The panel is put in front of OJS's own metadata form by extending the
 * workflow store, so what only a running OJS can answer is checked here: that
 * the menu state the plugin matches is the one OJS really gives that page,
 * that the journal's configuration reaches it through the dashboard, that the
 * button really moves the workflow to the CODECHECK tab — and that a submission
 * without a CODECHECK is explained in the same words on both tabs.
 *
 * Read-only: nothing here writes, so nothing needs restoring.
 */

const JOURNAL = 'codecheck';

// Published and opted in, with a reserved identifier.
const OPTED_IN = 5;
const OPTED_IN_CERTIFICATE = '2020-018';

const DESTINATIONS = ['registerIssue', 'registerCsv', 'orcid', 'articlePage', 'availabilityStatement', 'issueToc'];

/** What the destination entries may carry — booleans and public addresses only. */
const DESTINATION_KEYS = ['id', 'enabled', 'url', 'authorNames', 'sandbox'];

const openWorkflow = (submissionId, menuKey) =>
  cy.visit(
    `/index.php/${JOURNAL}/dashboard/editorial` +
    `?workflowSubmissionId=${submissionId}&workflowMenuKey=${menuKey}`
  );

const panel = () => cy.get('.codecheck-publication-info', { timeout: 20000 });

describe('CODECHECK on the publication Metadata page', () => {
  let noChoiceSubmission;

  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    // The dataset records no opt-in choice for some submissions; which ones is
    // read rather than assumed, so a dataset change fails loudly here.
    cy.ojsApi('GET', 'api/v1/submissions?count=100').then((response) => {
      expect(response.status).to.eq(200);
      const unset = response.body.items.filter(
        (submission) => submission.codecheckOptIn === null || submission.codecheckOptIn === undefined
      );
      expect(unset, 'a submission with no opt-in choice in the dataset').to.not.be.empty;
      noChoiceSubmission = unset[0].id;
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('injects the journal configuration without anything secret in it', () => {
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    cy.window().its('codecheckDashboardConfig.publicationInfo').then((config) => {
      expect(config.badge).to.have.property('text');
      expect(config.destinations.map((destination) => destination.id)).to.deep.equal(DESTINATIONS);
      config.destinations.forEach((destination) => {
        expect(Object.keys(destination)).to.satisfy(
          (keys) => keys.every((key) => DESTINATION_KEYS.includes(key)),
          `${destination.id} carries only ${DESTINATION_KEYS.join(', ')}`
        );
      });
    });
  });

  it('puts the panel above OJS\'s own metadata form', () => {
    openWorkflow(OPTED_IN, 'publication_metadata');

    panel().should('contain', 'This article takes part in CODECHECK');
    panel().find('.codecheck-publication-info__facts').should('contain', OPTED_IN_CERTIFICATE);
    panel().find('.codecheck-publication-info__destinations li').should('have.length', DESTINATIONS.length);

    // OJS's form is still there, and after the panel rather than replaced by it.
    cy.get('form', { timeout: 20000 }).should('exist').then(($form) => {
      panel().then(($panel) => {
        const position = $panel[0].compareDocumentPosition($form[0]);
        expect(position & Node.DOCUMENT_POSITION_FOLLOWING, 'the form follows the panel').to.be.greaterThan(0);
      });
    });
  });

  it('previews the codecheck.yml on request', () => {
    openWorkflow(OPTED_IN, 'publication_metadata');

    panel().find('.codecheck-publication-info__yaml summary').click();
    panel().find('.codecheck-publication-info__yaml pre', { timeout: 20000 })
      .should('contain', OPTED_IN_CERTIFICATE);
  });

  it('opens the CODECHECK tab', () => {
    openWorkflow(OPTED_IN, 'publication_metadata');

    panel().contains('button', 'Edit on the CODECHECK tab').click();
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
    cy.get('.codecheck-publication-info').should('not.exist');
  });

  it('gives the same reason on both tabs for a submission with no CODECHECK', () => {
    const reason = 'No CODECHECK choice was recorded for this article';

    openWorkflow(noChoiceSubmission, 'publication_metadata');
    panel().find('.codecheck-publication-info__not-opted-in').should('contain', reason);
    panel().find('.codecheck-publication-info__destinations').should('not.exist');

    openWorkflow(noChoiceSubmission, 'codecheck');
    cy.get('.codecheck-optin-warning', { timeout: 20000 }).should('contain', reason);
  });
});
