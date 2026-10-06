import '../../support/pkp-mock.js';
import CodecheckCodecheckerDialog from '../../../resources/js/Components/CodecheckCodecheckerDialog.vue';

/**
 * The body of the "add codechecker" dialog (#13): it offers the reviewers
 * assigned to the submission who are not codecheckers yet, and answers the
 * entry for the account chosen. It draws its own buttons — OJS's are disabled
 * for good by the first click, so a dialog that stays open to say why it
 * refused would have nothing left to press (#180).
 */
describe('CodecheckCodecheckerDialog', () => {
  const CARBERRY = {
    userId: 7, name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: '', doubleAnonymous: false, githubSuggestion: 'jcarberry',
  };
  const LOVELACE = {
    userId: 8, name: 'Ada Lovelace', orcid: '', github: 'ada', doubleAnonymous: true, githubSuggestion: null,
  };
  const SAVE_GITHUB = { method: 'POST', pathname: '/api/v1/codecheck/codecheckers/github' };

  let submitted;
  let closed;

  const mountDialog = (reviewers = [CARBERRY, LOVELACE], onSubmit = () => {}) => {
    submitted = [];
    closed = 0;
    cy.mount(CodecheckCodecheckerDialog, {
      props: {
        submissionId: 1,
        reviewers,
        submitLabel: 'Add',
        onSubmit: (value) => {
          submitted.push(value);
          return onSubmit(value);
        },
        onClose: () => { closed++; }
      }
    });
  };

  const submit = () => cy.contains('.modal-actions button', 'Add').click();
  const pick = (name) => cy.get('select[id^=codecheck-checker-reviewer]').select(name);

  it('answers the entry for the reviewer chosen, then closes', () => {
    mountDialog();
    pick('Ada Lovelace');
    submit();

    cy.then(() => {
      expect(submitted).to.deep.equal([{ userId: 8, name: 'Ada Lovelace', orcid: '', github: 'ada' }]);
      expect(closed).to.eq(1);
    });
  });

  it('refuses to add nobody, and can still be corrected', () => {
    mountDialog();
    submit();

    cy.get('.modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.reviewerRequired');
    cy.contains('.modal-actions button', 'Add').should('not.be.disabled');
    cy.then(() => expect(submitted).to.have.length(0));

    pick('Josiah Carberry');
    submit();
    cy.then(() => expect(submitted).to.have.length(1));
  });

  it('shows what the account holds, and says what it lacks', () => {
    mountDialog();
    pick('Ada Lovelace');

    cy.get('.codecheck-reviewer-details dd').eq(0).should('have.text', 'plugins.generic.codecheck.codecheckers.orcid.none');
    cy.get('.codecheck-reviewer-details dd').eq(1).should('contain', '@ada');
  });

  it('warns when the review is double-anonymous', () => {
    mountDialog();
    pick('Josiah Carberry');
    cy.get('.codecheck-double-anonymous').should('not.exist');

    pick('Ada Lovelace');
    cy.get('.codecheck-double-anonymous').should('have.text', 'plugins.generic.codecheck.codecheckers.doubleAnonymous');
  });

  it('explains how to assign a codechecker when no reviewer is left to add', () => {
    mountDialog([]);

    cy.get('.codecheck-no-reviewers').should('contain', 'plugins.generic.codecheck.codecheckers.noReviewers');
    cy.get('select[id^=codecheck-checker-reviewer]').should('not.exist');
    cy.contains('.modal-actions button', 'Add').should('not.exist');
  });

  /**
   * The community list's username is offered, never filled in, and taking
   * it saves it to the account, from which the entry is then copied.
   */
  describe("the community list's GitHub username", () => {
    it('is offered for an account without one and saved to the account when taken', () => {
      cy.intercept(SAVE_GITHUB, { success: true, github: 'jcarberry' }).as('saveGithub');
      mountDialog();
      pick('Josiah Carberry');

      cy.get('.codecheck-github-suggestion')
        .should('contain', 'The CODECHECK community list gives the GitHub username jcarberry for this ORCID iD.');
      cy.get('.codecheck-github-suggestion button').click();

      cy.wait('@saveGithub').its('request.body').should('deep.equal', { userId: 7, github: 'jcarberry' });
      cy.get('.codecheck-github-suggestion').should('not.exist');
      cy.get('.codecheck-reviewer-details dd').eq(1).should('contain', '@jcarberry');
      submit();
      cy.then(() => expect(submitted[0].github).to.eq('jcarberry'));
    });

    it('is not taken unasked', () => {
      mountDialog();
      pick('Josiah Carberry');
      submit();

      cy.then(() => expect(submitted[0].github).to.eq(''));
    });

    it('says why when the account refuses it', () => {
      cy.intercept(SAVE_GITHUB, { statusCode: 400, body: { success: false, error: 'Another account already has this GitHub username.' } });
      mountDialog();
      pick('Josiah Carberry');
      cy.get('.codecheck-github-suggestion button').click();

      cy.get('.modal-field-error').should('have.text', 'Another account already has this GitHub username.');
      cy.get('.codecheck-reviewer-details dd').eq(1).should('contain', 'plugins.generic.codecheck.codecheckers.github.none');
    });
  });
});
