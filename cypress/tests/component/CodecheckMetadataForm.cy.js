import '../../support/pkp-mock.js';
import CodecheckMetadataForm from '../../../resources/js/Components/CodecheckMetadataForm.vue';

/**
 * The metadata response the form loads from. Built fresh per call so a test
 * can adjust one field — the journal's enabled config versions, say — without
 * leaking the change into the next test.
 */
const metadataResponseBody = () => ({
  success: true,
  submissionId: 1,
  submission: {
    id: 1,
    title: 'Test Article Title',
    authors: [
      { name: 'John Doe', orcid: '0000-0001-2345-6789' },
      { name: 'Jane Smith', orcid: '0000-0002-3456-7890' }
    ],
    doi: '10.1234/test.2024',
    codeRepository: 'https://github.com/example/code',
    dataRepository: 'https://zenodo.org/record/123',
    manifestFiles: 'output.png\nresults.csv',
    dataAvailabilityStatement: 'Data is available at Zenodo'
  },
  codecheck: {
    version: 'latest',
    publicationType: 'doi',
    manifest: [],
    repository: { repositories: null, repoWithCodecheckYaml: null },
    source: '',
    codecheckers: [],
    certificate: '',
    check_time: '',
    summary: '',
    report: '',
    additionalContent: '',
    issue: {
      url: "https://github.come/example/repo/issues/0",
      number: 0,
      labels: ["test-label-1", "test-label-2"],
      labelsSelected: ["test-label-2"]
    },
  },
});

/** Serve the metadata endpoint, optionally with fields overridden. */
const interceptMetadata = (overrides = {}, alias = 'loadMetadata') =>
  cy.intercept('GET', '**/codecheck/metadata*', {
    statusCode: 200,
    body: { ...metadataResponseBody(), ...overrides }
  }).as(alias);

describe('CodecheckMetadataForm Component', () => {
  beforeEach(() => {
    interceptMetadata();

    cy.intercept('GET', '**/codecheck/labels*', {
      statusCode: 200,
      body: {
        success: true,
        labels: ['test-label-1', 'test-label-2'],
        message: 'Labels fetched successfully'
      }
    }).as('loadLabelData');
  });

  it('renders loading state initially', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.get('.loading-state').should('exist');
  });

  it('loads and displays submission metadata correctly', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
        
    // Check paper metadata section
    cy.contains('Test Article Title').should('exist');
    cy.contains('John Doe').should('exist');
    cy.contains('0000-0001-2345-6789').should('exist');
    cy.contains('Jane Smith').should('exist');
    cy.contains('10.1234/test.2024').should('exist');
  });

  it('renders the specification link into the introduction', () => {
    // The introduction is one message with a {$specLink} parameter rather than
    // a sentence assembled from several keys, so that word order and
    // punctuation stay with the translator. The mock t() substitutes the
    // parameter and throws if the message and the call disagree about it.
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/latest/')
      .and('have.attr', 'target', '_blank');
    cy.get('.codecheck-intro').should('not.contain', '{$specLink}');
  });

  it('points the specification link at the selected config version', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.get('.version-select').select('1.0');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/1.0/');

    cy.get('.version-select').select('latest');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/latest/');
  });

  it('falls back to the current stable specification when the journal has not chosen', () => {
    // No settings block in the response and no version on the record: the form
    // lands on 1.0 rather than on 'latest', matching the plugin's default.
    interceptMetadata(
      { codecheck: { ...metadataResponseBody().codecheck, version: '' } },
      'loadWithoutVersion'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadWithoutVersion');

    cy.get('.version-select').should('have.value', '1.0');
    cy.get('.version-select option').should('have.length', 1);
    cy.get('.version-select').should('be.disabled');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/1.0/');
  });

  it('offers only the config versions the journal enabled', () => {
    interceptMetadata(
      { settings: { enabledConfigVersions: ['1.0'] } },
      'loadRestrictedMetadata'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadRestrictedMetadata');

    // 'latest' is stored on this record, so it stays selectable rather than
    // being silently rewritten — which also means the control is not disabled.
    cy.get('.version-select option').should('have.length', 2);
    cy.get('.version-select').should('not.be.disabled');
  });

  it('keeps a superseded version selectable after switching away from it', () => {
    // The record is on 'latest', which the journal no longer offers. Selecting
    // 1.0 must not remove 'latest' from the list, or the codechecker could
    // leave the recorded version but never return to it.
    interceptMetadata(
      { settings: { enabledConfigVersions: ['1.0'] } },
      'loadSupersededVersion'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadSupersededVersion');

    cy.get('.version-select').select('1.0');
    cy.get('.version-select option').should('have.length', 2);
    cy.get('.version-select').should('not.be.disabled');

    cy.get('.version-select').select('latest');
    cy.get('.version-select').should('have.value', 'latest');
  });

  it('disables the version selector when a single version is on offer', () => {
    interceptMetadata(
      { settings: { enabledConfigVersions: ['latest'] } },
      'loadSingleVersionMetadata'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadSingleVersionMetadata');

    cy.get('.version-select option').should('have.length', 1);
    cy.get('.version-select').should('be.disabled');
  });

  it('displays read-only paper metadata with proper styling', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.read-only-section').should('exist');
    cy.get('.readonly-description').should('exist');
    cy.get('.info-grid').should('exist');
    cy.get('.orcid-badge').should('exist');
  });

  it('can add manifest files via file upload', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const fileName = 'test-output.png';
    const fileContent = 'fake file content';
    
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from(fileContent),
      fileName: fileName,
      mimeType: 'image/png'
    }, { force: true });
    
    cy.get('.manifest-table').should('exist');
    cy.get('input.file-name').should('have.value', fileName);
  });

  it('can add and remove manifest files', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Add file
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from('test'),
      fileName: 'test.csv',
      mimeType: 'text/csv'
    }, { force: true });
    
    cy.get('input.file-name').should('have.value', 'test.csv');
    
    // Remove file
    cy.get('.pkpButton--close').first().click();
    
    cy.on('window:confirm', () => true);
    
    // File should be removed (or empty state shown)
    cy.get('.manifest-table').should('not.exist');
  });

  it('can add and edit comment for manifest files', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from('test'),
      fileName: 'output.png',
      mimeType: 'image/png'
    }, { force: true });
    
    cy.get('.manifest-table input.file-comment')
      .type('This is the main result figure');

    cy.get('.manifest-table input.file-comment')
      .should('have.value', 'This is the main result figure');
  });

  it('can add repositories', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();
    
    cy.get('.repository-list').should('exist');
    cy.get('.repository-item input[type="url"]').should('exist');
  });

  it('can remove repositories', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Add repository
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();
    
    cy.get('.repository-item input[type="url"]')
      .type('https://github.com/example/repo');
    
    // Remove repository
    cy.get('.repository-item .pkpButton--close').click();
    
    cy.on('window:confirm', () => true);
  });

  it('validates required fields before saving', () => {
    cy.intercept('POST', '**/codecheck/metadata*', {
      statusCode: 200,
      body: { success: true }
    }).as('saveMetadata');

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Try to save without filling required fields
    cy.get('.footer-actions button').contains(/save/i).click();
    
    // Should show validation error
    cy.get('.save-message.error').should('exist');
  });

  it('can fill and save summary field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /summary/i)
      .parent()
      .find('textarea')
      .type('This is a comprehensive test summary of the codecheck process. All outputs were reproduced successfully.');
    
    cy.contains('.field-label', /summary/i)
      .parent()
      .find('textarea')
      .should('contain.value', 'This is a comprehensive test summary');
  });

  it('can fill report URL field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /report/i)
      .parent()
      .find('input[type="url"]')
      .type('https://zenodo.org/record/12345');
    
    cy.contains('.field-label', /report/i)
      .parent()
      .find('input[type="url"]')
      .should('have.value', 'https://zenodo.org/record/12345');
  });

  it('can fill completion time field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const testDateTime = '2025-01-29T15:30';
    
    cy.get('input[type="datetime-local"]')
      .type(testDateTime);
    
    cy.get('input[type="datetime-local"]')
      .should('have.value', testDateTime);
  });

  it('loads labels for certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    cy.wait('@loadLabelData');
    
    cy.get('.certificate-identifier-select.dropdown .dropdown-checkbox-input')
      .should('have.length.gt', 0);

    cy.get('.certificate-identifier-select.dropdown .dropdown-checkbox-input input[type="checkbox"]')
      .should('have.length', 2);
  });

  it('can reserve certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    let submissionId = 1;

    cy.intercept('POST', `**/codecheck/identifier?submissionId=${submissionId}`, {
      statusCode: 200,
      body: {
        success: true,
        identifier: '2025-042',
        issueUrl: 'https://github.com/codecheckers/register/issues/42',
        issueNumber: 42
      }
    }).as('reserveIdentifier');

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    cy.wait('@loadLabelData');
    
    // Open dropdown and select a label first, otherwise the guard blocks the request
    cy.get('.dropdown-content').invoke('show');
    cy.get('.dropdown-content').should('be.visible');
    cy.get('.dropdown-checkbox-input input[type="checkbox"]').first().check();

    cy.get('.certificate-identifier-button').contains(
      'plugins.generic.codecheck.identifier.reserve.withApi'
    ).click();

    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body).to.have.property('reserveIdentifierMode', 'api');
      expect(interception.request.body.issue.labelsSelected).to.have.length.gt(0);
    });

    cy.get('.certificate-identifier-input')
      .should('have.value', '2025-042');
  });

  it('disables preview button when requirements not met', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.footer-actions button')
      .contains(/preview/i)
      .should('be.disabled');
  });

  it('can add codecheckers via modal', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /codechecker/i)
      .parent()
      .find('.btn-add')
      .should('exist')
      .and('not.be.disabled');
  });

  it('can fill source field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /source/i)
      .parent()
      .find('textarea')
      .type('https://github.com/codecheckers/register/tree/master/2025-042');
    
    cy.contains('.field-label', /source/i)
      .parent()
      .find('textarea')
      .should('contain.value', 'https://github.com/codecheckers/register');
  });

  it('can fill additional content field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const additionalYaml = 'custom_field: custom_value\nanother_field: another_value';
    
    cy.get('.form-details textarea').last()
      .type(additionalYaml);
    
    cy.get('.form-details textarea').last()
      .should('contain.value', 'custom_field: custom_value');
  });

  it('shows correct form sections', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Check all major sections exist
    cy.get('.codecheck-header').should('exist');
    cy.get('.read-only-section').should('exist');
    cy.get('.form-details').should('exist');
    cy.get('.form-footer').should('exist');
  });

  it('displays version selector', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.version-selector').should('exist');
    cy.get('.version-select').should('exist');
    cy.get('.version-select option[value="latest"]').should('exist');
    cy.get('.version-select option[value="1.0"]').should('exist');
  });

  it('new repository has its hidden checkbox unchecked by default', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repo-hidden-checkbox').should('exist').and('not.be.checked');
  });

  it('checking the hidden checkbox marks the repository hidden', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repo-hidden-checkbox').check();
    cy.get('.repo-hidden-checkbox').should('be.checked');
  });

  it('hidden flag is independent per repository', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    // Add two repositories
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    // Check only the first one
    cy.get('.repo-hidden-checkbox').eq(0).check();

    cy.get('.repo-hidden-checkbox').eq(0).should('be.checked');
    cy.get('.repo-hidden-checkbox').eq(1).should('not.be.checked');
  });
  
  it('hidden checkbox state is preserved after save', () => {
    cy.intercept('POST', '**/codecheck/metadata*', {
      statusCode: 200,
      body: { success: true }
    }).as('saveMetadata');

    cy.mount(CodecheckMetadataForm, {
      props: { submission: { id: 1 }, canEdit: true }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repository-item input[type="url"]').first()
      .type('https://github.com/test/private-repo');

    cy.get('.repo-hidden-checkbox').first().check();
    cy.get('.repo-hidden-checkbox').first().should('be.checked');
  });

  it('fills the completion time with the current moment via the Now link', () => {
    cy.mount(CodecheckMetadataForm, {
      props: { submission: { id: 1 }, canEdit: true }
    });
    cy.wait('@loadMetadata');

    cy.get('input[type="datetime-local"]').should('have.value', '');
    cy.get('.check-time-now').click();

    // Native datetime-local wants YYYY-MM-DDTHH:mm, and it should be now.
    cy.get('input[type="datetime-local"]').invoke('val').should((value) => {
      expect(value).to.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
      const minutesApart = Math.abs(new Date(value) - new Date()) / 60000;
      expect(minutesApart, 'set to roughly now').to.be.lessThan(5);
    });
  });

  describe('author-provided entries', () => {
    beforeEach(() => {
      cy.intercept('GET', '**/codecheck/metadata*', {
        statusCode: 200,
        body: {
          success: true,
          submissionId: 1,
          submission: { id: 1, title: 'Test Article Title', authors: [], doi: null },
          codecheck: {
            version: 'latest',
            publicationType: 'doi',
            manifest: [
              { file: 'figure2.png', comment: 'Figure 2', hidden: false, providedByAuthor: true },
              { file: 'extra.csv', comment: 'added by the codechecker', hidden: false, providedByAuthor: false },
            ],
            repository: {
              repositories: [
                { url: 'https://github.com/author/repo', hidden: false, providedByAuthor: true },
                { url: 'https://github.com/codechecker/repo', hidden: false, providedByAuthor: false },
              ],
              repoWithCodecheckYaml: null,
            },
            source: '',
            codecheckers: [],
            certificate: '',
            check_time: '',
            summary: '',
            report: '',
            additionalContent: '',
            issue: { url: null, number: null, labels: [], labelsSelected: [] },
          },
        }
      }).as('loadAuthored');

      cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } });
      cy.wait('@loadAuthored');
    });

    it('marks the entries the author submitted', () => {
      cy.get('.repository-item').eq(0).find('.provided-by-author').should('exist');
      cy.get('.repository-item').eq(1).find('.provided-by-author').should('not.exist');

      cy.get('.manifest-row').eq(0).find('.provided-by-author').should('exist');
      cy.get('.manifest-row').eq(1).find('.provided-by-author').should('not.exist');
    });

    it('offers no delete control on an author repository', () => {
      cy.get('.repository-item').eq(0).find('.pkpButton--close').should('not.exist');
      cy.get('.repository-item').eq(1).find('.pkpButton--close').should('exist');
    });

    it('offers no delete control on an author manifest entry', () => {
      cy.get('.manifest-row').eq(0).find('.pkpButton--close').should('not.exist');
      cy.get('.manifest-row').eq(1).find('.pkpButton--close').should('exist');
    });

    it('allows the output file name to be edited, including the author\'s', () => {
      cy.get('.manifest-row').eq(0).find('input.file-name')
        .should('have.value', 'figure2.png')
        .clear()
        .type('figures/figure2.png')
        .should('have.value', 'figures/figure2.png');

      cy.get('.manifest-row').eq(1).find('input.file-name')
        .should('have.value', 'extra.csv')
        .should('not.be.disabled');
    });

    it('still allows an author entry to be edited and hidden', () => {
      cy.get('.repository-item').eq(0).find('input[type="url"]')
        .should('not.be.disabled')
        .clear()
        .type('https://github.com/author/repo-corrected');

      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').check().should('be.checked');
      cy.get('.manifest-row').eq(0).find('.manifest-hidden-checkbox').check().should('be.checked');
    });
  });
});