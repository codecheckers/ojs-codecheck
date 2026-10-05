/**
 * The GitHub username on the user's account (#13): on the public profile, in
 * the manager's user form, checked with the username rule and refused when
 * another account holds it.
 *
 * Every username set here is removed again, so the dataset's accounts end
 * without one, as they start.
 */

const JOURNAL = 'codecheck';

/** Opens the logged-in user's profile on its Public tab. */
const openPublicProfile = () => {
  cy.visit(`/index.php/${JOURNAL}/user/profile`);
  cy.get('a[name="publicProfile"], a[href*="publicProfile"]', { timeout: 20000 }).first().click();
  cy.get('input[name="githubUsername"]', { timeout: 20000 }).should('exist');
};

/** Saves the Public tab with this username, and waits until it is saved or refused. */
const savePublicProfile = (username) => {
  cy.intercept('POST', '**/profile-tab/save-public-profile*').as('savePublicProfile');
  cy.get('input[name="githubUsername"]').clear();
  if (username) {
    cy.get('input[name="githubUsername"]').type(username);
  }
  cy.get('input[name="githubUsername"]').closest('form').find('button[type="submit"]').click();
  cy.wait('@savePublicProfile');
};

const setOwnUsername = (login, username) => {
  cy.ojsLogin(login, login);
  openPublicProfile();
  savePublicProfile(username);
};

describe('The GitHub username on the profile', () => {
  after(() => {
    setOwnUsername('rreviewer', '');
    setOwnUsername('seglen', '');
  });

  it('is stored in the form it is meant, from the address of a profile', () => {
    setOwnUsername('rreviewer', 'https://github.com/rreviewer-codechecks-at-length');
    cy.get('.pkp_form_error, .error').should('not.exist');

    openPublicProfile();
    cy.get('input[name="githubUsername"]').should('have.value', 'rreviewer-codechecks-at-length');
  });

  it('refuses what is not a GitHub username', () => {
    setOwnUsername('rreviewer', 'not a username');
    cy.contains('This is not a GitHub username').should('be.visible');
  });

  it('refuses a username another account holds, whatever its capitals', () => {
    setOwnUsername('rreviewer', 'shared-codechecker');
    setOwnUsername('seglen', 'Shared-Codechecker');
    cy.contains('Another account already has this GitHub username.').should('be.visible');
  });

  it('can be removed', () => {
    setOwnUsername('rreviewer', 'rreviewer-codechecks');
    setOwnUsername('rreviewer', '');

    openPublicProfile();
    cy.get('input[name="githubUsername"]').should('have.value', '');
  });

  /**
   * The manager's user form (Users & Roles › Edit User), driven as its modal
   * does: the form is fetched, its fields are posted back with the username
   * changed. Opening the modal through the grid adds nothing the request
   * does not.
   */
  describe("in the manager's user form", () => {
    const USER_GRID = `/index.php/${JOURNAL}/$$$call$$$/grid/settings/user/user-grid`;
    const RREVIEWER = 6;

    const fetchForm = () =>
      cy.request(`${USER_GRID}/edit-user?rowId=${RREVIEWER}`).then((response) => {
        expect(response.body.status).to.eq(true);
        return Cypress.$('<div>').html(response.body.content).find('form').first();
      });

    const saveForm = (username) =>
      fetchForm().then(($form) => {
        const fields = {};
        $form.serializeArray().forEach(({ name, value }) => {
          fields[name] = value;
        });
        fields.githubUsername = username;
        return cy.request({ method: 'POST', url: $form.attr('action'), form: true, body: fields });
      });

    beforeEach(() => {
      cy.ojsLogin('admin', 'admin');
    });

    after(() => {
      cy.ojsLogin('admin', 'admin');
      saveForm('');
    });

    it('shows the username stored on the account and saves a new one', () => {
      setOwnUsername('rreviewer', 'rreviewer-codechecks');
      cy.ojsLogin('admin', 'admin');
      fetchForm().then(($form) => expect($form.find('input[name="githubUsername"]').val()).to.eq('rreviewer-codechecks'));

      saveForm('github.com/rreviewer-managed').its('status').should('eq', 200);
      fetchForm().then(($form) => expect($form.find('input[name="githubUsername"]').val()).to.eq('rreviewer-managed'));
    });
  });
});
