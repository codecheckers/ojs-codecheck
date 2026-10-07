import '../../support/pkp-mock.js';
import CodecheckExistingCheck from '../../../resources/js/Components/CodecheckExistingCheck.vue';

/**
 * Issue #190. The wizard's pointer to a check of the paper done elsewhere: it
 * loads the check's repositories and expected outputs through the server and
 * hands them on, and only warns when the paper title differs.
 */
describe('CodecheckExistingCheck', () => {
  const PREVIEW = '**/codecheck/repository/preview?submissionId=7';

  const mountField = (value = '') => {
    const onInput = cy.stub().as('onInput');
    const onLoaded = cy.stub().as('onLoaded').returns(2);
    cy.mount(CodecheckExistingCheck, { props: { submissionId: 7, value, onInput, onLoaded } });
  };

  it('hands the address on as it is typed, and offers no load while it is empty', () => {
    mountField();
    cy.get('.existing-check-load').should('be.disabled');
    cy.get('#existingCodecheck').type(' 10.5281/zenodo.1 ');
    cy.get('@onInput').should('have.been.calledWith', '10.5281/zenodo.1');
    cy.get('.existing-check-load').should('not.be.disabled');
  });

  it('loads the entries of the check and says so', () => {
    cy.intercept('POST', PREVIEW, {
      statusCode: 200,
      body: {
        success: true,
        titleMatches: true,
        repositories: ['https://github.com/a/b'],
        manifest: [{ file: 'fig.png', comment: 'Figure 1' }],
      },
    }).as('preview');
    mountField('https://zenodo.org/records/1');

    cy.get('.existing-check-load').click();

    cy.wait('@preview').its('request.body').should('deep.equal', { address: 'https://zenodo.org/records/1' });
    cy.get('@onLoaded').should('have.been.calledWith', {
      repositories: ['https://github.com/a/b'],
      manifest: [{ file: 'fig.png', comment: 'Figure 1' }],
    });
    cy.get('.existing-check-message').should('have.text', 'plugins.generic.codecheck.existingCheck.loaded');
    cy.get('.existing-check-warning').should('not.exist');
  });

  it('warns about another paper title, and still hands the entries on', () => {
    cy.intercept('POST', PREVIEW, {
      statusCode: 200,
      body: { success: true, titleMatches: false, repositories: ['https://github.com/a/b'], manifest: [] },
    });
    mountField('https://zenodo.org/records/1');

    cy.get('.existing-check-load').click();

    cy.get('@onLoaded').should('have.been.calledOnce');
    cy.get('.existing-check-warning').should('have.text', 'plugins.generic.codecheck.existingCheck.titleMismatch');
  });

  it('says when the check adds nothing', () => {
    cy.intercept('POST', PREVIEW, {
      statusCode: 200,
      body: { success: true, titleMatches: true, repositories: [], manifest: [] },
    });
    const onLoaded = cy.stub().returns(0);
    cy.mount(CodecheckExistingCheck, {
      props: { submissionId: 7, value: '10.5281/zenodo.1', onInput: cy.stub(), onLoaded },
    });

    cy.get('.existing-check-load').click();

    cy.get('.existing-check-message').should('have.text', 'plugins.generic.codecheck.existingCheck.nothingFound');
  });

  it('says when everything the check lists is already there', () => {
    cy.intercept('POST', PREVIEW, {
      statusCode: 200,
      body: { success: true, titleMatches: true, repositories: ['https://github.com/a/b'], manifest: [] },
    });
    cy.mount(CodecheckExistingCheck, {
      props: { submissionId: 7, value: '10.5281/zenodo.1', onInput: cy.stub(), onLoaded: cy.stub().returns(0) },
    });

    cy.get('.existing-check-load').click();

    cy.get('.existing-check-message').should('have.text', 'plugins.generic.codecheck.existingCheck.alreadyAdded');
  });

  it('shows the server\'s reason when nothing could be loaded', () => {
    cy.intercept('POST', PREVIEW, {
      statusCode: 404,
      body: { success: false, error: 'codecheck.yml not found' },
    });
    mountField('https://zenodo.org/records/1');

    cy.get('.existing-check-load').click();

    cy.get('.existing-check-warning').should('contain', 'codecheck.yml not found');
    cy.get('@onLoaded').should('not.have.been.called');
  });
});
