/**
 * The CODECHECK links in an article's Crossref and DataCite records (#19),
 * through OJS's own DOI API: the record is the one the DOI list's Export
 * button downloads, built by the agency plugin and validated by OJS — and
 * refused with a 400 if the plugin's additions made it invalid.
 *
 * The unit tests pin what the writers add to a document; what only a running
 * OJS shows is that the hooks are registered, that the article is found from
 * the record (by DOI for Crossref, by OJS's own identifier for DataCite, whose
 * test mode rewrites the DOI) and that the settings reach them.
 *
 * Nothing is deposited and nothing leaves the machine: both agencies stay in
 * test mode, the credentials are not real, the DOI prefix is 10.5555 — which
 * Crossref refuses to register — and only export and "mark registered" are
 * called, never deposit.
 *
 * Restores what it changes: the DOI it creates is deleted, the journal's DOI
 * prefix, publisher and registration agency are put back, both agency plugins
 * are switched off again and the two CODECHECK settings are switched off. The
 * agencies' own settings stay behind in `plugin_settings`, unused while the
 * plugins are off. The status table is append-only and "pending" is no status
 * one can record, so submission 4 is left at *completed*, as
 * `publication-validation.cy.js` leaves 5: nothing reader-facing reads it.
 */

const JOURNAL = 'codecheck';
const CONTEXT_ID = 1;

// Published, opted in, a Zenodo certificate DOI and one public GitHub
// repository, and touched by no other spec.
const SUBMISSION = 4;
const CERTIFICATE_DOI = '10.5281/zenodo.3981253';
const REPOSITORY = 'https://github.com/codecheckers/OpeningPractice';

const ARTICLE_DOI = '10.5555/codecheck-e2e.4';
const DATACITE_TEST_PREFIX = '10.5072';

const PUBLISHED_CERTIFICATE = 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction';
const COMPLETED = 'plugins.generic.codecheck.status.completed.fullReproduction';

const DOI_STATUS_REGISTERED = 3;
const DOI_STATUS_STALE = 5;

const AGENCIES = {
  crossref: {
    plugin: 'crossrefplugin',
    settings: {
      depositorName: 'CODECHECK e2e (test)',
      depositorEmail: 'doi-test@example.org',
      username: 'not-a-real-crossref-account',
      password: 'not-a-real-password',
      testMode: true,
      automaticRegistration: false,
    },
  },
  datacite: {
    plugin: 'dataciteplugin',
    settings: {
      username: 'NOT.A.REAL.ACCOUNT',
      password: 'not-a-real-password',
      testUsername: 'NOT.A.REAL.TEST.ACCOUNT',
      testPassword: 'not-a-real-password',
      testDOIPrefix: DATACITE_TEST_PREFIX,
      testMode: true,
      automaticRegistration: false,
    },
  },
};

const codecheckApi = (method, path, body) => cy.ojsApi(method, `api/v1/codecheck/${path}`, body);
const recordStatus = (status) =>
  codecheckApi('POST', `status/update?submissionId=${SUBMISSION}`, { status, userId: 1 }).its('status').should('eq', 200);

/** Switch a generic plugin on or off through the plugin grid, as its checkbox does. */
const setPluginEnabled = (plugin, enabled) =>
  cy.getCsrfToken().then((csrfToken) =>
    cy.request({
      method: 'POST',
      url: `/index.php/${JOURNAL}/$$$call$$$/grid/settings/plugins/settings-plugin-grid/${enabled ? 'enable' : 'disable'}`,
      form: true,
      body: { plugin, category: 'generic', csrfToken, disableNotification: true },
    }).its('status').should('eq', 200)
  );

/** The agency, its settings and DOIs with the test prefix, through the DOI settings' endpoints. */
const useAgency = (name) => {
  const agency = AGENCIES[name];
  setPluginEnabled(agency.plugin, true);
  cy.ojsApi('PUT', `api/v1/contexts/${CONTEXT_ID}`, {
    doiPrefix: '10.5555',
    // Crossref's `registrant`, which its schema requires and the dataset leaves empty.
    publisherInstitution: 'CODECHECK e2e publisher (test)',
  }).its('status').should('eq', 200);
  cy.ojsApi('PUT', `api/v1/contexts/${CONTEXT_ID}/registrationAgency`, {
    registrationAgency: agency.plugin,
    ...agency.settings,
  }).its('status').should('eq', 200);
};

/**
 * Export the article's record as the DOI list does, and yield it parsed.
 *
 * Two minutes rather than Cypress's 30 seconds: OJS validates every export
 * against the agency's published schema, fetching it and everything it imports
 * each time, and a Crossref export takes 20 to 35 seconds. One that ran past 30
 * failed the test and left the DOI half-changed for the next.
 */
const EXPORT_TIMEOUT = 120000;
const exportRecord = () =>
  cy.ojsApi('PUT', 'api/v1/dois/submissions/export', { ids: [SUBMISSION] }, { timeout: EXPORT_TIMEOUT }).then((response) => {
    expect(response.status, JSON.stringify(response.body)).to.eq(200);
    return cy.ojsApi('GET', `api/v1/dois/exports/${response.body.temporaryFileId}`).then((file) => {
      expect(file.status).to.eq(200);
      return new DOMParser().parseFromString(file.body, 'application/xml');
    });
  });

/** Crossref relations: `[{relationship, type, target}]`. */
const crossrefRelations = (xml) =>
  [...xml.getElementsByTagNameNS('http://www.crossref.org/relations.xsd', 'related_item')].flatMap((item) =>
    [...item.children].map((relation) => ({
      relationship: relation.getAttribute('relationship-type'),
      type: relation.getAttribute('identifier-type'),
      target: relation.textContent.trim(),
    }))
  );

/** DataCite related identifiers: `[{relation, type, target}]`. */
const dataciteRelations = (xml) =>
  [...xml.getElementsByTagName('relatedIdentifier')].map((identifier) => ({
    relation: identifier.getAttribute('relationType'),
    type: identifier.getAttribute('relatedIdentifierType'),
    target: identifier.textContent.trim(),
  }));

describe('CODECHECK links in DOI deposits', () => {
  let doiId;
  let journal;

  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dois`);

    cy.ojsApi('GET', `api/v1/contexts/${CONTEXT_ID}`).then((response) => {
      expect(response.status).to.eq(200);
      journal = response.body;
    });

    // The article's DOI. A published publication cannot be edited through the
    // API, so the DOI is created and assigned the way OJS's DOI list does it.
    cy.ojsApi('PUT', `api/v1/contexts/${CONTEXT_ID}`, { doiPrefix: '10.5555' }).its('status').should('eq', 200);
    cy.ojsApi('POST', 'api/v1/dois/submissions/assignDois', { ids: [SUBMISSION] }).its('status').should('eq', 200);
    cy.ojsApi('GET', `api/v1/submissions/${SUBMISSION}`).then((response) => {
      const publication = response.body.publications.find((p) => p.id === response.body.currentPublicationId);
      expect(publication.doiObject, 'the article has a DOI').to.exist;
      doiId = publication.doiObject.id;
      // Pinned, so the Crossref lookup by DOI is the lookup being tested.
      cy.ojsApi('PUT', `api/v1/dois/${doiId}`, { doi: ARTICLE_DOI }).its('status').should('eq', 200);
    });

    recordStatus(PUBLISHED_CERTIFICATE);
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dois`);
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dois`);
    cy.setCodecheckFields({ '#codecheckDoiDepositLinks': false, '#codecheckDoiRedeposit': false });
    cy.visit(`/index.php/${JOURNAL}/dois`);
    recordStatus(COMPLETED);
    if (doiId) {
      cy.ojsApi('DELETE', `api/v1/dois/${doiId}`);
    }
    // Switching an agency off also unsets it as the journal's agency.
    Object.values(AGENCIES).forEach(({ plugin }) => setPluginEnabled(plugin, false));
    cy.then(() =>
      cy.ojsApi('PUT', `api/v1/contexts/${CONTEXT_ID}`, {
        doiPrefix: journal.doiPrefix ?? null,
        publisherInstitution: journal.publisherInstitution ?? '',
      })
    );
  });

  describe('Crossref', () => {
    before(() => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dois`);
      useAgency('crossref');
    });

    it('carries no CODECHECK links while the setting is off', () => {
      cy.setCodecheckFields({ '#codecheckDoiDepositLinks': false });
      cy.visit(`/index.php/${JOURNAL}/dois`);
      exportRecord().then((xml) => {
        expect(xml.getElementsByTagName('doi')[0].textContent).to.eq(ARTICLE_DOI);
        expect(crossrefRelations(xml)).to.deep.eq([]);
      });
    });

    it('links the certificate as a review and the repository as a supplement', () => {
      cy.setCodecheckFields({ '#codecheckDoiDepositLinks': true });
      cy.visit(`/index.php/${JOURNAL}/dois`);
      exportRecord().then((xml) => {
        expect(crossrefRelations(xml)).to.deep.include.members([
          { relationship: 'hasReview', type: 'doi', target: CERTIFICATE_DOI },
          { relationship: 'isSupplementedBy', type: 'uri', target: REPOSITORY },
        ]);
      });
    });

    it('links nothing before the certificate is published', () => {
      recordStatus(COMPLETED);
      exportRecord().then((xml) => {
        expect(crossrefRelations(xml)).to.deep.eq([]);
      });
      recordStatus(PUBLISHED_CERTIFICATE);
    });

    it('marks a registered DOI stale when the links change, if the journal asks for it', () => {
      cy.setCodecheckFields({ '#codecheckDoiDepositLinks': true, '#codecheckDoiRedeposit': true });
      cy.visit(`/index.php/${JOURNAL}/dois`);
      const markRegistered = () =>
        cy.ojsApi('PUT', 'api/v1/dois/submissions/markRegistered', { ids: [SUBMISSION] }).its('status').should('eq', 200);
      const doiStatus = () => cy.ojsApi('GET', `api/v1/dois/${doiId}`).its('body.status');

      markRegistered();
      doiStatus().should('eq', DOI_STATUS_REGISTERED);
      // Taking the certificate back takes its links out of the record.
      recordStatus(COMPLETED);
      doiStatus().should('eq', DOI_STATUS_STALE);

      // Recording a status that leaves the links as they are does not.
      markRegistered();
      recordStatus(COMPLETED);
      doiStatus().should('eq', DOI_STATUS_REGISTERED);

      recordStatus(PUBLISHED_CERTIFICATE);
      doiStatus().should('eq', DOI_STATUS_STALE);
    });
  });

  describe('DataCite, in test mode', () => {
    before(() => {
      cy.ojsLogin('admin', 'admin');
      cy.visit(`/index.php/${JOURNAL}/dois`);
      useAgency('datacite');
      cy.setCodecheckFields({ '#codecheckDoiDepositLinks': true });
    });

    it('finds the article although test mode rewrote its DOI, and links the certificate and repository', () => {
      exportRecord().then((xml) => {
        const identifier = xml.getElementsByTagName('identifier')[0].textContent.trim();
        expect(identifier.startsWith(`${DATACITE_TEST_PREFIX}/`), identifier).to.be.true;
        expect(dataciteRelations(xml)).to.deep.include.members([
          { relation: 'IsReviewedBy', type: 'DOI', target: CERTIFICATE_DOI },
          { relation: 'IsSupplementedBy', type: 'URL', target: REPOSITORY },
        ]);
      });
    });
  });
});
