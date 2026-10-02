import { h } from 'vue';
import '../../support/pkp-mock.js';
import CodecheckCodecheckerDialog from '../../../resources/js/Components/CodecheckCodecheckerDialog.vue';

/**
 * The body of the "add codechecker" dialog. It draws its own buttons — OJS's
 * are disabled for good by the first click, so a dialog that stays open to say
 * why it refused would have nothing left to press (#180).
 */
describe('CodecheckCodecheckerDialog', () => {
  let submitted;
  let closed;

  const mountDialog = (onSubmit = () => {}) => {
    submitted = [];
    closed = 0;
    cy.mount(CodecheckCodecheckerDialog, {
      props: {
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

  // Matched on the path alone: a URL string matches by substring, so the
  // directory's would answer the lookup's requests too.
  const DIRECTORY = { method: 'GET', pathname: '/api/v1/codecheck/codecheckers' };
  const LOOKUP = { method: 'GET', pathname: '/api/v1/codecheck/codecheckers/lookup' };

  // No directory and no suggestion unless a test says otherwise, so no test
  // reaches past the mock.
  beforeEach(() => {
    cy.intercept(DIRECTORY, { success: true, codecheckers: [] }).as('directory');
    cy.intercept(LOOKUP, { success: true, github: null, source: null }).as('lookup');
  });

  it('hands over the name and the ORCID it was given, then closes', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097');
    submit();

    cy.then(() => {
      expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '0000-0002-1825-0097', github: '' }]);
      expect(closed).to.equal(1);
    });
  });

  it('trims the name and hands over an empty ORCID as empty', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('  Ada Lovelace  ');
    submit();

    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '', github: '' }]));
  });

  /**
   * An empty name used to close the dialog and add nothing, so the editor was
   * told the codechecker had been added when it had not.
   */
  it('refuses an empty name, says so beside the field, and stays usable', () => {
    mountDialog();
    submit();

    cy.get('.modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.nameRequired');
    cy.then(() => {
      expect(submitted).to.deep.equal([]);
      expect(closed).to.equal(0);
    });

    // the point of drawing our own buttons: the editor can correct and retry
    cy.contains('.modal-actions button', 'Add').should('not.be.disabled');
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    submit();
    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '', github: '' }]));
  });

  it('refuses an ORCID whose check digit does not agree', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0098');
    submit();

    cy.get('.modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.orcidInvalid');
    cy.then(() => expect(closed).to.equal(0));
  });

  it('clears the message as soon as the field is corrected', () => {
    mountDialog();
    submit();
    cy.get('.modal-field-error').should('exist');
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('.modal-field-error').should('not.exist');
  });

  it('reduces an ORCID pasted as an address to the bare iD', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('input[id^=codecheck-checker-orcid]').type('https://www.orcid.org/0000-0002-1825-0097');
    submit();

    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '0000-0002-1825-0097', github: '' }]));
  });

  /** The dialog is what asked for the value, so it is where a refusal belongs. */
  it('shows a reason the caller refused the value with, and does not close', () => {
    mountDialog(() => 'nothing was saved');
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    submit();

    cy.get('.modal-field-error').should('have.text', 'nothing was saved');
    cy.then(() => expect(closed).to.equal(0));

    // and the refusal is cleared by the next attempt rather than piling up
    submit();
    cy.get('.modal-field-error').should('have.length', 1);
  });

  /** Nothing else catches a throw here: OJS calls the handler fire-and-forget. */
  it('stays open and says something when the caller throws', () => {
    mountDialog(() => { throw new Error('boom'); });
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    submit();

    cy.get('.modal-field-error').should('have.text', 'plugins.generic.codecheck.dialog.submitFailed');
    cy.contains('.modal-actions button', 'Add').should('not.be.disabled');
    cy.then(() => expect(closed).to.equal(0));
  });

  it('closes without submitting when cancelled', () => {
    mountDialog();
    cy.contains('.modal-actions button', 'common.cancel').click();
    cy.then(() => {
      expect(submitted).to.deep.equal([]);
      expect(closed).to.equal(1);
    });
  });

  /**
   * The fields used to be read out of the document by a fixed id, so a second
   * copy on the page answered for the first — and the `<label for>` of both
   * pointed at whichever rendered first.
   */
  it('gives each copy on the page its own field ids', () => {
    const props = { submitLabel: 'Add', onSubmit: () => {}, onClose: () => {} };
    cy.mount({
      components: { CodecheckCodecheckerDialog },
      render() {
        return [
          h(CodecheckCodecheckerDialog, props),
          h(CodecheckCodecheckerDialog, props)
        ];
      }
    });

    cy.get('input[id^=codecheck-checker-name]').should('have.length', 2).then(($inputs) => {
      expect($inputs.eq(0).attr('id')).not.to.equal($inputs.eq(1).attr('id'));
    });
  });

  describe('GitHub username (#186)', () => {
    it('hands over a username pasted as a mention or an address as the bare name', () => {
      mountDialog();
      cy.get('input[id^=codecheck-checker-name]').type('Daniel');
      cy.get('input[id^=codecheck-checker-github]').type('https://github.com/nuest');
      submit();

      cy.then(() => expect(submitted).to.deep.equal([{ name: 'Daniel', orcid: '', github: 'nuest' }]));
    });

    it('refuses what GitHub would not accept as a username, and stays open', () => {
      mountDialog();
      cy.get('input[id^=codecheck-checker-name]').type('Daniel');
      cy.get('input[id^=codecheck-checker-github]').type('daniel nuest');
      submit();

      cy.get('.modal-field-error')
        .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.githubInvalid');
      cy.then(() => {
        expect(submitted).to.deep.equal([]);
        expect(closed).to.equal(0);
      });
    });

    /**
     * Offered, never filled in: the blur that asks is also the one pressing
     * Add causes, so a value filled in then would be added unseen.
     */
    it('offers a username for a known ORCID iD, says where from, and fills it only when taken', () => {
      cy.intercept(LOOKUP, { success: true, github: 'jcarberry', source: 'community' }).as('lookup');
      mountDialog();
      cy.get('input[id^=codecheck-checker-name]').type('Josiah Carberry');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097').blur();

      cy.wait('@lookup').its('request.url').should('contain', 'orcid=0000-0002-1825-0097');
      cy.get('.codecheck-github-suggestion')
        .should('contain.text', 'The CODECHECK community list gives the GitHub username jcarberry');
      cy.get('input[id^=codecheck-checker-github]').should('have.value', '');

      cy.contains('.codecheck-github-suggestion button', 'plugins.generic.codecheck.codecheckers.githubSuggestion.use').click();
      cy.get('input[id^=codecheck-checker-github]').should('have.value', 'jcarberry');
      cy.get('.codecheck-github-suggestion').should('not.exist');
      cy.get('input[id^=codecheck-checker-github]').clear().type('someone-else');
      submit();

      cy.then(() => expect(submitted).to.deep.equal([
        { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: 'someone-else' }
      ]));
    });

    it('adds no username the editor did not take', () => {
      cy.intercept(LOOKUP, { success: true, github: 'jcarberry', source: 'community' }).as('lookup');
      mountDialog();
      cy.get('input[id^=codecheck-checker-name]').type('Josiah Carberry');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097');
      submit();

      cy.then(() => expect(submitted).to.deep.equal([
        { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: '' }
      ]));
    });

    it('withdraws the offer when the ORCID iD changes', () => {
      cy.intercept(LOOKUP, { success: true, github: 'jcarberry', source: 'community' }).as('lookup');
      mountDialog();
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097').blur();
      cy.get('.codecheck-github-suggestion').should('exist');
      cy.get('input[id^=codecheck-checker-orcid]').type('{backspace}');
      cy.get('.codecheck-github-suggestion').should('not.exist');
    });

    it('never replaces a username the editor typed', () => {
      cy.intercept(LOOKUP, { success: true, github: 'jcarberry', source: 'community' }).as('lookup');
      mountDialog();
      cy.get('input[id^=codecheck-checker-github]').type('typed-by-hand');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097').blur();

      cy.get('input[id^=codecheck-checker-github]').should('have.value', 'typed-by-hand');
      cy.get('.codecheck-github-suggestion').should('not.exist');
      cy.get('@lookup.all').should('have.length', 0);
    });

    it('asks nothing for an ORCID iD that is not one', () => {
      mountDialog();
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0098').blur();
      cy.get('input[id^=codecheck-checker-github]').should('have.value', '');
      cy.get('@lookup.all').should('have.length', 0);
    });

    it('works without a suggestion when the lookup is refused', () => {
      cy.intercept(LOOKUP, { statusCode: 401, body: {} }).as('lookup');
      mountDialog();
      cy.get('input[id^=codecheck-checker-name]').type('Josiah Carberry');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097').blur();
      cy.wait('@lookup');
      submit();

      cy.then(() => expect(submitted).to.deep.equal([
        { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: '' }
      ]));
    });
  });

  describe("the journal's directory (#186)", () => {
    it('offers no picker while the journal has no codecheckers on file', () => {
      mountDialog();
      cy.wait('@directory');
      cy.get('select[id^=codecheck-checker-directory]').should('not.exist');
    });

    it('fills all three fields from a codechecker on file', () => {
      cy.intercept(DIRECTORY, {
        success: true,
        codecheckers: [
          { name: 'Daniel Nüst', orcid: '0000-0002-0024-5046', github: 'nuest' },
          { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: '' }
        ]
      }).as('directory');
      mountDialog();
      cy.get('select[id^=codecheck-checker-directory]').select('Daniel Nüst · 0000-0002-0024-5046 · @nuest');

      cy.get('input[id^=codecheck-checker-name]').should('have.value', 'Daniel Nüst');
      cy.get('input[id^=codecheck-checker-orcid]').should('have.value', '0000-0002-0024-5046');
      cy.get('input[id^=codecheck-checker-github]').should('have.value', 'nuest');
      submit();

      cy.then(() => expect(submitted).to.deep.equal([
        { name: 'Daniel Nüst', orcid: '0000-0002-0024-5046', github: 'nuest' }
      ]));
    });
  
    it('offers the username the journal has on file without asking the server', () => {
      cy.intercept(DIRECTORY, {
        success: true,
        codecheckers: [{ name: 'Daniel Nüst', orcid: '0000-0002-0024-5046', github: 'nuest' }]
      }).as('directory');
      mountDialog();
      cy.wait('@directory');
      cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-0024-5046').blur();

      cy.get('.codecheck-github-suggestion')
        .should('contain.text', 'This journal has recorded the GitHub username nuest');
      cy.get('@lookup.all').should('have.length', 0);
    });

    it('empties the fields an earlier pick filled when "someone new" is chosen', () => {
      cy.intercept(DIRECTORY, {
        success: true,
        codecheckers: [{ name: 'Daniel Nüst', orcid: '0000-0002-0024-5046', github: 'nuest' }]
      }).as('directory');
      mountDialog();
      cy.get('select[id^=codecheck-checker-directory]').select('Daniel Nüst · 0000-0002-0024-5046 · @nuest');
      cy.get('select[id^=codecheck-checker-directory]').select('plugins.generic.codecheck.codecheckers.directory.none');

      cy.get('input[id^=codecheck-checker-name]').should('have.value', '');
      cy.get('input[id^=codecheck-checker-orcid]').should('have.value', '');
      cy.get('input[id^=codecheck-checker-github]').should('have.value', '');
    });
});
});
