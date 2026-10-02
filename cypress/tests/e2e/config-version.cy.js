/**
 * A config version the plugin does not implement cannot be recorded (#185).
 *
 * `ConstantsUnitTest` pins which versions are known. What needs a running
 * instance is the metadata endpoint answering a refusal, with a reason, for
 * `latest`, 1.0, a version a later release may add and a value that is not a
 * string at all — and writing nothing when it does.
 *
 * Only refusals are posted, so no fixture is changed and nothing is restored.
 *
 * Not here yet: a version the plugin knows but the journal has not enabled.
 * With one known version that cannot happen, so
 * `ConstantsUnitTest::testAVersionMustBeKnownAndOfferedOrAlreadyStored` is the
 * coverage. When a second version exists, add the case: untick it in the
 * settings, post it (400), post the stored one (200).
 */

const JOURNAL = 'codecheck';
const SUBMISSION = 2;

describe('Config version on save', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    // cy.ojsApi() reads the CSRF token off a backend page.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  const stored = () =>
    cy.ojsApi('GET', `api/v1/codecheck/metadata?submissionId=${SUBMISSION}`)
      .then((response) => {
        expect(response.status).to.eq(200);
        return response.body.codecheck.version;
      });

  ['latest', '1.0', '2.1', '', 2.0, null, ['2.0']].forEach((version) => {
    it(`refuses ${JSON.stringify(version)} and keeps the stored version`, () => {
      stored().then((before) => {
        expect(before, 'the record is on a known version').to.eq('2.0');

        cy.ojsApi('POST', `api/v1/codecheck/metadata?submissionId=${SUBMISSION}`, { version })
          .then((response) => {
            expect(response.status).to.eq(400);
            expect(response.body.error).to.be.a('string').and.not.be.empty;
          });

        stored().should('eq', before);
      });
    });
  });
});
