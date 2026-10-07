/**
 * An author's pointer to a check of the paper done elsewhere (#190).
 *
 * The pointer is a submission field, `existingCodecheck`, judged when it is
 * saved; the CODECHECK tab offers the editor to load the record from it; and
 * the wizard's preview of the check is open to the submission's authors alone.
 * Nothing here contacts a repository: every load that would is either refused
 * before it fetches or stubbed.
 *
 * Submission 8 is seglen's; dnuest is not among its authors. The field is
 * removed again afterwards.
 */

const JOURNAL = 'codecheck';
const SUBMISSION = 8;
const POINTER = '10.5281/zenodo.14900193';

const api = (method, path, body) => cy.ojsApi(method, `api/v1/${path}`, body);

/** A backend page, for the CSRF token; an author has no editorial dashboard. */
const openBackend = (view = 'editorial') => cy.visit(`/index.php/${JOURNAL}/dashboard/${view}`);

const setPointer = (value) => api('PUT', `submissions/${SUBMISSION}`, { existingCodecheck: value });

describe('An existing check named by the author', () => {
  after(() => {
    cy.ojsLogin('admin', 'admin');
    openBackend();
    setPointer(null).its('status').should('eq', 200);
  });

  it('is refused when it is neither a DOI nor a web address', () => {
    cy.ojsLogin('admin', 'admin');
    openBackend();

    setPointer('javascript:alert(1)').then((response) => {
      expect(response.status).to.eq(400);
      expect(response.body).to.have.property('existingCodecheck');
    });
  });

  it('is stored on the submission and answered with the CODECHECK record', () => {
    cy.ojsLogin('admin', 'admin');
    openBackend();

    setPointer(POINTER).its('body.existingCodecheck').should('eq', POINTER);
    api('GET', `codecheck/metadata?submissionId=${SUBMISSION}`)
      .its('body.existingCodecheck').should('eq', POINTER);
  });

  it('is offered to the editor, who loads the record from it', () => {
    cy.ojsLogin('admin', 'admin');
    openBackend();
    setPointer(POINTER);

    // The fixture's record has a certificate already; the note is for a
    // record without one, so the answer is read without it.
    cy.intercept('GET', `**/codecheck/metadata?submissionId=${SUBMISSION}*`, (req) => {
      req.continue((res) => { res.body.codecheck.certificate = ''; });
    }).as('metadata');
    cy.intercept('POST', `**/codecheck/repository?submissionId=${SUBMISSION}*`, {
      statusCode: 200,
      body: { success: true, metadata: { paper: { title: 'x' }, summary: 'Loaded from the existing check' } },
    }).as('import');

    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?workflowSubmissionId=${SUBMISSION}&workflowMenuKey=codecheck`
    );
    cy.wait('@metadata');

    cy.get('.codecheck-existing-check a', { timeout: 20000 })
      .should('have.attr', 'href', `https://doi.org/${POINTER}`);
    cy.get('.codecheck-existing-check-load').click();

    cy.wait('@import').its('request.body').should('deep.equal', { repository: POINTER });
    cy.get('.codecheck-metadata-form textarea')
      .filter((_, el) => el.value === 'Loaded from the existing check')
      .should('have.length', 1);
  });

  it('can be previewed by the submission\'s author, who is told why an address is unusable', () => {
    cy.ojsLogin('seglen', 'seglen');
    openBackend('mySubmissions');

    api('POST', `codecheck/repository/preview?submissionId=${SUBMISSION}`, { address: 'not an address' })
      .then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.error).to.be.a('string').and.not.be.empty;
      });
  });

  it('cannot be previewed by an author of other submissions', () => {
    cy.ojsLogin('dnuest', 'dnuest');
    openBackend('mySubmissions');

    api('POST', `codecheck/repository/preview?submissionId=${SUBMISSION}`, { address: POINTER })
      .its('status').should('be.oneOf', [401, 403]);
  });
});
