import '../../support/pkp-mock.js';
import { h } from 'vue';
import CodecheckOrcidSection from '../../../resources/js/Components/CodecheckOrcidSection.vue';

/**
 * An ORCID account can be connected only for a codechecker whose iD is on
 * record (GHSA-4p3r-qgp4-g74r), so the panel offers the button for those alone
 * and tells the codechecker what to do about the others.
 */
const PkpButton = {
  name: 'PkpButton',
  emits: ['click'],
  render() {
    return h('button', { class: 'pkp-button-stub', onClick: () => this.$emit('click') }, this.$slots.default?.());
  },
};

const mountPanel = (codecheckers, { depositScope = 'none', canAuthorise = true } = {}) => {
  cy.intercept('GET', '**/codecheck/orcid-status*', {
    statusCode: 200,
    body: { success: true, submissionId: 1, codecheckers, journalConfigError: null, depositScope },
  }).as('status');

  cy.mount(CodecheckOrcidSection, {
    props: { submission: { id: 1 }, orcidEnabled: true, orcidAuthUrl: '/startAuth', canAuthorise },
    global: { components: { 'pkp-button': PkpButton } },
  });
  cy.wait('@status');
};

describe('CodecheckOrcidSection', () => {
  it('offers to connect an account only for a codechecker with an iD on record', () => {
    mountPanel([
      { name: 'With an iD', hasOrcid: true, orcidId: null, depositStatus: null },
      { name: 'Name only', hasOrcid: false, orcidId: null, depositStatus: null },
    ]);

    cy.contains('.codecheck-orcid-row', 'With an iD')
      .find('.pkp-button-stub').should('contain', 'plugins.generic.codecheck.orcid.authorise');

    cy.contains('.codecheck-orcid-row', 'Name only').within(() => {
      cy.get('.pkp-button-stub').should('not.exist');
      cy.contains('plugins.generic.codecheck.orcid.noOrcidRecorded').should('be.visible');
    });
  });

  // What the server answers about the user decides which deposits are offered;
  // the endpoint refuses the others whatever the panel shows (#127).
  describe('deposit buttons', () => {
    const connected = [
      { name: 'Mine', hasOrcid: true, orcidId: '0000-0002-1825-0097', depositStatus: null, isCurrentUser: true },
      { name: 'Theirs', hasOrcid: true, orcidId: '0000-0001-5109-3700', depositStatus: null, isCurrentUser: false },
    ];

    it('offers an editor every deposit and "deposit all"', () => {
      mountPanel(connected, { depositScope: 'all', canAuthorise: false });

      cy.contains('.codecheck-orcid-row', 'Mine').contains('plugins.generic.codecheck.orcid.deposit');
      cy.contains('.codecheck-orcid-row', 'Theirs').contains('plugins.generic.codecheck.orcid.deposit');
      cy.contains('plugins.generic.codecheck.orcid.depositAll').should('exist');
    });

    it('offers a reviewer their own row alone', () => {
      mountPanel(connected, { depositScope: 'own' });

      cy.contains('.codecheck-orcid-row', 'Mine').contains('plugins.generic.codecheck.orcid.deposit');
      cy.contains('.codecheck-orcid-row', 'Theirs').contains('plugins.generic.codecheck.orcid.deposit').should('not.exist');
      cy.contains('plugins.generic.codecheck.orcid.depositAll').should('not.exist');
    });

    it('offers no deposit when the server allows none', () => {
      mountPanel(connected, { depositScope: 'none', canAuthorise: false });

      cy.get('.codecheck-orcid-row').should('have.length', 2);
      cy.contains('plugins.generic.codecheck.orcid.deposit').should('not.exist');
      cy.contains('plugins.generic.codecheck.orcid.depositAll').should('not.exist');
    });
  });
});
