/**
 * The email *CODECHECK: codechecker needed* (#31): an editor assigned to a
 * submission that takes part in a CODECHECK and has no codechecker yet is told
 * so; one assigned when a codechecker is recorded is not.
 *
 * Submission 9 has no editor, and its one codechecker is linked to
 * ccodechecker's review, so the list can be emptied and put back. The editors
 * assigned here are removed in after(). A status past *needs codechecker*
 * (left by e2e specs on the same instance) is set back to it for the spec and
 * restored.
 */

import '../../support/mail.js';

const SUBMISSION = 9;
const REVIEW_STAGE = 3;
const JOURNAL_EDITOR_GROUP = 3;
const SECTION_EDITOR_GROUP = 5;
const JMANAGER = { id: 5, email: 'jmanager@mailinator.com' };
const SECTIONEDITOR = { id: 7, email: 'sectioneditor@mailinator.com' };

const SUBJECT = 'A codechecker is needed';
const PENDING = 'plugins.generic.codecheck.status.pending';
const NEEDS_CODECHECKER = 'plugins.generic.codecheck.status.needsCodechecker';

const api = (path) => cy.ojsApi('GET', `api/v1/codecheck/${path}?submissionId=${SUBMISSION}`);
const saveCodecheckers = (record, codecheckers) =>
  cy.saveCodecheckRecord(SUBMISSION, record, codecheckers).its('status').should('eq', 200);
const assignEditor = (user, userGroupId) =>
  cy.assignParticipant(SUBMISSION, REVIEW_STAGE, userGroupId, user.id);

describe('Email: codechecker needed', () => {
  let stored = null;
  let statusToRestore = null;

  before(() => {
    cy.openBackend();
    api('metadata').its('body.codecheck').then((record) => {
      expect(record.codecheckers, 'the dataset links one codechecker to submission 9').to.have.length(1);
      stored = record;
    });
    api('status').its('body.statusRecord.status').then((status) => {
      if (status !== PENDING && status !== NEEDS_CODECHECKER) {
        statusToRestore = status;
        cy.recordCodecheckStatus(SUBMISSION, NEEDS_CODECHECKER);
      }
    });
  });

  beforeEach(() => cy.openBackend());

  after(() => {
    cy.openBackend();
    cy.removeParticipants(SUBMISSION, REVIEW_STAGE, [JOURNAL_EDITOR_GROUP, SECTION_EDITOR_GROUP]);
    if (stored) {
      saveCodecheckers(stored, stored.codecheckers);
    }
    if (statusToRestore) {
      cy.recordCodecheckStatus(SUBMISSION, statusToRestore);
    }
  });

  it('is sent to an editor assigned while no codechecker is recorded', () => {
    saveCodecheckers(stored, []);
    cy.clearMail();

    assignEditor(JMANAGER, JOURNAL_EDITOR_GROUP);

    cy.mailTo(JMANAGER.email, SUBJECT).then((messages) => {
      expect(messages, 'one email').to.have.length(1);
      const [message] = messages;
      expect(message.Text).to.contain('Add Reviewer');
      expect(message.HTML).to.contain(`workflowSubmissionId=${SUBMISSION}"`);
    });
  });

  it('is not sent once a codechecker is recorded', () => {
    saveCodecheckers(stored, stored.codecheckers);
    cy.clearMail();

    assignEditor(SECTIONEDITOR, SECTION_EDITOR_GROUP);

    cy.mailTo(SECTIONEDITOR.email, SUBJECT).should('have.length', 0);

    // The control: the same assignment without a codechecker sends it, so the
    // silence above is the codechecker's doing, not a mail that cannot go out.
    cy.removeParticipants(SUBMISSION, REVIEW_STAGE, [SECTION_EDITOR_GROUP]);
    saveCodecheckers(stored, []);
    assignEditor(SECTIONEDITOR, SECTION_EDITOR_GROUP);
    cy.mailTo(SECTIONEDITOR.email, SUBJECT).should('have.length', 1);
  });
});
