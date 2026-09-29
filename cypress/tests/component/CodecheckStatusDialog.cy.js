import '../../support/pkp-mock.js';
import CodecheckStatusDialog from '../../../resources/js/Components/CodecheckStatusDialog.vue';

// The real keys, as Constants::CODECHECK_STATUSES lists them — the mock reads
// locale/en/locale.po and refuses a key with no entry there.
const STATUSES = [
  'plugins.generic.codecheck.status.needsCodechecker',
  'plugins.generic.codecheck.status.assignedCodechecker',
  'plugins.generic.codecheck.status.completed.fullReproduction'
];

/** The body of the "change CODECHECK status" dialog (#180). */
describe('CodecheckStatusDialog', () => {
  let submitted;
  let closed;

  const mountDialog = ({ statuses = STATUSES, currentStatus = STATUSES[0], onSubmit = () => {} } = {}) => {
    submitted = [];
    closed = 0;
    cy.mount(CodecheckStatusDialog, {
      props: {
        statuses,
        currentStatus,
        submitLabel: 'Change',
        onSubmit: (value) => {
          submitted.push(value);
          return onSubmit(value);
        },
        onClose: () => { closed++; }
      }
    });
  };

  const submit = () => cy.contains('.modal-actions button', 'Change').click();

  it('offers every status and starts on the current one', () => {
    mountDialog({ currentStatus: STATUSES[1] });

    cy.get('select[id^=codecheck-status-select] option').should('have.length', STATUSES.length);
    cy.get('select[id^=codecheck-status-select]').should('have.value', STATUSES[1]);
  });

  /** A record with no status yet must still offer something to record. */
  it('starts on the first status when the current one is not in the list', () => {
    mountDialog({ currentStatus: '' });
    cy.get('select[id^=codecheck-status-select]').should('have.value', STATUSES[0]);
  });

  it('hands over the status the editor chose, then closes', () => {
    mountDialog();
    cy.get('select[id^=codecheck-status-select]').select(STATUSES[2]);
    submit();

    cy.then(() => {
      expect(submitted).to.deep.equal([STATUSES[2]]);
      expect(closed).to.equal(1);
    });
  });

  /**
   * A refused update used to reach the console alone, the dialog having closed
   * on the way.
   */
  it('shows the reason the update was refused, and stays open and usable', () => {
    mountDialog({ onSubmit: () => 'the status could not be recorded' });
    submit();

    cy.get('.modal-field-error').should('have.text', 'the status could not be recorded');
    cy.then(() => expect(closed).to.equal(0));
    cy.contains('.modal-actions button', 'Change').should('not.be.disabled');
  });

  /** The status list never arrived: saying so beats a button that does nothing. */
  it('says so rather than recording nothing when there are no statuses', () => {
    mountDialog({ statuses: [], currentStatus: '' });
    submit();

    cy.get('.modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.status.update.noStatuses');
    cy.then(() => {
      expect(submitted).to.deep.equal([]);
      expect(closed).to.equal(0);
    });
  });

  it('closes without recording anything when cancelled', () => {
    mountDialog();
    cy.contains('.modal-actions button', 'common.cancel').click();
    cy.then(() => {
      expect(submitted).to.deep.equal([]);
      expect(closed).to.equal(1);
    });
  });
});
