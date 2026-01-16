import '../../support/pkp-mock.js';
import CodecheckReviewDisplay from '../../../resources/js/Components/CodecheckReviewDisplay.vue';

describe('CodecheckReviewDisplay Component', () => {
  it('shows not opted in message when codecheckOptIn is false', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: false
        }
      }
    });
    
    cy.contains('plugins.generic.codecheck.notOptedIn').should('exist');
    cy.get('.codecheck-info').should('not.exist');
  });

  it('shows pending status when opted in but no metadata', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: {}
        }
      }
    });
    
    cy.get('.status-pending').should('exist');
    cy.contains('plugins.generic.codecheck.status.pending').should('exist');
  });

  it('shows in-progress status with partial metadata', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: {
            configVersion: 'latest',
            manifest: [{ file: 'output.png' }],
            codecheckers: [{ name: 'John Doe' }]
          }
        }
      }
    });
    
    cy.get('.status-in-progress').should('exist');
    cy.contains('plugins.generic.codecheck.status.inProgress').should('exist');
  });

  it('shows complete status with full metadata', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: {
            configVersion: 'latest',
            certificate: 'CODECHECK-2024-001',
            checkTime: '2024-01-15T10:00:00Z',
            manifest: [{ file: 'output.png', comment: 'Main result' }],
            codecheckers: [
              { name: 'John Doe', orcid: '0000-0001-2345-6789' }
            ],
            repository: 'https://github.com/test/repo',
            summary: 'Code executed successfully'
          }
        }
      }
    });
    
    cy.get('.status-complete').should('exist');
    cy.contains('CODECHECK-2024-001').should('exist');
    cy.contains('John Doe').should('exist');
    cy.contains('0000-0001-2345-6789').should('exist');
  });

  it('displays manifest files correctly', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: {
            manifest: [
              { file: 'figure1.png', comment: 'Main visualization' },
              { file: 'data.csv', comment: 'Dataset' },
              { file: 'script.R', comment: '' }
            ]
          }
        }
      }
    });
    
    cy.contains('figure1.png').should('exist');
    cy.contains('Main visualization').should('exist');
    cy.contains('data.csv').should('exist');
    cy.contains('script.R').should('exist');
  });

  it('parses JSON string metadata', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: JSON.stringify({
            certificate: 'CODECHECK-2024-002',
            codecheckers: [{ name: 'Jane Smith' }]
          })
        }
      }
    });
    
    cy.contains('CODECHECK-2024-002').should('exist');
    cy.contains('Jane Smith').should('exist');
  });

  it('handles view full metadata button click', () => {
    cy.mount(CodecheckReviewDisplay, {
      props: {
        submission: {
          codecheckOptIn: true,
          codecheckMetadata: {
            certificate: 'CODECHECK-2024-001'
          }
        }
      }
    });
    
    cy.contains('plugins.generic.codecheck.viewFullMetadata').click();
    
    cy.wait(100);
  });
});