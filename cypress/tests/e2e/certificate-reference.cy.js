/**
 * The CODECHECK certificate among the article's references (#183), against a
 * real OJS: the editorial form's button and its endpoint, and the publish hook
 * that adds the line when the journal asks for that.
 *
 * What is unit tested is the line and how it joins the list
 * (`CertificateReferenceUnitTest`). What only a running OJS shows is that the
 * button reaches OJS's own publication edit, that the publish hook's line
 * survives `publish()` — which never reparses a `citationsRaw` set before it —
 * and that the article page then lists it. The endpoint's role set, editors
 * only, is pinned here too: the unit test cannot enumerate routes.
 *
 * **Restores what it changes.** Submission 8 is shared with
 * `publication-validation` and `status-handler`, which leave it at
 * *codechecker assigned*; so does this. Its references go back to none, as
 * the dataset ships it. Submission 5 is unpublished and published again by
 * the publish test, and put back without references and at *completed*, as
 * `publication-validation.cy.js` leaves it. The journal's mode goes back to
 * off and its status allow-list back to empty, the dataset's own settings.
 */

const JOURNAL = 'codecheck';

// In review, so its latest version can be edited; certificate 2022-018 with
// a Zenodo DOI.
const SUBMISSION = 8;
const SUBMISSION_LINE = /CODECHECK certificate 2022-018\. Zenodo\. https:\/\/doi\.org\/10\.5281\/zenodo\.7084333/;

// Published, in production and assigned to an issue: OJS has nothing of its
// own against publishing it again. Certificate 2020-018, an OSF DOI.
const PUBLISHABLE_SUBMISSION = 5;
const PUBLISHABLE_DOI = /10\.17605\/OSF\.IO\/ZTC7M/i;

// Published, so its latest version is locked.
const PUBLISHED_SUBMISSION = 2;

// The reviewer assigned to submission 9, and to nothing else.
const REVIEWED_SUBMISSION = 9;

const ASSIGNED_CODECHECKER = 'plugins.generic.codecheck.status.assignedCodechecker';
const COMPLETED = 'plugins.generic.codecheck.status.completed.fullReproduction';
const PUBLISHED_CERTIFICATE = 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction';

const OTHER_REFERENCE = 'Nüst, D., & Eglen, S. J. (2021). CODECHECK: an Open Science initiative for the independent execution of computations underlying research articles during peer review to improve reproducibility. F1000Research, 10, 253.';

const setStatusOf = (submissionId, status) => cy.recordCodecheckStatus(submissionId, status);

const addReference = (submissionId) => cy.ojsApi('POST', `api/v1/codecheck/references?submissionId=${submissionId}`);

const setMode = (mode) => cy.setCodecheckFields({ [`#certificateReference-${mode}`]: true });

/** The submission's latest publication, as OJS answers it. */
const latestPublication = (submissionId) =>
  cy.ojsApi('GET', `api/v1/submissions/${submissionId}`).then((response) => {
    expect(response.status).to.eq(200);
    const publications = response.body.publications;
    const latest = publications[publications.length - 1];
    return cy.ojsApi('GET', `api/v1/submissions/${submissionId}/publications/${latest.id}`).its('body');
  });

const setReferences = (submissionId, citationsRaw) =>
  latestPublication(submissionId).then((publication) =>
    cy.ojsApi('PUT', `api/v1/submissions/${submissionId}/publications/${publication.id}`, { citationsRaw })
      .its('status').should('eq', 200)
  );

const publication = (submissionId, publicationId, action) =>
  cy.ojsApi('PUT', `api/v1/submissions/${submissionId}/publications/${publicationId}/${action}`);

/** The journal's status allow-list for publishing, through the settings form. */
function allowOnlyStatuses(statusKeys) {
  cy.openCodecheckSettings();
  cy.get('[name="codecheckStatusKeysSelected[]"]').each(($box) => {
    if ($box.is(':checked') !== statusKeys.includes($box.attr('value'))) {
      cy.wrap($box).click({ force: true });
    }
  });
  cy.saveCodecheckSettings();
}

const backend = () => cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

/** Whether the journal had ORCID switched on; read rather than assumed. */
let orcidWasEnabled = null;

describe('CODECHECK certificate in the references', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    backend();
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    setMode('off');
    backend();
    setReferences(SUBMISSION, '');
    setStatusOf(SUBMISSION, ASSIGNED_CODECHECKER);

    // Submission 5 back to published, without references, at *completed* —
    // whatever state a failed publish test left it in.
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', false);
    allowOnlyStatuses([COMPLETED]);
    backend();
    setStatusOf(PUBLISHABLE_SUBMISSION, COMPLETED);
    cy.ojsApi('GET', `api/v1/submissions/${PUBLISHABLE_SUBMISSION}`).then((submission) => {
      const publicationId = submission.body.currentPublicationId;
      if (submission.body.status === 3) {
        publication(PUBLISHABLE_SUBMISSION, publicationId, 'unpublish').its('status').should('eq', 200);
      }
      cy.ojsApi('PUT', `api/v1/submissions/${PUBLISHABLE_SUBMISSION}/publications/${publicationId}`, { citationsRaw: '' })
        .its('status').should('eq', 200);
      publication(PUBLISHABLE_SUBMISSION, publicationId, 'publish').its('status').should('eq', 200);
    });
    allowOnlyStatuses([]);
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', true);
    cy.then(() => {
      if (orcidWasEnabled) {
        cy.setCodecheckSetting('orcidEnabled', true);
      }
    });
  });

  it('refuses while the journal lists no certificates', () => {
    setMode('off');
    backend();
    addReference(SUBMISSION).its('status').should('eq', 400);
  });

  it('refuses before the certificate is published', () => {
    setMode('button');
    backend();
    setStatusOf(SUBMISSION, ASSIGNED_CODECHECKER);
    addReference(SUBMISSION).its('status').should('eq', 400);
    latestPublication(SUBMISSION).its('citationsRaw').should('not.match', SUBMISSION_LINE);
  });

  it('adds the line beside the references already there, once', () => {
    setMode('button');
    backend();
    setStatusOf(SUBMISSION, PUBLISHED_CERTIFICATE);
    setReferences(SUBMISSION, OTHER_REFERENCE);

    addReference(SUBMISSION).then((response) => {
      expect(response.status, JSON.stringify(response.body)).to.eq(200);
      expect(response.body.changed).to.be.true;
      expect(response.body.line).to.match(SUBMISSION_LINE);
    });
    latestPublication(SUBMISSION).its('citationsRaw').then((raw) => {
      expect(raw.split('\n')[0], 'the reference already there is left as it was').to.eq(OTHER_REFERENCE);
      expect(raw).to.match(SUBMISSION_LINE);
    });

    // Asked again, nothing changes and nothing is added a second time.
    addReference(SUBMISSION).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.changed).to.be.false;
    });
    latestPublication(SUBMISSION).its('citationsRaw').then((raw) => {
      expect(raw.match(new RegExp(SUBMISSION_LINE.source, 'g'))).to.have.length(1);
    });
  });

  it('is pressed on the CODECHECK tab and says what it added', () => {
    setMode('button');
    backend();
    setStatusOf(SUBMISSION, PUBLISHED_CERTIFICATE);
    setReferences(SUBMISSION, '');

    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial?workflowSubmissionId=${SUBMISSION}&workflowMenuKey=codecheck`
    );
    cy.get('.certificate-reference button', { timeout: 20000 }).should('be.enabled').click();
    cy.get('.certificate-reference-line', { timeout: 20000 }).invoke('text').should('match', SUBMISSION_LINE);
    latestPublication(SUBMISSION).its('citationsRaw').should('match', SUBMISSION_LINE);
  });

  it('leaves a published version alone', () => {
    setMode('button');
    backend();
    addReference(PUBLISHED_SUBMISSION).its('status').should('eq', 400);
  });

  it('is for editors: the assigned reviewer is refused', () => {
    setMode('button');
    cy.ojsLogin('rreviewer', 'rreviewer');
    cy.visit(`/index.php/${JOURNAL}/submissions`);
    addReference(REVIEWED_SUBMISSION).its('status').should('eq', 401);
  });

  it('lists the certificate on publication, and the article page shows it', () => {
    // Publishing reaches for GitHub and ORCID on the same hook; both off.
    cy.setCodecheckSetting('codecheckRegisterDepositEnabled', false);
    cy.getCodecheckSetting('orcidEnabled').then((enabled) => {
      orcidWasEnabled = enabled;
      cy.setCodecheckSetting('orcidEnabled', false);
    });
    setMode('publish');
    allowOnlyStatuses([PUBLISHED_CERTIFICATE]);
    backend();
    setStatusOf(PUBLISHABLE_SUBMISSION, PUBLISHED_CERTIFICATE);

    cy.ojsApi('GET', `api/v1/submissions/${PUBLISHABLE_SUBMISSION}`).then((submission) => {
      const publicationId = submission.body.currentPublicationId;
      if (submission.body.status === 3) {
        publication(PUBLISHABLE_SUBMISSION, publicationId, 'unpublish').its('status').should('eq', 200);
      }
      publication(PUBLISHABLE_SUBMISSION, publicationId, 'publish').then((response) => {
        expect(response.status, JSON.stringify(response.body)).to.eq(200);
      });
      cy.ojsApi('GET', `api/v1/submissions/${PUBLISHABLE_SUBMISSION}/publications/${publicationId}`)
        .its('body.citationsRaw').should('match', PUBLISHABLE_DOI);
    });

    cy.visit(`/index.php/${JOURNAL}/article/view/${PUBLISHABLE_SUBMISSION}`);
    cy.get('.item.references').should('contain', 'CODECHECK certificate 2020-018').invoke('text').should('match', PUBLISHABLE_DOI);
    // Looked at rather than inferred: the References section as a reader sees it.
    cy.get('.item.references').scrollIntoView().screenshot('certificate-reference-article-page', { overwrite: true });
  });
});
