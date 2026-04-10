import '../../support/pkp-mock.js';
import CodecheckRepositoryList from '../../../resources/js/Components/CodecheckRepositoryList.vue';

describe('CodecheckRepositoryList Component', () => {
  it('mounts and displays initial repository input', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        description: 'Enter repository URL',
        value: ''
      }
    });
    
    cy.get('.repository-row').should('have.length', 1);
    cy.get('input[type="url"]').should('have.value', 'https://');
  });

  it('loads existing repositories from value prop', () => {
    const existingRepos = 'https://github.com/user/repo1\nhttps://gitlab.com/user/repo2';
    
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: existingRepos
      }
    });
    
    cy.get('.repository-row').should('have.length', 2);
    cy.get('input[type="url"]').eq(0).should('have.value', 'https://github.com/user/repo1');
    cy.get('input[type="url"]').eq(1).should('have.value', 'https://gitlab.com/user/repo2');
  });

  it('allows adding new repository', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    cy.get('.btn-add').click();
    
    cy.get('.repository-row').should('have.length', 2);
  });

  it('allows removing repository', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: 'https://github.com/test/repo'
      }
    });
    
    cy.get('.repository-row').should('have.length', 1);
    cy.get('.btn-remove').click();
    cy.get('.repository-row').should('have.length', 0);
  });

  it('accepts valid repository URLs', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    // Type a valid URL
    cy.get('input[type="url"]').clear().type('https://github.com/test/repo');
    cy.get('input[type="url"]').should('have.value', 'https://github.com/test/repo');
    
    // No errors should appear
    cy.get('.pkpFormField__error').should('not.exist');
  });

  it('allows multiple repositories', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    // Add and fill first repo
    cy.get('input[type="url"]').clear().type('https://github.com/user/repo1');
    
    // Add second repo
    cy.get('.btn-add').click();
    cy.get('input[type="url"]').eq(1).clear().type('https://gitlab.com/user/repo2');
    
    // Verify both exist
    cy.get('.repository-row').should('have.length', 2);
    cy.get('input[type="url"]').eq(0).should('have.value', 'https://github.com/user/repo1');
    cy.get('input[type="url"]').eq(1).should('have.value', 'https://gitlab.com/user/repo2');
  });
});