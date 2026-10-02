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
 * One submission is switched out of CODECHECK for the last test and switched
 * back in after(); everything else only reads.
 */

const JOURNAL = 'codecheck';

// Published and opted in, with a reserved identifier.
const OPTED_IN = 5;
const OPTED_IN_CERTIFICATE = '2020-018';

// Opted in in the dataset and written by no other spec; switched out for the
// last test, because every seeded submission is opted in.
const SWITCHED_OUT = 7;

/** What the journal's three reasons read in English (locale/en/locale.po). */
const REASONS = [
  'This article was not opted into CODECHECK during submission.',
  'The author opted out of CODECHECK for this article.',
  'No CODECHECK choice was recorded for this article',
];

const setOptIn = (submissionId, optIn) =>
  cy.ojsApi('PUT', `api/v1/submissions/${submissionId}`, { codecheckOptIn: optIn })
    .its('status').should('eq', 200);

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
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    // cy.ojsApi() reads the token off the page's pkp object.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    setOptIn(SWITCHED_OUT, true);
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
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    setOptIn(SWITCHED_OUT, false);

    openWorkflow(SWITCHED_OUT, 'publication_metadata');
    panel().find('.codecheck-publication-info__destinations').should('not.exist');
    panel().find('.codecheck-publication-info__not-opted-in').invoke('text').then((text) => {
      const reason = text.trim();
      expect(REASONS.some((known) => reason.startsWith(known)), `"${reason}" is one of the reasons`).to.be.true;

      openWorkflow(SWITCHED_OUT, 'codecheck');
      cy.get('.codecheck-optin-warning', { timeout: 20000 }).should('contain', reason);
    });
  });
});
