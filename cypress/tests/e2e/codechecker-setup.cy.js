/**
 * The Codechecker role and the "Invitation to codecheck" email template a
 * journal gets for inviting codecheckers as reviewers (#13), and the settings
 * section that shows whether each still exists and recreates a missing one.
 *
 * Whatever this deletes it recreates, so the journal ends with both, as the
 * dataset has them. The section sits far down a scrolling modal, where Cypress
 * counts an element as hidden, so what is shown is read off the `hidden`
 * attribute the page sets.
 */

const JOURNAL = 'codecheck';
const STATUS = '#codecheckerSetupStatus';
const RECREATE = '#codecheckerSetupRecreate';

const shown = (part, state) => `${STATUS} [data-part=${part}] .codecheck-setup-status__${state}`;

/** Yields the journal's alternates to "Review Request" named as the plugin names its own. */
const invitationTemplates = () =>
  cy.ojsApi('GET', 'api/v1/emailTemplates?alternateTo=REVIEW_REQUEST').then((response) => {
    expect(response.status, 'the email templates API').to.eq(200);
    return response.body.items.filter((template) =>
      Object.values(template.name ?? {}).includes('Invitation to codecheck'));
  });

/** Opens the settings and creates whatever is missing, so both exist. */
const ensureBoth = () => {
  cy.openCodecheckSettings();
  cy.get(RECREATE).then(($button) => {
    if (!$button.attr('hidden')) {
      cy.wrap($button).click({ force: true });
    }
  });
  cy.get(RECREATE).should('have.attr', 'hidden');
};

describe('The Codechecker role and invitation template', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  it('shows both as present, with nothing to recreate', () => {
    ensureBoth();
    ['role', 'template'].forEach((part) => {
      cy.get(shown(part, 'present')).should('not.have.attr', 'hidden');
      cy.get(shown(part, 'missing')).should('have.attr', 'hidden');
    });
  });

  it('adds the invitation as an alternate to "Review Request", carrying its variables', () => {
    ensureBoth();
    invitationTemplates().then((templates) => {
      expect(templates).to.have.length(1);
      const body = Object.values(templates[0].body)[0];
      expect(body).to.contain('{$reviewAssignmentUrl}');
      expect(body).to.contain('{$responseDueDate}');
      expect(body).not.to.contain('##');
    });
  });

  it('shows a deleted template as missing and recreates it', () => {
    ensureBoth();
    invitationTemplates().then((templates) => {
      cy.ojsApi('DELETE', `api/v1/emailTemplates/${templates[0].key}`).its('status').should('eq', 200);
    });

    cy.openCodecheckSettings();
    cy.get(shown('template', 'missing')).should('not.have.attr', 'hidden');
    cy.get(shown('role', 'present')).should('not.have.attr', 'hidden');
    cy.get(RECREATE).should('not.have.attr', 'hidden');
    cy.get(RECREATE).click({ force: true });

    cy.get(shown('template', 'present')).should('not.have.attr', 'hidden');
    cy.get(shown('template', 'missing')).should('have.attr', 'hidden');
    cy.get(RECREATE).should('have.attr', 'hidden');
    invitationTemplates().should('have.length', 1);
  });

  it('refuses a recreation without the CSRF token', () => {
    cy.request({
      method: 'POST',
      url: `/index.php/${JOURNAL}/$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage` +
        '?category=generic&plugin=codecheckplugin&verb=recreateCodecheckerSetup',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.body.status).to.eq(false);
    });
  });

});
