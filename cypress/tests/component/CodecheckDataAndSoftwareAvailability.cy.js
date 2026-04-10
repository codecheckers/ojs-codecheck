import '../../support/pkp-mock.js';
import CodecheckDataAndSoftwareAvailability from '../../../resources/js/Components/CodecheckDataAndSoftwareAvailability.vue';

describe('CodecheckDataAndSoftwareAvailability Component', () => {
  it('renders textarea with correct placeholder', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: ''
      }
    });
    
    cy.get('textarea').should('have.attr', 'placeholder', 'plugins.generic.codecheck.dataSoftwareAvailability.description');
  });

  it('displays initial value from props', () => {
    const testValue = 'Data is available at https://zenodo.org/record/123';
    
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: testValue
      }
    });
    
    cy.get('textarea').should('have.value', testValue);
  });

  it('emits input event when text changes', () => {
    const onInputSpy = cy.spy().as('inputSpy');
    
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: '',
        onInput: onInputSpy
      }
    });
    
    cy.get('textarea').type('New data availability statement');
    
    cy.get('@inputSpy').should('have.been.called');
  });

  it('can clear text with remove button', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: 'Some initial text'
      }
    });
    
    cy.get('textarea').should('have.value', 'Some initial text');
    
    cy.get('.btn-remove').click();
    
    cy.get('textarea').should('have.value', '');
  });

  it('renders remove button with correct styling', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: ''
      }
    });
    
    cy.get('.btn-remove')
      .should('exist')
      .and('have.css', 'background-color', 'rgb(220, 53, 69)'); // #dc3545
  });

  it('textarea is resizable', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        value: ''
      }
    });
    
    cy.get('textarea')
      .should('have.css', 'resize', 'none')
      .and('have.css', 'overflow', 'scroll');
  });
});