import '../../support/pkp-mock.js';
import { askForConfirmation } from '../../../resources/js/dialogs.js';

/**
 * `askForConfirmation` against the mocked OJS dialog.
 *
 * What is worth pinning here is that the question is answered exactly once.
 * OJS calls a dialog's `close` prop on Escape and on a click outside as well as
 * from an action, so the three ways out have to agree: dismissing means No, and
 * pressing a button must not also count as a dismissal.
 */
describe('askForConfirmation', () => {
  let confirmed;
  let cancelled;

  const ask = () => {
    confirmed = 0;
    cancelled = 0;
    askForConfirmation({
      title: 'Remove it?',
      question: 'Really?',
      onConfirm: () => { confirmed++; },
      onCancel: () => { cancelled++; }
    });
  };

  const button = (key) => cy.get('.pkp-mock-modal__action').contains(key);

  /** Escape and the overlay, which is the only route with no button. */
  const dismiss = () => cy.get('.pkp-mock-modal').then(($d) => $d[0].__dismiss());

  it('puts No first and Yes second, labelled from OJS', () => {
    ask();
    cy.get('.pkp-mock-modal__action').should('have.length', 2);
    cy.get('.pkp-mock-modal__action').eq(0).should('have.text', 'common.no');
    cy.get('.pkp-mock-modal__action').eq(1).should('have.text', 'common.yes');
  });

  it('confirms once, and does not also cancel', () => {
    ask();
    button('common.yes').click();

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.then(() => {
      expect(confirmed, 'confirmed').to.equal(1);
      expect(cancelled, 'cancelled').to.equal(0);
    });
  });

  it('cancels exactly once when No is pressed', () => {
    ask();
    button('common.no').click();

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.then(() => {
      expect(cancelled, 'cancelled').to.equal(1);
      expect(confirmed, 'confirmed').to.equal(0);
    });
  });

  /**
   * Dismissing used to run neither handler, so the editor who pressed Escape on
   * "open the register's first issue?" was never told that nothing had been
   * reserved.
   */
  it('treats a dismissal as No', () => {
    ask();
    dismiss();

    cy.then(() => {
      expect(cancelled, 'cancelled').to.equal(1);
      expect(confirmed, 'confirmed').to.equal(0);
    });
  });
});
