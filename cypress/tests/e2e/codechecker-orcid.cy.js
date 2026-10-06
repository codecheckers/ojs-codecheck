/**
 * The ORCID rule where codecheckers are written, end to end.
 *
 * `CodecheckCodecheckersUnitTest` pins the rule itself. What needs a running
 * instance is the two things that happen around it: the metadata endpoint
 * refusing a mistyped iD with a reason, and the generated `codecheck.yml`
 * carrying **one** shape of iD — OJS stores an author's as the full
 * `https://orcid.org/…` URI and a codechecker's is bare, so the file used to
 * have both forms in neighbouring sections.
 *
 * The dataset gives author 4 of submission 2 Josiah Carberry's iD — the one
 * ORCID publishes for testing — stored as the URI, which is what makes the
 * second of those observable at all.
 */

const JOURNAL = 'codecheck';
const SUBMISSION = 2;
const CARBERRY = '0000-0002-1825-0097';

describe('Codechecker ORCID iDs', () => {
  let csrfToken;

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(
      `/index.php/${JOURNAL}/dashboard/editorial` +
      `?currentViewId=published&workflowSubmissionId=${SUBMISSION}&workflowMenuKey=codecheck`
    );
    cy.get('.codecheck-metadata-form', { timeout: 20000 }).should('exist');
    cy.window().then((win) => {
      csrfToken = win.pkp?.currentUser?.csrfToken;
      expect(csrfToken, 'CSRF token').to.exist;
    });
  });

  /** The record as it stands, so a test can put it back. */
  const readMetadata = () =>
    cy.request({
      method: 'GET',
      url: `/index.php/${JOURNAL}/api/v1/codecheck/metadata?submissionId=${SUBMISSION}`,
      headers: { 'X-Csrf-Token': csrfToken }
    }).its('body.codecheck');

  it('refuses an iD whose check digit does not agree, and says which', () => {
    readMetadata().then((stored) => {
      cy.saveCodecheckRecord(SUBMISSION, stored, [{ name: 'Mistyped', orcid: '0000-0002-1825-0098' }])
        .then((response) => {
          expect(response.status).to.eq(400);
          expect(response.body.success).to.eq(false);
          expect(response.body.error).to.contain('0000-0002-1825-0098');
        });

      // and nothing was written
      readMetadata().its('codecheckers').should('deep.equal', stored.codecheckers);
    });
  });

  /**
   * A codechecker is a reviewer of the submission, copied from their account
   * (#13): an entry typed in, with whatever iD, is not one, and nothing is
   * written. How an iD is stored is pinned in `CodecheckCodecheckersUnitTest`.
   */
  it('refuses a codechecker typed in rather than linked to a reviewer', () => {
    readMetadata().then((stored) => {
      cy.saveCodecheckRecord(SUBMISSION, stored, [
        ...stored.codecheckers,
        { name: 'Josiah Carberry', orcid: `https://orcid.org/${CARBERRY}` },
      ]).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.error).to.contain('Josiah Carberry is not a reviewer of this submission');
      });

      readMetadata().its('codecheckers').should('deep.equal', stored.codecheckers);
    });
  });

  /**
   * The author's iD is stored by OJS as a URI and the codechecker's is bare, so
   * without this the generated file declared the same kind of identifier in two
   * different ways.
   */
  it('writes one shape of iD into the generated codecheck.yml', () => {
    cy.request({
      method: 'GET',
      url: `/index.php/${JOURNAL}/api/v1/codecheck/yaml?submissionId=${SUBMISSION}`,
      headers: { 'X-Csrf-Token': csrfToken }
    }).then((response) => {
      expect(response.status).to.eq(200);
      const yaml = response.body.yaml;

      // the author's, which is stored as https://orcid.org/0000-0002-1825-0097
      expect(yaml, 'the author iD reaches the file').to.contain(CARBERRY);
      expect(yaml, 'and not as a URI').not.to.contain(`https://orcid.org/${CARBERRY}`);

      // every ORCID in the file is bare
      const ids = yaml.match(/ORCID:\s*\S+/g) ?? [];
      expect(ids.length, 'ORCID entries in the file').to.be.greaterThan(1);
      ids.forEach((line) => {
        expect(line).to.match(/ORCID:\s*'?\d{4}-\d{4}-\d{4}-\d{3}[0-9X]'?$/);
      });
    });
  });
});
