/**
 * The *Invitation to codecheck* email (#13): inviting a codechecker through
 * "Add Reviewer" with the plugin's template sends its subject and text, with
 * OJS's variables filled in.
 *
 * ccodechecker is invited to submission 8's current review round, which has
 * no reviewer, and unassigned afterwards: an invitation not yet answered is
 * deleted. What stays is what OJS logs and stamps: the event log and email log
 * entries, the editor's notifications and submission 8's last-modified date.
 */

import '../../support/mail.js';

const SUBMISSION = 8;
const REVIEW_STAGE = 3;
const CCODECHECKER = { id: 8, name: 'Cora Codechecker', email: 'ccodechecker@mailinator.com' };

/** The current review round and the codechecker's live assignment in it, if any. */
const readReview = () =>
  cy.ojsApi('GET', `api/v1/submissions/${SUBMISSION}`).its('body').then((submission) => {
    const rounds = submission.reviewRounds.filter((round) => round.stageId === REVIEW_STAGE);
    const reviewRoundId = Math.max(...rounds.map((round) => round.id));
    return {
      reviewRoundId,
      assignment: submission.reviewAssignments.find((review) =>
        review.reviewerId === CCODECHECKER.id && review.roundId === reviewRoundId && !review.dateCancelled),
    };
  });

describe('Email: invitation to codecheck', () => {
  after(() => {
    cy.openBackend();
    readReview().then(({ reviewRoundId, assignment }) => {
      if (assignment) {
        cy.unassignReviewer({
          submissionId: SUBMISSION, stageId: REVIEW_STAGE, reviewRoundId, reviewAssignmentId: assignment.id,
        });
      }
    });
  });

  it("is sent with the template's subject and text", () => {
    cy.openBackend();
    readReview().then(({ reviewRoundId, assignment }) => {
      expect(assignment, 'ccodechecker does not review submission 8 in the dataset').to.eq(undefined);

      cy.invitationTemplates().then((templates) => {
        expect(templates, 'the journal has the plugin template').to.have.length(1);
        const [template] = templates;
        cy.clearMail();

        cy.inviteReviewer({
          submissionId: SUBMISSION, stageId: REVIEW_STAGE, reviewRoundId, reviewerId: CCODECHECKER.id, template: template.key,
        });

        cy.mailTo(CCODECHECKER.email, template.subject.en).then((messages) => {
          expect(messages, 'one email').to.have.length(1);
          const [message] = messages;
          expect(message.HTML).to.contain(`Dear ${CCODECHECKER.name},`);
          expect(message.HTML).to.contain('A codecheck confirms');
          expect(message.HTML, 'every variable filled in').not.to.contain('{$');
        });
      });
    });
  });
});
