import '../../support/pkp-mock.js';
import CodecheckMetadataForm from '../../../resources/js/Components/CodecheckMetadataForm.vue';

describe('CodecheckMetadataForm Component', () => {
  beforeEach(() => {
    // Mock the metadata API
    cy.intercept('GET', '**/codecheck/metadata*', {
      statusCode: 200,
      body: {
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
          repository: '',
          source: '',
          codecheckers: [],
          certificate: '',
          check_time: '',
          summary: '',
          report: '',
          additionalContent: ''
        }
      }
    }).as('loadMetadata');

    // Mock venue data API
    cy.intercept('GET', '**/codecheck/venue', {
      statusCode: 200,
      body: {
        success: true,
        message: 'Venue data loaded',
        venueTypes: ['Journal', 'Conference', 'Preprint'],
        venueNames: ['Nature', 'Science', 'PLOS ONE', 'arXiv']
      }
    }).as('getVenueData');
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
    cy.contains(fileName).should('exist');
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
    
    cy.contains('test.csv').should('exist');
    
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
    
    cy.get('.manifest-table input[type="text"]')
      .type('This is the main result figure');
    
    cy.get('.manifest-table input[type="text"]')
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

  it('loads venue data for certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    cy.wait('@getVenueData');
    
    cy.get('.certificate-identifier-venue-types option')
      .should('have.length.gt', 1);
    
    cy.get('.certificate-identifier-venue-names option')
      .should('have.length.gt', 1);
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
        issueUrl: 'https://github.com/codecheckers/register/issues/42'
      }
    }).as('reserveIdentifier');

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    cy.wait('@getVenueData');
    
    // Select venue type and name
    cy.get('.certificate-identifier-venue-types').select('Journal');
    cy.get('.certificate-identifier-venue-names').select('Nature');
    
    // Click reserve button
    cy.get('#certificate-identifier-button-wrapper')
      .find('button')
      .contains(/reserve/i)
      .click();
    
    cy.on('window:alert', (text) => {
      expect(text).to.contains('2025-042');
    });
    
    cy.wait('@reserveIdentifier');
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
});