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

  it('hands over the name and the ORCID it was given, then closes', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097');
    submit();

    cy.then(() => {
      expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '0000-0002-1825-0097' }]);
      expect(closed).to.equal(1);
    });
  });

  it('trims the name and hands over an empty ORCID as empty', () => {
    mountDialog();
    cy.get('input[id^=codecheck-checker-name]').type('  Ada Lovelace  ');
    submit();

    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '' }]));
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
    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '' }]));
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

    cy.then(() => expect(submitted).to.deep.equal([{ name: 'Ada Lovelace', orcid: '0000-0002-1825-0097' }]));
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
});
