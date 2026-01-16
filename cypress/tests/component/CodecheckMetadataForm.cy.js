import '../../support/pkp-mock.js';
import CodecheckMetadataForm from '../../../resources/js/Components/CodecheckMetadataForm.vue';

describe('CodecheckMetadataForm Component', () => {
  beforeEach(() => {
    cy.intercept('GET', '**/codecheck/metadata*', {
      statusCode: 200,
      body: {
        submissionId: 1,
        submission: {
          id: 1,
          title: 'Test Article',
          authors: [
            { name: 'John Doe', orcid: '0000-0001-2345-6789' }
          ],
          doi: '10.1234/test.2024',
          codeRepository: 'https://github.com/example/code',
          dataRepository: '',
          manifestFiles: '',
          dataAvailabilityStatement: ''
        },
        codecheck: null
      }
    }).as('loadMetadata');
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
    
    cy.contains('Test Article').should('exist');
    cy.contains('John Doe').should('exist');
    cy.contains('0000-0001-2345-6789').should('exist');
  });

  it('can add manifest files', () => {
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
    
    cy.contains(fileName).should('exist');
  });

  it('can add repositories', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('button', /add/i).first().click();
    
    cy.get('input[type="url"]').should('have.length.at.least', 1);
  });


  it('can add codecheckers', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /codechecker/i).should('exist');
    
    cy.contains('.field-label', /codechecker/i)
      .parent()
      .within(() => {
        cy.contains('button', /add/i).should('exist').and('not.be.disabled');
      });
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
    
    cy.get('.form-footer').should('exist');
    
    cy.get('.footer-actions button').contains(/save/i).click();
    
    cy.get('.save-message.error').should('exist');
  });

  it('can fill certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.identifier-section input[type="text"]').type('CODECHECK-2024-001');
    
    cy.get('.identifier-section input[type="text"]').should('have.value', 'CODECHECK-2024-001');
  });

  it('can reserve a certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.identifier-section').within(() => {
      cy.contains('button', /reserve/i).click();
    });
    
    cy.get('.identifier-section input[type="text"]').should('not.have.value', '');
    cy.get('.identifier-section input[type="text"]').invoke('val').should('match', /CODECHECK-\d{4}-\d{4}/);
  });

  it('disables preview button when requirements not met', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.form-footer').should('exist');
    
    cy.get('.footer-actions button').contains(/preview/i).should('be.disabled');
  });

  it('can fill summary field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('label', /summary/i).parent().within(() => {
      cy.get('textarea').type('This is a test summary of the code check process.');
    });
    
    cy.contains('label', /summary/i).parent().within(() => {
      cy.get('textarea').should('contain.value', 'This is a test summary');
    });
  });
});