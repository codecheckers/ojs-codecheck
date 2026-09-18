/**
 * The data and software availability statement, edited by an editor.
 *
 * Until Issue #167 the statement could only be written by the author in the
 * submission wizard or through the REST API. Every editorial view of it was
 * read-only, so a statement left empty or entered in the wrong place could not
 * be corrected by anyone — while being rendered on every article landing page.
 *
 * The plugin adds the field to OJS's own publication Metadata form on the
 * `Form::config::before` hook. Which form it lands on is unit tested; what is
 * checked here is the part that only a running OJS can answer: that the field
 * really reaches the form OJS serves to the workflow, carries the recorded
 * value, and that saving it through that form's own endpoint reaches the
 * article page. Nothing in the plugin saves it — the round trip belongs to OJS,
 * which is exactly why it is worth pinning.
 */

const JOURNAL = 'codecheck';

// Published, with a statement already recorded, so the field has something to
// show and the article page has somewhere to show it.
const SUBMISSION = 5;
const FIELD = 'dataAvailabilityStatement';

const EDITED = 'Code and data archived at https://doi.org/10.5281/zenodo.7777777 (edited by the editor).';

let publicationId;
let originalStatement;

function api(method, path, body) {
  return cy.getCsrfToken().then((csrfToken) =>
    cy.request({
      method,
      url: `/index.php/${JOURNAL}/${path}`,
      headers: { 'X-Csrf-Token': csrfToken },
      body,
      failOnStatusCode: false,
    })
  );
}

/** The Metadata form as OJS serves it to the workflow. */
const metadataForm = () =>
  api('GET', `api/v1/submissions/${SUBMISSION}/publications/${publicationId}/_components/metadata`);

const availabilityField = (response) =>
  response.body.fields.find((field) => field.name === FIELD);

const setStatement = (statement) =>
  api('PUT', `api/v1/submissions/${SUBMISSION}/publications/${publicationId}`, {
    [FIELD]: statement,
  });

describe('Editing the availability statement', () => {
  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);

    api('GET', `api/v1/submissions/${SUBMISSION}`).then((submission) => {
      publicationId = submission.body.currentPublicationId;
      originalStatement = submission.body.publications.find(
        (publication) => publication.id === publicationId
      )[FIELD];
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
    // cy.getCsrfToken() reads the token off the page's pkp object.
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/dashboard/editorial`);
    setStatement(originalStatement);
  });

  it('offers the statement on the publication metadata form', () => {
    metadataForm().then((response) => {
      expect(response.status).to.eq(200);

      const field = availabilityField(response);

      expect(field, 'the field reaches the form OJS serves').to.exist;
      expect(field.component, 'plain text, not rich text').to.eq('field-textarea');
      expect(field.label).to.eq('Data and Software Availability');

      // A field whose group is not one of the form's groups is dropped by the
      // renderer, so it would reach the config and still never be seen. The
      // plugin sets no groupId on purpose: PKPMetadataForm declares no groups,
      // and getConfig() adds the default one and remaps every field onto it
      // after the hook has run.
      const groupIds = response.body.groups.map((group) => group.id);
      expect(groupIds, 'the field belongs to a group the form renders').to.include(field.groupId);
    });
  });

  it('shows the statement that is already recorded', () => {
    // A field that came up empty would wipe the author's statement on the
    // next save of a form the editor opened for some other reason.
    metadataForm().then((response) => {
      expect(availabilityField(response).value).to.eq(originalStatement);
    });
  });

  it('saves an edit and shows it on the article page', () => {
    setStatement(EDITED).then((response) => {
      expect(response.status, 'the publication accepts the field').to.eq(200);
      expect(response.body[FIELD]).to.eq(EDITED);
    });

    metadataForm().then((response) => {
      expect(availabilityField(response).value, 'the form comes back with the edit').to.eq(EDITED);
    });

    cy.visit(`/index.php/${JOURNAL}/article/view/${SUBMISSION}`);
    cy.contains(EDITED).should('be.visible');
  });

  it('does not add the field to the title and abstract form', () => {
    // ForTheEditors and the other publication forms extend or sit beside the
    // metadata form; the field belongs on exactly one of them.
    api(
      'GET',
      `api/v1/submissions/${SUBMISSION}/publications/${publicationId}/_components/titleAbstract`
    ).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.fields.some((field) => field.name === FIELD)).to.be.false;
    });
  });
});
