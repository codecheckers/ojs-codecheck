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

  it('fills the available width like any other optional field', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: { name: 'dataAvailabilityStatement', value: '' }
    });

    // The field used to sit at 90% beside a red clear button.
    cy.get('textarea').should('have.css', 'width').and('not.equal', '0px');
    cy.get('button').should('not.exist');
  });

  it('lets the author resize it and grows with its content', () => {
    cy.mount(CodecheckDataAndSoftwareAvailability, {
      props: {
        name: 'dataAvailabilityStatement',
        value: ''
      }
    });

    // Was resize:none with a scrollbar, alongside the clear button.
    cy.get('textarea')
      .should('have.css', 'resize', 'vertical')
      .and('have.css', 'overflow', 'hidden');
  });
});