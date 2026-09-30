import '../../support/pkp-mock.js';
import { askForConfirmation, showInformation } from '../../../resources/js/dialogs.js';

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

  /** A question whose Yes removes something should not look like any other. */
  it('marks a destructive question as such', () => {
    askForConfirmation({
      title: 'Remove it?',
      question: 'Really?',
      onConfirm: () => {},
      destructive: true
    });

    cy.get('.pkp-mock-modal').should('have.attr', 'data-modal-style', 'negative');
    cy.get('.pkp-mock-modal__action').eq(1)
      .should('have.attr', 'data-warnable', 'true')
      .and('have.attr', 'data-primary', 'false');
  });

  it('leaves an ordinary question alone', () => {
    askForConfirmation({ title: 'Go on?', question: 'Really?', onConfirm: () => {} });

    cy.get('.pkp-mock-modal').should('have.attr', 'data-modal-style', '');
    cy.get('.pkp-mock-modal__action').eq(1)
      .should('have.attr', 'data-primary', 'true')
      .and('have.attr', 'data-warnable', 'false');
  });
});

/** Every dialog puts its primary action last, so the buttons do not move about. */
describe('showInformation', () => {
  it('puts Close first and the further action last', () => {
    showInformation({
      title: 'Preview',
      text: 'Something to read',
      actionLabel: 'Download',
      onAction: () => {}
    });

    cy.get('.pkp-mock-modal__action').should('have.length', 2);
    cy.get('.pkp-mock-modal__action').eq(0).should('have.text', 'common.close');
    cy.get('.pkp-mock-modal__action').eq(1)
      .should('have.text', 'Download')
      .and('have.attr', 'data-primary', 'true');
  });

  it('shows only a close button when no action was asked for', () => {
    showInformation({ title: 'Note', text: 'Something to read' });

    cy.get('.pkp-mock-modal__action').should('have.length', 1);
    cy.get('.pkp-mock-modal__action').should('have.text', 'common.close');
  });
});
