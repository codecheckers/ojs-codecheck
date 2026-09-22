import '../../support/pkp-mock.js';
import CodecheckRepositoryList from '../../../resources/js/Components/CodecheckRepositoryList.vue';

describe('CodecheckRepositoryList Component', () => {
  it('mounts and displays initial repository input', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        description: 'Enter repository URL',
        value: ''
      }
    });
    
    cy.get('.repository-row').should('have.length', 1);
    cy.get('input[type="url"]').should('have.value', 'https://');
  });

  it('loads existing repositories from value prop', () => {
    const existingRepos = 'https://github.com/user/repo1\nhttps://gitlab.com/user/repo2';
    
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: existingRepos
      }
    });
    
    cy.get('.repository-row').should('have.length', 2);
    cy.get('input[type="url"]').eq(0).should('have.value', 'https://github.com/user/repo1');
    cy.get('input[type="url"]').eq(1).should('have.value', 'https://gitlab.com/user/repo2');
  });

  it('allows adding new repository', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    cy.get('.btn-add').click();
    
    cy.get('.repository-row').should('have.length', 2);
  });

  it('allows removing repository', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: 'https://github.com/test/repo'
      }
    });
    
    cy.get('.repository-row').should('have.length', 1);
    cy.get('.btn-remove').click();
    cy.get('.repository-row').should('have.length', 0);
  });

  it('accepts valid repository URLs', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    // Type a valid URL
    cy.get('input[type="url"]').clear().type('https://github.com/test/repo');
    cy.get('input[type="url"]').should('have.value', 'https://github.com/test/repo');
    
    // No errors should appear
    cy.get('.pkpFormField__error').should('not.exist');
  });

  /**
   * Issue #170. The field writes into a hidden textarea the wizard submits.
   *
   * It submits everything the author typed, invalid addresses included, and the
   * server refuses the save with the message keyed to the field — the way PKP's
   * own fields work, because `Repository::edit()` reads a submitted list as
   * complete and an absent entry as a deletion. Withholding a row here was
   * indistinguishable from the author deleting it, and took the address already
   * on file with it. What the field owes the author is the message, not a
   * quietly shortened payload.
   */
  describe('what reaches the submitted value', () => {
    // The component looks up `textarea[name=…]` and dispatches a bubbling
    // `update` event on the element before it. Reproduce that shape once and
    // listen on the document, which the event reaches by bubbling.
    let submitted;
    let listener;

    beforeEach(() => {
      cy.mount(CodecheckRepositoryList, {
        props: {name: 'repositories', label: 'Repositories', value: ''},
      });

      submitted = {value: null};
      listener = (event) => {
        submitted.value = event.detail;
      };

      cy.document().then((doc) => {
        const host = doc.createElement('div');
        const textarea = doc.createElement('textarea');
        textarea.setAttribute('name', 'repositories');

        doc.body.appendChild(host);
        doc.body.appendChild(textarea);

        doc.addEventListener('update', listener);
      });
    });

    // Mount cleanup only clears [data-cy-root], so without this the host and
    // textarea of every test survive into the next one — and the component
    // takes the *first* textarea it finds, which would be an earlier test's.
    afterEach(() => {
      cy.document().then((doc) => {
        doc.removeEventListener('update', listener);
        doc.querySelectorAll('textarea[name="repositories"]').forEach((textarea) => {
          textarea.previousElementSibling?.remove();
          textarea.remove();
        });
      });
    });

    /**
     * What was submitted, asserting first that anything was — otherwise a
     * broken fixture would make every "does not contain" below pass by
     * capturing nothing at all.
     */
    const submittedValue = () => {
      expect(submitted.value, 'the field submitted something').to.be.a('string');

      return submitted.value;
    };

    it('flags an address the rule refuses as soon as it is loaded', () => {
      // The field is remounted here with a stored value rather than typed into.
      cy.mount(CodecheckRepositoryList, {
        props: {name: 'repositories', label: 'Repositories', value: 'github.com/me/project'},
      });

      cy.get('.pkpFormField__error')
        .should('contain', 'plugins.generic.codecheck.repository.validation.invalid');
    });

    it('flags an address with no scheme and still submits it', () => {
      // Blurred, because the message is raised when the row is left — an
      // author typing `h`, `ht`, `htt` is not told each time that it is not a
      // URL yet.
      cy.get('input[type="url"]').clear().type('github.com/me/project').blur();

      // No scheme at all, so it does not parse as a URL: that is the message.
      cy.get('.pkpFormField__error')
        .should('contain', 'plugins.generic.codecheck.repository.validation.invalid');
      cy.get('input[type="url"]').should('have.value', 'github.com/me/project');
      // Submitted, so the server refuses the save rather than the field
      // silently shortening the list — which merge() reads as a deletion.
      cy.then(() => {
        expect(submittedValue()).to.contain('github.com/me/project');
      });
    });

    it('flags an address that is not a URL at all', () => {
      cy.get('input[type="url"]').clear().type('git@github.com:foo/bar.git').blur();

      cy.get('.pkpFormField__error').should('exist');
      cy.then(() => {
        expect(submittedValue()).to.contain('git@github.com');
      });
    });

    /**
     * The scheme case, and the one with teeth: `javascript:` parses perfectly
     * well as a URL, so only the scheme rule refuses it. It reaches the public
     * article page, register.csv and a public GitHub issue (issue #154) —
     * which is why the server, not the field, is what stops it being stored.
     */
    it('flags an address whose scheme is not http or https', () => {
      cy.get('input[type="url"]').clear().type('javascript://example.org/%0Aalert(1)').blur();

      cy.get('.pkpFormField__error')
        .should('contain', 'plugins.generic.codecheck.repository.validation.protocol');
    });

    /**
     * The disagreement that made this worth fixing: the field refused
     * `HTTP://`, `Constants::isWebUrl()` accepted it, so the author was told
     * their address was wrong and the server stored it anyway.
     */
    it('accepts an upper-case scheme, as the server does', () => {
      cy.get('input[type="url"]').clear().type('HTTP://example.org/repo').blur();

      cy.get('.pkpFormField__error').should('not.exist');
      cy.then(() => {
        expect(submittedValue()).to.contain('HTTP://example.org/repo');
      });
    });

    it('puts each message with the row that caused it', () => {
      cy.get('input[type="url"]').clear().type('https://github.com/user/good');
      cy.get('.btn-add').click();
      cy.get('input[type="url"]').eq(1).clear().type('github.com/user/bad').blur();

      // The message sits with the row that caused it, not in a block below all
      // of them where two identical messages cannot be told apart.
      cy.get('.repository-row-group').eq(0).find('.pkpFormField__error').should('not.exist');
      cy.get('.repository-row-group').eq(1).find('.pkpFormField__error').should('exist');

      cy.then(() => {
        expect(submittedValue()).to.contain('https://github.com/user/good');
      });
    });

    /** A row the author has not filled in is not an address and is not sent. */
    it('leaves an untouched row out of the submitted value', () => {
      cy.get('input[type="url"]').clear().type('https://github.com/user/good');
      cy.get('.btn-add').click();

      cy.then(() => {
        expect(submittedValue()).to.equal('https://github.com/user/good');
      });
    });
  });

  it('allows multiple repositories', () => {
    cy.mount(CodecheckRepositoryList, {
      props: {
        name: 'codeRepository',
        label: 'Code Repository',
        value: ''
      }
    });
    
    // Add and fill first repo
    cy.get('input[type="url"]').clear().type('https://github.com/user/repo1');
    
    // Add second repo
    cy.get('.btn-add').click();
    cy.get('input[type="url"]').eq(1).clear().type('https://gitlab.com/user/repo2');
    
    // Verify both exist
    cy.get('.repository-row').should('have.length', 2);
    cy.get('input[type="url"]').eq(0).should('have.value', 'https://github.com/user/repo1');
    cy.get('input[type="url"]').eq(1).should('have.value', 'https://gitlab.com/user/repo2');
  });
});