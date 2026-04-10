import '../../support/pkp-mock.js';
import CodecheckManifestFiles from '../../../resources/js/Components/CodecheckManifestFiles.vue';

describe('CodecheckManifestFiles Component', () => {
  it('mounts with empty initial state', () => {
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: '',
        isRequired: true
      }
    });
    
    cy.get('.manifest-file-row').should('have.length', 1);
    cy.get('.manifest-file-row input.form-control').first().should('have.value', '');
  });

  it('loads existing manifest files', () => {
    const existingFiles = 'figure1.png - Main result\ndata.csv - Dataset';
    
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: existingFiles
      }
    });
    
    cy.get('.manifest-file-row').should('have.length', 2);
    cy.get('.manifest-file-row').eq(0).find('input.form-control').eq(0).should('have.value', 'figure1.png');
    cy.get('.manifest-file-row').eq(0).find('input.form-control').eq(1).should('have.value', 'Main result');
    cy.get('.manifest-file-row').eq(1).find('input.form-control').eq(0).should('have.value', 'data.csv');
    cy.get('.manifest-file-row').eq(1).find('input.form-control').eq(1).should('have.value', 'Dataset');
  });

  it('allows adding new file entry', () => {
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: ''
      }
    });
    
    cy.get('.btn-add').click();
    
    cy.get('.manifest-file-row').should('have.length', 2);
  });

  it('allows removing file entry', () => {
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: 'file1.png\nfile2.csv'
      }
    });
    
    cy.get('.btn-remove').first().click();
    
    cy.get('.manifest-file-row').should('have.length', 1);
    cy.get('.manifest-file-row input.form-control').first().should('have.value', 'file2.csv');
  });

  it('maintains at least one empty row after removing all', () => {
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: 'onlyfile.txt'
      }
    });
    
    cy.get('.btn-remove').click();
    
    cy.get('.manifest-file-row').should('have.length', 1);
    cy.get('.manifest-file-row input.form-control').first().should('have.value', '');
  });

  it('formats output correctly', () => {
    cy.mount(CodecheckManifestFiles, {
      props: {
        name: 'manifestFiles',
        label: 'Manifest Files',
        value: ''
      }
    });
    
    cy.get('.manifest-file-row input.form-control').eq(0).type('output.png');
    cy.get('.manifest-file-row input.form-control').eq(1).type('Main visualization');
    
    cy.get('.btn-add').click();
    cy.get('.manifest-file-row').eq(1).find('input.form-control').eq(0).type('data.csv');
  });
});