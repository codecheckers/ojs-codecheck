import '../../support/pkp-mock.js';
import CodecheckMetadataForm from '../../../resources/js/Components/CodecheckMetadataForm.vue';

/**
 * The metadata response the form loads from. Built fresh per call so a test
 * can adjust one field — the journal's enabled config versions, say — without
 * leaking the change into the next test.
 */
const metadataResponseBody = () => ({
  success: true,
  submissionId: 1,
  canManageIdentifier: true,
  submission: {
    id: 1,
    title: 'Test Article Title',
    authors: [
      { name: 'John Doe', orcid: '0000-0001-2345-6789' },
      { name: 'Jane Smith', orcid: '0000-0002-3456-7890' }
    ],
    contact: { name: 'Jane Smith', email: 'jane.smith@example.org' },
    authorsWithheld: false,
    doi: '10.1234/test.2024',
    dataAvailabilityStatement: 'Data is available at Zenodo'
  },
  codecheck: {
    version: '2.0',
    publicationType: 'doi',
    manifest: [],
    repository: { repositories: null },
    source: '',
    codecheckers: [],
    certificate: '',
    check_time: '',
    summary: '',
    report: '',
    additionalContent: '',
    issue: {
      url: "https://github.come/example/repo/issues/0",
      number: 0,
      labels: ["test-label-1", "test-label-2"],
      labelsSelected: ["test-label-2"]
    },
  },
});

/** Serve the metadata endpoint, optionally with fields overridden. */
const interceptMetadata = (overrides = {}, alias = 'loadMetadata') =>
  cy.intercept('GET', '**/codecheck/metadata*', {
    statusCode: 200,
    body: { ...metadataResponseBody(), ...overrides }
  }).as(alias);

/** Serve a successful reservation of 2025-042. */
const interceptReservation = (submissionId = 1) =>
  cy.intercept('POST', `**/codecheck/identifier?submissionId=${submissionId}`, {
    statusCode: 200,
    body: {
      success: true,
      identifier: '2025-042',
      issueUrl: 'https://github.com/codecheckers/register/issues/42',
      issueNumber: 42
    }
  }).as('reserveIdentifier');

/** One of the buttons beside the certificate identifier, by its label key. */
const identifierButton = (key) =>
  cy.get('.certificate-identifier-button').contains(`plugins.generic.codecheck.identifier.${key}`);

/** Answers the modal's yes/no question, which is OJS's own dialog, not the browser's. */
const answerModal = (answer) =>
  cy.get('.pkp-mock-modal__action')
    .contains(answer === 'yes' ? 'common.yes' : 'common.no')
    .click();

/** Mounts the editorial form for an editor who may change it. */
const mountForm = (submissionId = 1) =>
  cy.mount(CodecheckMetadataForm, { props: { submission: { id: submissionId }, canEdit: true } });

/** A dialog button by its label key. */
const modalButton = (key) => cy.get('.pkp-mock-modal__action').contains(key);

/**
 * A button a dialog *body* draws — the dialogs that ask for something own
 * theirs, because OJS disables its own after the first click (#180).
 */
const dialogSubmit = (key) => cy.contains('.pkp-mock-modal .modal-actions button', key);

/** Opens the "add codechecker" dialog. */
const addCodechecker = () =>
  cy.contains('.field-label', /codechecker/i).parent().find('.btn-add').click();

/**
 * Mounts the form and clicks "reserve automatically", which needs a label
 * selected first — the form refuses the request otherwise.
 */
const mountAndReserve = (submissionId = 1) => {
  cy.mount(CodecheckMetadataForm, {
    props: {
      submission: { id: submissionId },
      canEdit: true
    }
  });

  cy.wait('@loadMetadata');
  cy.wait('@loadLabelData');

  cy.get('.dropdown-content').invoke('show');
  cy.get('.dropdown-checkbox-input input[type="checkbox"]').first().check();

  identifierButton('reserve.withApi').click();
};

describe('CodecheckMetadataForm Component', () => {
  beforeEach(() => {
    interceptMetadata();

    cy.intercept('GET', '**/codecheck/labels*', {
      statusCode: 200,
      body: {
        success: true,
        labels: ['test-label-1', 'test-label-2'],
        message: 'Labels fetched successfully'
      }
    }).as('loadLabelData');
  });

  it('renders loading state initially', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.get('.loading-state').should('exist');
  });

  describe('the opt-in warning gives the reason every tab gives (#34)', () => {
    [
      { optIn: true, mode: 'opt-in', reason: null },
      { optIn: false, mode: 'opt-in', reason: 'plugins.generic.codecheck.warning.notOptedIn' },
      { optIn: false, mode: 'opt-out', reason: 'plugins.generic.codecheck.warning.optedOut' },
      { optIn: null, mode: 'opt-out', reason: 'plugins.generic.codecheck.warning.noChoice' },
    ].forEach(({ optIn, mode, reason }) => {
      it(`${String(optIn)} in an ${mode} journal`, () => {
        cy.mount(CodecheckMetadataForm, {
          props: { submission: { id: 1, codecheckOptIn: optIn }, canEdit: true, codecheckMode: mode },
        });
        cy.wait('@loadMetadata');

        if (reason) {
          cy.get('.codecheck-optin-warning').should('contain', reason);
        } else {
          cy.get('.codecheck-header').should('exist');
          cy.get('.codecheck-optin-warning').should('not.exist');
        }
      });
    });
  });

  it('loads and displays submission metadata correctly', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
        
    // Check paper metadata section
    cy.contains('Test Article Title').should('exist');
    cy.contains('John Doe').should('exist');
    cy.contains('0000-0001-2345-6789').should('exist');
    cy.contains('Jane Smith').should('exist');
    cy.contains('10.1234/test.2024').should('exist');
  });

  it("shows the author's availability statement in the paper metadata panel", () => {
    // The codechecker has to be able to read what the author wrote about where
    // the materials are; the statement lives on the publication and arrives in
    // the metadata response's submission block.
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.get('.read-only-section .availability-statement')
      .should('contain', 'Data is available at Zenodo');
  });

  it('says so when the author provided no availability statement', () => {
    interceptMetadata({
      submission: {
        id: 1,
        title: 'Test Article Title',
        authors: [],
        doi: '10.1234/test.2024',
        dataAvailabilityStatement: ''
      }
    }, 'loadWithoutStatement');

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadWithoutStatement');

    cy.get('.read-only-section .availability-statement').should('not.exist');
    cy.get('.read-only-section')
      .should('contain', 'plugins.generic.codecheck.paperMetadata.noAvailabilityStatement');
  });

  /** The submission part of the metadata response, with fields overridden. */
  const submissionWith = (fields) => ({ submission: { ...metadataResponseBody().submission, ...fields } });

  it('names the contact author and links their address, with the paper in the subject (#28)', () => {
    interceptMetadata();
    mountForm();
    cy.wait('@loadMetadata');

    cy.get('.codecheck-contact').should('contain', 'Jane Smith');
    cy.get('.codecheck-contact-email')
      .should('have.text', 'jane.smith@example.org')
      .invoke('attr', 'href')
      .then((href) => {
        expect(href.startsWith('mailto:jane.smith@example.org?subject=')).to.equal(true);
        expect(decodeURIComponent(href.split('?subject=')[1]))
          .to.equal('Question about the CODECHECK of "Test Article Title"');
      });
  });

  it('keeps a title with $ patterns intact in the mail subject', () => {
    // OJS's t() substitutes with String.replace, so `$&` and `$'` in a title would
    // otherwise be read as patterns; the mock substitutes the same way.
    const title = "Cost of $5, $& and $' in R";
    interceptMetadata(submissionWith({ title }));
    mountForm();
    cy.wait('@loadMetadata');

    cy.get('.codecheck-contact-email').invoke('attr', 'href').then((href) => {
      expect(decodeURIComponent(href.split('?subject=')[1]))
        .to.equal(`Question about the CODECHECK of "${title}"`);
    });
  });

  it('keeps an address from adding headers of its own to the mail', () => {
    interceptMetadata(submissionWith({ contact: { name: 'Mallory', email: 'm@example.org?bcc=x@example.org' } }));
    mountForm();
    cy.wait('@loadMetadata');

    cy.get('.codecheck-contact-email').invoke('attr', 'href').then((href) => {
      expect(href).to.contain('m@example.org%3Fbcc%3Dx@example.org?subject=');
      expect(href.match(/\?/g)).to.have.length(1);
    });
  });

  it('says so when the submission names no contact author', () => {
    interceptMetadata(submissionWith({ contact: null }));
    mountForm();
    cy.wait('@loadMetadata');

    cy.get('.codecheck-contact-email').should('not.exist');
    cy.get('.codecheck-contact').should('contain', 'plugins.generic.codecheck.paperMetadata.noContact');
  });

  it('says in the YAML preview that the authors are left out for a withheld viewer', () => {
    interceptMetadata(submissionWith({ authors: [], contact: null, authorsWithheld: true }));
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } })
      .then(({ wrapper }) => {
        cy.wait('@loadMetadata');
        // The response is in before the form has applied it, and the preview
        // reads what the form applied: wait for the form to say so first.
        cy.get('.read-only-section')
          .should('contain', 'plugins.generic.codecheck.paperMetadata.authorsWithheld')
          .then(() => wrapper.vm.showYamlModal('paper:\n  title: x\n'));
      });

    cy.get('.pkp-mock-modal .yaml-withheld-notice')
      .should('contain', 'plugins.generic.codecheck.yaml.authorsWithheld');
  });

  it('shows no such notice to a viewer who sees the authors', () => {
    interceptMetadata();
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } })
      .then(({ wrapper }) => {
        cy.wait('@loadMetadata');
        // As above, or the notice is absent only because nothing was applied yet.
        cy.get('.author-item').should('exist').then(() => wrapper.vm.showYamlModal('paper:\n  title: x\n'));
      });

    cy.get('.pkp-mock-modal .yaml-preview-content').should('exist');
    cy.get('.yaml-withheld-notice').should('not.exist');
  });

  /** The yml a repository import answers with: other paper data and an identifier. */
  const importedYml = () => ({
    success: true,
    metadata: {
      version: 'https://codecheck.org.uk/spec/config/1.0/',
      paper: { title: 'A different title', authors: [{ name: 'Someone Else' }], doi: '10.9999/other' },
      summary: 'Imported summary',
      certificate: '2030-001',
    },
  });

  /** Import from the first repository, once the form has loaded. */
  const importFirstRepository = (certificateInStore) =>
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } })
      .then(({ wrapper }) => {
        cy.wait('@loadMetadata');
        // The response is in before the form has applied it, and the linked issue
        // is part of what it applies.
        cy.get('.codecheck-contact-email').should('exist').then(() => {
          wrapper.vm.repositories = [{ url: 'https://github.com/a/b', hidden: false, containsCodecheckYaml: true }];
          if (certificateInStore !== undefined) {
            // Replaced as a whole: a nested write through the wrapper does not reach
            // the form's computed properties.
            wrapper.vm.metadata = { ...wrapper.vm.metadata, certificate: certificateInStore };
          }
          return wrapper.vm.loadMetadataFromRepository(0).then(() => wrapper);
        });
      });

  it('imports for this submission, and leaves the paper data the form shows read-only alone (#28)', () => {
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', { statusCode: 200, body: importedYml() })
      .as('importRepository');
    interceptMetadata();
    importFirstRepository().then((wrapper) => {
      cy.wait('@importRepository');
      cy.wrap(wrapper.vm).should((vm) => {
        expect(vm.metadata.summary, 'what the form edits is imported').to.equal('Imported summary');
        expect(vm.submissionData.title).to.equal('Test Article Title');
        expect(vm.submissionData.authors.map((a) => a.name)).to.deep.equal(['John Doe', 'Jane Smith']);
        expect(vm.submissionData.doi).to.equal('10.1234/test.2024');
      });
    });
  });

  it('takes the certificate identifier from the file while the field can still be edited', () => {
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', { statusCode: 200, body: importedYml() });
    interceptMetadata();
    importFirstRepository('').then((wrapper) => {
      cy.wrap(wrapper.vm).its('metadata.certificate').should('equal', '2030-001');
    });
  });

  it('keeps an identifier that is linked to its register issue', () => {
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', { statusCode: 200, body: importedYml() });
    interceptMetadata();
    // metadataResponseBody() links the issue; the identifier below is the one reserved for it.
    importFirstRepository('2025-042').then((wrapper) => {
      cy.wrap(wrapper.vm).its('metadata.certificate').should('equal', '2025-042');
    });
  });

  /**
   * The file spells the iD `ORCID` and the form `orcid`, and an imported iD
   * used to be dropped on save; one that is not an iD still is, or every
   * later save would fail. The file carries no GitHub username, so one already
   * on the form for the same iD, or the same name, survives the import (#186).
   */
  it("takes the file's codecheckers in the form's shape, keeping a known username", () => {
    const yml = importedYml();
    yml.metadata.codechecker = [
      { name: 'Josiah Carberry', ORCID: 'https://orcid.org/0000-0002-1825-0097' },
      { name: 'Someone New', ORCID: '0000-0001-5109-3700' },
      { name: 'Daniel', ORCID: 'NA' },
    ];
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', { statusCode: 200, body: yml });
    interceptMetadata();
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } })
      .then(({ wrapper }) => {
        cy.wait('@loadMetadata');
        cy.get('.codecheck-contact-email').should('exist').then(() => {
          wrapper.vm.repositories = [{ url: 'https://github.com/a/b', hidden: false, containsCodecheckYaml: true }];
          wrapper.vm.metadata = {
            ...wrapper.vm.metadata,
            codecheckers: [
              { name: 'J. Carberry', orcid: '0000-0002-1825-0097', github: 'jcarberry' },
              { name: 'Daniel', orcid: '', github: 'nuest' },
            ],
          };
          return wrapper.vm.loadMetadataFromRepository(0).then(() => wrapper);
        });
      })
      .then((wrapper) => {
        cy.wrap(wrapper.vm).its('metadata.codecheckers').should('deep.equal', [
          { name: 'Josiah Carberry', orcid: '0000-0002-1825-0097', github: 'jcarberry' },
          { name: 'Someone New', orcid: '0000-0001-5109-3700', github: '' },
          { name: 'Daniel', orcid: '', github: 'nuest' },
        ]);
      });
  });

  /**
   * Who the record names as codecheckers decides whose ORCID account may be
   * credited, so a reviewer's copy of the form cannot change the list — the
   * server refuses such a save (GHSA-4p3r-qgp4-g74r) — and an import keeps it.
   */
  it('lets a reviewer neither change nor import the codecheckers', () => {
    const yml = importedYml();
    yml.metadata.codechecker = [{ name: 'Someone New', ORCID: '0000-0001-5109-3700' }];
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', { statusCode: 200, body: yml });
    interceptMetadata({
      codecheck: {
        ...metadataResponseBody().codecheck,
        codecheckers: [{ name: 'Recorded', orcid: '0000-0002-1825-0097', github: '' }],
      },
    });
    cy.mount(CodecheckMetadataForm, {
      props: { submission: { id: 1 }, canEdit: true, canEditCodecheckers: false },
    }).then(({ wrapper }) => {
      cy.wait('@loadMetadata');
      cy.get('.codecheckers-list').closest('.field-group').find('.btn-add').should('not.exist');
      cy.get('.codecheckers-list .pkpButton--close').should('not.exist');
      cy.contains('plugins.generic.codecheck.codecheckers.editorsOnly').should('be.visible');

      cy.get('.codecheck-contact-email').should('exist').then(() => {
        wrapper.vm.repositories = [{ url: 'https://github.com/a/b', hidden: false, containsCodecheckYaml: true }];
        return wrapper.vm.loadMetadataFromRepository(0);
      });
      cy.wrap(wrapper.vm).its('metadata.summary').should('equal', yml.metadata.summary);
      cy.wrap(wrapper.vm).its('metadata.codecheckers').should('deep.equal', [
        { name: 'Recorded', orcid: '0000-0002-1825-0097', github: '' },
      ]);
    });
  });

  /**
   * Reserving, linking and removing the identifier are a journal manager's;
   * anyone else sees it read-only, and the form neither asks for the venue
   * labels nor writes the register issue on save (#65).
   */
  it('shows the identifier read-only to someone who may not manage it', () => {
    const requested = [];
    cy.intercept('GET', '**/codecheck/labels*', (req) => {
      requested.push('labels');
      req.reply({ statusCode: 401, body: {} });
    });
    cy.intercept('POST', '**/codecheck/issue*', (req) => {
      requested.push('issue');
      req.reply({ statusCode: 401, body: {} });
    });
    cy.intercept('POST', '**/codecheck/metadata*', { statusCode: 200, body: { success: true } }).as('save');
    interceptMetadata({
      canManageIdentifier: false,
      // No register issue, so the field is not read-only because it is linked:
      // only the missing permission can make it so.
      codecheck: {
        ...metadataResponseBody().codecheck,
        certificate: '2025-042',
        issue: { url: '', number: null, labels: [], labelsSelected: [] },
      },
    });
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } });
    cy.wait('@loadMetadata');

    cy.get('.certificate-identifier-input').should('have.value', '2025-042').and('have.attr', 'readonly');
    cy.get('.certificate-identifier-button').should('not.exist');
    cy.get('.certificate-identifier-select').should('not.exist');
    cy.contains('plugins.generic.codecheck.identifier.managersOnly').should('be.visible');

    cy.get('.footer-actions button').contains(/save/i).click();
    cy.wait('@save');
    // The issue update would follow the save's answer, before the message.
    cy.get('.save-message').should('exist').then(() => {
      expect(requested).to.deep.equal([]);
    });
  });

  /** Once an identifier is reserved the labels cannot be chosen, so a missing venue list is not worth a warning (#65). */
  it('does not warn about the venue list once an identifier is reserved', () => {
    cy.intercept('GET', '**/codecheck/labels*', {
      statusCode: 200,
      body: { success: true, labels: [], labelsWarning: 'The venue list could not be read.' },
    }).as('loadLabelsWithWarning');
    interceptMetadata({ codecheck: { ...metadataResponseBody().codecheck, certificate: '2025-042' } });
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } });
    cy.wait('@loadLabelsWithWarning');
    cy.get('.certificate-identifier-input').should('have.value', '2025-042');
    cy.get('.save-message').should('not.exist');
  });

  /** Without the venue list the form still offers the journal's own labels, and says why the rest are missing (#65). */
  it('offers the labels it got and warns when the venue list could not be read', () => {
    cy.intercept('GET', '**/codecheck/labels*', {
      statusCode: 200,
      body: { success: true, labels: ['journal-own'], labelsWarning: 'The venue list could not be read.' },
    }).as('loadLabelsWithWarning');
    cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } }).then(({ wrapper }) => {
      cy.wait('@loadLabelsWithWarning');
      cy.get('.save-message.warning').should('contain', 'The venue list could not be read.');
      cy.wrap(wrapper.vm).its('certificateIdentifier.issue.labels').should('deep.equal', ['journal-own']);
    });
  });

  it('shows why a file for another paper was refused, and imports nothing', () => {
    cy.intercept('POST', '**/codecheck/repository?submissionId=1*', {
      statusCode: 422,
      body: { success: false, error: 'The paper title does not match.', repository: 'https://github.com/a/b' },
    });
    interceptMetadata();
    importFirstRepository().then((wrapper) => {
      cy.wrap(wrapper.vm).should((vm) => {
        expect(vm.repositoryWarning.message).to.contain('The paper title does not match.');
        expect(vm.metadata.summary).to.not.equal('Imported summary');
      });
    });
  });

  it('tells a codechecker on an anonymous assignment to go through the editor', () => {
    interceptMetadata(submissionWith({ authors: [], contact: null, authorsWithheld: true }));
    mountForm();
    cy.wait('@loadMetadata');

    cy.get('.author-item').should('not.exist');
    cy.get('.read-only-section')
      .should('contain', 'plugins.generic.codecheck.paperMetadata.authorsWithheld')
      .and('not.contain', 'plugins.generic.codecheck.paperMetadata.noAuthors');
    cy.get('.codecheck-contact').should('contain', 'plugins.generic.codecheck.paperMetadata.contactWithheld');
  });

  it('renders the specification link into the introduction', () => {
    // The introduction is one message with a {$specLink} parameter rather than
    // a sentence assembled from several keys, so that word order and
    // punctuation stay with the translator. The mock t() substitutes the
    // parameter and throws if the message and the call disagree about it.
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/2.0/')
      .and('have.attr', 'target', '_blank');
    cy.get('.codecheck-intro').should('not.contain', '{$specLink}');
  });

  // The plugin knows one version, 2.0. The tests below that need a choice
  // offer a future '2.1' through the journal's setting, which is how a second
  // version would reach the form.
  it('points the specification link at the selected config version', () => {
    interceptMetadata(
      { settings: { enabledConfigVersions: ['2.1', '2.0'] } },
      'loadTwoVersions'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadTwoVersions');

    cy.get('.version-select').select('2.1');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/2.1/');

    cy.get('.version-select').select('2.0');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/2.0/');
  });

  it('falls back to the current stable specification when the journal has not chosen', () => {
    // No settings block in the response and no version on the record: the form
    // lands on the plugin's default.
    interceptMetadata(
      { codecheck: { ...metadataResponseBody().codecheck, version: '' } },
      'loadWithoutVersion'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadWithoutVersion');

    cy.get('.version-select').should('have.value', '2.0');
    cy.get('.version-select option').should('have.length', 1);
    cy.get('.version-select').should('be.disabled');
    cy.get('.codecheck-intro a')
      .should('have.attr', 'href', 'https://codecheck.org.uk/spec/config/2.0/');
  });

  it('offers only the config versions the journal enabled', () => {
    interceptMetadata(
      { settings: { enabledConfigVersions: ['2.1'] } },
      'loadRestrictedMetadata'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadRestrictedMetadata');

    // '2.0' is stored on this record, so it stays selectable rather than
    // being silently rewritten — which also means the control is not disabled.
    cy.get('.version-select option').should('have.length', 2);
    cy.get('.version-select').should('not.be.disabled');
  });

  it('keeps a superseded version selectable after switching away from it', () => {
    // The record is on '2.0', which the journal no longer offers. Selecting
    // 2.1 must not remove '2.0' from the list, or the codechecker could
    // leave the recorded version but never return to it.
    interceptMetadata(
      { settings: { enabledConfigVersions: ['2.1'] } },
      'loadSupersededVersion'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadSupersededVersion');

    cy.get('.version-select').select('2.1');
    cy.get('.version-select option').should('have.length', 2);
    cy.get('.version-select').should('not.be.disabled');

    cy.get('.version-select').select('2.0');
    cy.get('.version-select').should('have.value', '2.0');
  });

  it('disables the version selector when a single version is on offer', () => {
    interceptMetadata(
      { settings: { enabledConfigVersions: ['2.0'] } },
      'loadSingleVersionMetadata'
    );

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadSingleVersionMetadata');

    cy.get('.version-select option').should('have.length', 1);
    cy.get('.version-select').should('be.disabled');
  });

  it('displays read-only paper metadata with proper styling', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.read-only-section').should('exist');
    cy.get('.readonly-description').should('exist');
    cy.get('.info-grid').should('exist');
    cy.get('.orcid-badge').should('exist');
  });

  it('can add manifest files via file upload', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const fileName = 'test-output.png';
    const fileContent = 'fake file content';
    
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from(fileContent),
      fileName: fileName,
      mimeType: 'image/png'
    }, { force: true });
    
    cy.get('.manifest-table').should('exist');
    cy.get('input.file-name').should('have.value', fileName);
  });

  it('can add and remove manifest files', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Add file
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from('test'),
      fileName: 'test.csv',
      mimeType: 'text/csv'
    }, { force: true });
    
    cy.get('input.file-name').should('have.value', 'test.csv');
    
    // Remove file, which asks first through OJS's own modal
    cy.get('.pkpButton--close').first().click();

    answerModal('yes');

    // File should be removed (or empty state shown)
    cy.get('.manifest-table').should('not.exist');
  });

  it('can add and edit comment for manifest files', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('input[type="file"]').selectFile({
      contents: Cypress.Buffer.from('test'),
      fileName: 'output.png',
      mimeType: 'image/png'
    }, { force: true });
    
    cy.get('.manifest-table input.file-comment')
      .type('This is the main result figure');

    cy.get('.manifest-table input.file-comment')
      .should('have.value', 'This is the main result figure');
  });

  it('can add repositories', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();
    
    cy.get('.repository-list').should('exist');
    cy.get('.repository-item input[type="url"]').should('exist');
  });

  it('can remove repositories', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Add repository
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();
    
    cy.get('.repository-item input[type="url"]')
      .type('https://github.com/example/repo');
    
    // Remove repository, which asks first through OJS's own modal
    cy.get('.repository-item .pkpButton--close').click();

    answerModal('no');
    cy.get('.repository-item').should('have.length', 1);

    cy.get('.repository-item .pkpButton--close').click();
    answerModal('yes');
    cy.get('.repository-item').should('not.exist');
  });

  /**
   * An unfinished record saves: the identifier, the manifest and the summary
   * arrive at different times, and requiring them held back every save until
   * the last of them was there.
   */
  it('saves a record that is not complete yet', () => {
    cy.intercept('POST', '**/codecheck/metadata*', {
      statusCode: 200,
      body: { success: true }
    }).as('saveMetadata');

    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.get('.footer-actions button').contains(/save/i).click();

    cy.wait('@saveMetadata');
    cy.get('.save-message.error').should('not.exist');
  });

  /**
   * The save stands when the register issue could not be brought up to date,
   * and the editor is told so beside it rather than only in the console (#186).
   */
  it('says beside a successful save what the register did not get', () => {
    cy.intercept('POST', '**/codecheck/metadata*', {
      statusCode: 200,
      body: { success: true, registerWarning: 'GitHub did not answer within 10 seconds.' }
    }).as('saveMetadata');

    mountForm();
    cy.wait('@loadMetadata');
    cy.get('.footer-actions button').contains(/save/i).click();
    cy.wait('@saveMetadata');

    cy.get('.save-message.warning')
      .should('contain', 'plugins.generic.codecheck.savedSuccessfully')
      .and('contain', 'GitHub did not answer within 10 seconds.');
  });

  it('does not ask to update a register issue the check does not have', () => {
    cy.intercept('POST', '**/codecheck/metadata*', { statusCode: 200, body: { success: true } }).as('saveMetadata');
    cy.intercept('POST', '**/codecheck/issue*', cy.spy().as('updateIssue'));

    mountForm();
    cy.wait('@loadMetadata');
    cy.get('.footer-actions button').contains(/save/i).click();
    cy.wait('@saveMetadata');

    cy.get('.save-message.success').should('be.visible');
    cy.get('@updateIssue').should('not.have.been.called');
  });

  describe('fields the config specification requires', () => {
    const warning = () => cy.get('[data-testid="config-spec-warning"]');
    const missing = (field) => `plugins.generic.codecheck.configSpec.missing.${field}`;

    /** A record with everything 2.0 requires, bar what `codecheck` overrides. */
    const completeRecord = (codecheck = {}, submission = {}) => {
      const body = metadataResponseBody();
      return {
        submission: { ...body.submission, ...submission },
        codecheck: {
          ...body.codecheck,
          manifest: [{ file: 'figure2.png', comment: 'Figure 2' }],
          codecheckers: [{ name: 'Stephen J. Eglen', orcid: '0000-0001-8607-8025' }],
          certificate: '2025-042',
          summary: 'Everything reproduced.',
          report: 'https://doi.org/10.5281/zenodo.1',
          ...codecheck,
        },
      };
    };

    it('names what an unfinished record still lacks, and saves it anyway', () => {
      cy.intercept('POST', '**/codecheck/metadata*', {
        statusCode: 200,
        body: { success: true }
      }).as('saveMetadata');

      mountForm();
      cy.wait('@loadMetadata');

      warning().should('contain', 'Version 2.0 of the CODECHECK config file specification');
      ['manifest', 'codechecker', 'summary', 'certificate', 'report'].forEach((field) =>
        warning().should('contain', missing(field))
      );
      // The paper's title, authors, their iD and the DOI are all there.
      ['title', 'authors', 'reference'].forEach((field) =>
        warning().should('not.contain', missing(field))
      );
      warning().should('not.contain', 'ORCID iD for every author');

      cy.get('.footer-actions button').contains(/save/i).click();
      cy.wait('@saveMetadata');
      cy.get('.save-message.error').should('not.exist');
    });

    it('shows nothing for a record that has everything', () => {
      interceptMetadata(completeRecord(), 'loadComplete');
      mountForm();
      cy.wait('@loadComplete');

      warning().should('not.exist');
    });

    it('names the authors without an ORCID iD, and a missing DOI', () => {
      interceptMetadata(completeRecord({}, {
        authors: [
          { name: 'John Doe', orcid: '0000-0001-2345-6789' },
          { name: 'Jane Smith', orcid: '' },
          { name: 'Max Mustermann', orcid: null },
        ],
        doi: null,
      }), 'loadWithoutOrcids');
      mountForm();
      cy.wait('@loadWithoutOrcids');

      warning()
        .should('contain', 'missing for: Jane Smith, Max Mustermann')
        .and('not.contain', 'John Doe')
        .and('contain', missing('reference'));
    });

    it('goes away once the missing field is filled in', () => {
      interceptMetadata(completeRecord({ summary: '' }), 'loadWithoutSummary');
      mountForm();
      cy.wait('@loadWithoutSummary');

      warning().should('contain', missing('summary'));
      cy.contains('.field-label', /summary/i).parent().find('textarea').type('Everything reproduced.');
      warning().should('not.exist');
    });

    it('does not take a malformed certificate identifier for one', () => {
      interceptMetadata(completeRecord({ certificate: 'CODECHECK 2025/42' }), 'loadMalformedIdentifier');
      mountForm();
      cy.wait('@loadMalformedIdentifier');

      warning().should('contain', missing('certificate'));
    });

    it('keeps judging the OJS authors after a repository import', () => {
      // The generated codecheck.yml takes the paper's authors from OJS, so an
      // imported file's authors must not make the warning think they have iDs.
      interceptMetadata(completeRecord({
        repository: { repositories: [
          { url: 'https://github.com/codecheckers/repo', hidden: false, providedByAuthor: false, containsCodecheckYaml: true },
        ] },
      }, {
        authors: [{ name: 'Jane Smith', orcid: '' }],
      }), 'loadForImport');
      cy.intercept('POST', '**/codecheck/repository?submissionId=1*', {
        statusCode: 200,
        body: {
          success: true,
          metadata: {
            paper: {
              title: 'Imported title',
              authors: [{ name: 'Imported Author', ORCID: '0000-0001-2345-6789' }],
              doi: '10.1234/imported',
            },
            summary: 'Imported summary',
          },
        },
      }).as('importMetadata');

      mountForm();
      cy.wait('@loadForImport');
      warning().should('contain', 'missing for: Jane Smith');

      cy.get('.repository-item .btn-add').click();
      cy.wait('@importMetadata');

      cy.contains('.field-label', /summary/i).parent().find('textarea').should('have.value', 'Imported summary');
      cy.get('.read-only-section').should('contain', 'Test Article Title').and('not.contain', 'Imported title');
      warning().should('contain', 'missing for: Jane Smith').and('not.contain', 'Imported Author');
    });

    it('repeats the warning in the YAML preview', () => {
      interceptMetadata(completeRecord({ report: '' }), 'loadWithoutReport');
      cy.intercept('GET', '**/codecheck/yaml*', {
        statusCode: 200,
        body: { yaml: 'version: https://codecheck.org.uk/spec/config/2.0/\n', filename: 'codecheck.yml' }
      }).as('generateYaml');
      cy.intercept('POST', '**/codecheck/yaml/validate*', {
        statusCode: 200,
        body: { success: true }
      }).as('validateYaml');

      mountForm();
      cy.wait('@loadWithoutReport');
      cy.get('[data-testid="preview-yaml-button"]').click();
      cy.wait('@validateYaml');

      cy.get('.pkp-mock-modal [data-testid="config-spec-warning"]')
        .should('contain', missing('report'))
        .and('not.contain', missing('summary'));
      cy.get('.pkp-mock-modal .yaml-preview-content').should('contain', 'spec/config/2.0/');
    });
  });

  it('can fill and save summary field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /summary/i)
      .parent()
      .find('textarea')
      .type('This is a comprehensive test summary of the codecheck process. All outputs were reproduced successfully.');
    
    cy.contains('.field-label', /summary/i)
      .parent()
      .find('textarea')
      .should('contain.value', 'This is a comprehensive test summary');
  });

  it('can fill report URL field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /report/i)
      .parent()
      .find('input[type="url"]')
      .type('https://zenodo.org/record/12345');
    
    cy.contains('.field-label', /report/i)
      .parent()
      .find('input[type="url"]')
      .should('have.value', 'https://zenodo.org/record/12345');
  });

  it('can fill completion time field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const testDateTime = '2025-01-29T15:30';
    
    cy.get('input[type="datetime-local"]')
      .type(testDateTime);
    
    cy.get('input[type="datetime-local"]')
      .should('have.value', testDateTime);
  });

  it('loads labels for certificate identifier', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    cy.wait('@loadLabelData');
    
    cy.get('.certificate-identifier-select.dropdown .dropdown-checkbox-input')
      .should('have.length.gt', 0);

    cy.get('.certificate-identifier-select.dropdown .dropdown-checkbox-input input[type="checkbox"]')
      .should('have.length', 2);
  });

  it('can reserve certificate identifier', () => {
    const submissionId = 1;

    interceptReservation(submissionId);

    mountAndReserve(submissionId);

    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body).to.have.property('reserveIdentifierMode', 'api');
      expect(interception.request.body.issue.labelsSelected).to.have.length.gt(0);
    });

    cy.get('.certificate-identifier-input')
      .should('have.value', '2025-042');
  });

  /**
   * Removing forgets the register issue along with the identifier: a
   * reservation made afterwards must not carry the old issue number, which
   * would point the update at the issue of the identifier just removed.
   */
  it('removes a reserved identifier once the editor confirms', () => {
    interceptReservation();

    mountAndReserve();
    cy.wait('@reserveIdentifier');

    identifierButton('reserve.withApi').should('be.disabled');

    identifierButton('remove').click();
    cy.get('.pkp-mock-modal__title')
      .should('have.text', 'plugins.generic.codecheck.identifier.remove.modal.title');
    answerModal('yes');

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.get('.certificate-identifier-input').should('have.value', '');
    identifierButton('reserve.withApi').should('not.be.disabled').click();

    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body.issue).to.include({ url: '', number: null });
    });
  });

  it('keeps the identifier when the removal is not confirmed', () => {
    interceptReservation();

    mountAndReserve();
    cy.wait('@reserveIdentifier');

    identifierButton('remove').click();
    answerModal('no');

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.get('.certificate-identifier-input').should('have.value', '2025-042');
    identifierButton('reserve.withApi').should('be.disabled');
  });

  /**
   * A submission may have no authors yet. The empty string is the server's to
   * turn into the issue's placeholder title, so the request carries no
   * translated stand-in (#130).
   */
  it('reserves an identifier for a submission without authors', () => {
    interceptMetadata({
      submission: { ...metadataResponseBody().submission, authors: [] }
    });
    interceptReservation();

    mountAndReserve();

    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body.submission.authorString).to.equal('');
    });
    cy.get('.certificate-identifier-input').should('have.value', '2025-042');
  });

  /**
   * The register holds no identifier yet: the server answers with what it
   * found instead of reserving, and the reservation is repeated only once the
   * editor has agreed to the register's first issue being opened (#130).
   */
  /**
   * OJS renders a dialog's message as markup. What the server names — here the
   * register repository — has to arrive as text.
   */
  it('shows what the server names in a dialog as text, not markup', () => {
    cy.intercept('POST', '**/codecheck/identifier?submissionId=1', {
      statusCode: 200,
      body: {
        success: false,
        confirmFirstIdentifier: true,
        identifier: '2026-001',
        organization: 'codecheckers',
        repository: '<img src=x class="injected">'
      }
    }).as('reserveIdentifier');

    mountAndReserve();
    cy.wait('@reserveIdentifier');

    cy.get('.pkp-mock-modal__message').should('contain', '<img src=x class="injected">');
    cy.get('.pkp-mock-modal__message img').should('not.exist');
  });

  it('asks before reserving the first identifier of an empty register', () => {
    const submissionId = 1;
    let call = 0;

    cy.intercept('POST', `**/codecheck/identifier?submissionId=${submissionId}`, (req) => {
      call++;
      if (call === 1) {
        req.reply({
          statusCode: 200,
          body: {
            success: false,
            confirmFirstIdentifier: true,
            identifier: '2026-001',
            organization: 'codecheckers',
            repository: 'testing-dev-register'
          }
        });
        return;
      }
      req.reply({
        statusCode: 200,
        body: {
          success: true,
          identifier: '2026-001',
          issueUrl: 'https://github.com/codecheckers/testing-dev-register/issues/1',
          issueNumber: 1
        }
      });
    }).as('reserveIdentifier');

    mountAndReserve(submissionId);

    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body).to.have.property('confirmFirstIdentifier', false);
    });

    cy.get('.pkp-mock-modal__message').should('contain', '2026-001');
    answerModal('yes');

    // the confirmation carries the identifier the editor was shown, so the
    // server can refuse a consent that has gone stale
    cy.wait('@reserveIdentifier').then((interception) => {
      expect(interception.request.body).to.have.property('confirmFirstIdentifier', true);
      expect(interception.request.body).to.have.property('confirmedIdentifier', '2026-001');
    });

    cy.get('.certificate-identifier-input').should('have.value', '2026-001');
  });

  it('reserves nothing when the first identifier is not confirmed', () => {
    const submissionId = 1;

    cy.intercept('POST', `**/codecheck/identifier?submissionId=${submissionId}`, {
      statusCode: 200,
      body: {
        success: false,
        confirmFirstIdentifier: true,
        identifier: '2026-001',
        organization: 'codecheckers',
        repository: 'testing-dev-register'
      }
    }).as('reserveIdentifier');

    mountAndReserve(submissionId);

    cy.wait('@reserveIdentifier');

    // asked once, declined, and nothing reserved — in particular no second POST
    cy.get('.pkp-mock-modal__message').should('contain', '2026-001');
    answerModal('no');

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.get('.save-message.warning').should('be.visible');
    cy.get('.certificate-identifier-input').should('have.value', '');
  });

  /**
   * A register that cannot be read, or whose issues carry no readable
   * identifier, is refused by the server rather than offered for confirmation —
   * reserving there would duplicate an identifier that is already recorded. The
   * form shows the reason and asks nothing (#130).
   */
  it('shows the reason and asks nothing when the register is refused', () => {
    const submissionId = 1;

    cy.intercept('POST', `**/codecheck/identifier?submissionId=${submissionId}`, {
      statusCode: 409,
      body: {
        success: false,
        error: 'The register repository codecheckers/testing-dev-register has no label “id assigned”.'
      }
    }).as('reserveIdentifier');

    mountAndReserve(submissionId);

    cy.wait('@reserveIdentifier');

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.get('.save-message.error')
      .should('be.visible')
      .and('contain', 'id assigned');
    cy.get('.certificate-identifier-input').should('have.value', '');
  });

  it('disables preview button when requirements not met', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.footer-actions button')
      .contains(/preview/i)
      .should('be.disabled');
  });

  it('can add codecheckers via modal', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /codechecker/i)
      .parent()
      .find('.btn-add')
      .should('exist')
      .and('not.be.disabled');
  });

  /**
   * The dialog's body is a Vue component with its own fields (#180), so the
   * form takes the codechecker from what the component answers rather than
   * from `document.getElementById`.
   */
  it('adds the codechecker the dialog was filled in with', () => {
    mountForm();
    cy.wait('@loadMetadata');

    addCodechecker();
    cy.get('.pkp-mock-modal input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('.pkp-mock-modal input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0097');
    dialogSubmit('common.add').click();

    cy.get('.pkp-mock-modal').should('not.exist');
    cy.get('.codecheckers-list .item-name').should('have.text', 'Ada Lovelace');
    cy.get('.codecheckers-list .item-orcid').should('contain', '0000-0002-1825-0097');
  });

  /**
   * An empty name used to close the dialog and add nothing at all, so the
   * editor was left believing the codechecker was on the record.
   */
  it('keeps the dialog open and adds nothing when the name is missing', () => {
    mountForm();
    cy.wait('@loadMetadata');

    addCodechecker();
    dialogSubmit('common.add').click();

    cy.get('.pkp-mock-modal').should('exist');
    cy.get('.pkp-mock-modal .modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.nameRequired');
    cy.get('.codecheckers-list').should('not.exist');
  });

  it('refuses an ORCID that is not one', () => {
    mountForm();
    cy.wait('@loadMetadata');

    addCodechecker();
    cy.get('.pkp-mock-modal input[id^=codecheck-checker-name]').type('Ada Lovelace');
    cy.get('.pkp-mock-modal input[id^=codecheck-checker-orcid]').type('0000-0002-1825-0098');
    dialogSubmit('common.add').click();

    cy.get('.pkp-mock-modal').should('exist');
    cy.get('.pkp-mock-modal .modal-field-error')
      .should('have.text', 'plugins.generic.codecheck.codecheckers.validation.orcidInvalid');
    cy.get('.codecheckers-list').should('not.exist');
  });

  it('can fill source field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.contains('.field-label', /source/i)
      .parent()
      .find('textarea')
      .type('https://github.com/codecheckers/register/tree/master/2025-042');
    
    cy.contains('.field-label', /source/i)
      .parent()
      .find('textarea')
      .should('contain.value', 'https://github.com/codecheckers/register');
  });

  it('can fill additional content field', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    const additionalYaml = 'custom_field: custom_value\nanother_field: another_value';
    
    cy.get('.form-details textarea').last()
      .type(additionalYaml);
    
    cy.get('.form-details textarea').last()
      .should('contain.value', 'custom_field: custom_value');
  });

  it('shows correct form sections', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    // Check all major sections exist
    cy.get('.codecheck-header').should('exist');
    cy.get('.read-only-section').should('exist');
    cy.get('.form-details').should('exist');
    cy.get('.form-footer').should('exist');
  });

  it('displays version selector', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });
    
    cy.wait('@loadMetadata');
    
    cy.get('.version-selector').should('exist');
    cy.get('.version-select').should('exist');
    cy.get('.version-select option[value="2.0"]').should('exist');
  });

  it('new repository has its hidden checkbox unchecked by default', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repo-hidden-checkbox').should('exist').and('not.be.checked');
  });

  it('checking the hidden checkbox marks the repository hidden', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repo-hidden-checkbox').check();
    cy.get('.repo-hidden-checkbox').should('be.checked');
  });

  it('hidden flag is independent per repository', () => {
    cy.mount(CodecheckMetadataForm, {
      props: {
        submission: { id: 1 },
        canEdit: true
      }
    });

    cy.wait('@loadMetadata');

    // Add two repositories
    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    // Check only the first one
    cy.get('.repo-hidden-checkbox').eq(0).check();

    cy.get('.repo-hidden-checkbox').eq(0).should('be.checked');
    cy.get('.repo-hidden-checkbox').eq(1).should('not.be.checked');
  });
  
  it('hidden checkbox state is preserved after save', () => {
    cy.intercept('POST', '**/codecheck/metadata*', {
      statusCode: 200,
      body: { success: true }
    }).as('saveMetadata');

    cy.mount(CodecheckMetadataForm, {
      props: { submission: { id: 1 }, canEdit: true }
    });

    cy.wait('@loadMetadata');

    cy.contains('.field-label', /repositories/i)
      .parent()
      .find('.btn-add')
      .click();

    cy.get('.repository-item input[type="url"]').first()
      .type('https://github.com/test/private-repo');

    cy.get('.repo-hidden-checkbox').first().check();
    cy.get('.repo-hidden-checkbox').first().should('be.checked');
  });

  it('fills the completion time with the current moment via the Now link', () => {
    cy.mount(CodecheckMetadataForm, {
      props: { submission: { id: 1 }, canEdit: true }
    });
    cy.wait('@loadMetadata');

    cy.get('input[type="datetime-local"]').should('have.value', '');
    cy.get('.check-time-now').click();

    // Native datetime-local wants YYYY-MM-DDTHH:mm, and it should be now.
    cy.get('input[type="datetime-local"]').invoke('val').should((value) => {
      expect(value).to.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
      const minutesApart = Math.abs(new Date(value) - new Date()) / 60000;
      expect(minutesApart, 'set to roughly now').to.be.lessThan(5);
    });
  });

  describe('author-provided entries', () => {
    beforeEach(() => {
      cy.intercept('GET', '**/codecheck/metadata*', {
        statusCode: 200,
        body: {
          success: true,
          submissionId: 1,
          submission: { id: 1, title: 'Test Article Title', authors: [], doi: null },
          codecheck: {
            version: '2.0',
            publicationType: 'doi',
            manifest: [
              { file: 'figure2.png', comment: 'Figure 2', hidden: false, providedByAuthor: true },
              { file: 'extra.csv', comment: 'added by the codechecker', hidden: false, providedByAuthor: false },
            ],
            repository: {
              repositories: [
                { url: 'https://github.com/author/repo', hidden: false, providedByAuthor: true },
                { url: 'https://github.com/codechecker/repo', hidden: false, providedByAuthor: false, containsCodecheckYaml: true },
              ],
            },
            source: '',
            codecheckers: [],
            certificate: '',
            check_time: '',
            summary: '',
            report: '',
            additionalContent: '',
            issue: { url: null, number: null, labels: [], labelsSelected: [] },
          },
        }
      }).as('loadAuthored');

      cy.mount(CodecheckMetadataForm, { props: { submission: { id: 1 }, canEdit: true } });
      cy.wait('@loadAuthored');
    });

    it('marks the entries the author submitted', () => {
      cy.get('.repository-item').eq(0).find('.provided-by-author').should('exist');
      cy.get('.repository-item').eq(1).find('.provided-by-author').should('not.exist');

      cy.get('.manifest-row').eq(0).find('.provided-by-author').should('exist');
      cy.get('.manifest-row').eq(1).find('.provided-by-author').should('not.exist');
    });

    // Issue #154: which repository holds the codecheck.yml is recorded on the
    // entry, so the mark is on the one that carries it rather than on whatever
    // is currently in a given position.
    it('marks the repository that carries the codecheck.yml', () => {
      cy.get('.repository-item').eq(0).find('.btn-radio').should('not.have.class', 'btn-radio__active');
      cy.get('.repository-item').eq(1).find('.btn-radio').should('have.class', 'btn-radio__active');
    });

    it('moves the mark when another repository is chosen', () => {
      cy.get('.repository-item').eq(0).find('.btn-radio').click();

      cy.get('.repository-item').eq(0).find('.btn-radio').should('have.class', 'btn-radio__active');
      cy.get('.repository-item').eq(1).find('.btn-radio').should('not.have.class', 'btn-radio__active');
    });

    // Issue #169: hiding a repository and marking it as the one holding the
    // codecheck.yml contradict each other — the mark publishes the address in
    // the CODECHECK Register, the hide flag says it must not be published.
    // Publication validation refuses the combination, but at the publish
    // dialog; the form refuses to create it in the first place.
    it('refuses to hide the repository that carries the codecheck.yml', () => {
      // .click() rather than .check(), which asserts the box ends up checked.
      cy.get('.repository-item').eq(1).find('.repo-hidden-checkbox').click();

      cy.get('.repository-item').eq(1).find('.repo-hidden-checkbox').should('not.be.checked');
      cy.get('.repository-item').eq(1).find('.btn-radio').should('have.class', 'btn-radio__active');
      cy.get('.codecheck-repository-error').should('contain', 'cannot be the one containing');
    });

    it('refuses to mark a hidden repository as carrying the codecheck.yml', () => {
      // Hiding an unmarked repository is allowed and is the setup for this.
      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').check();
      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').should('be.checked');

      cy.get('.repository-item').eq(0).find('.btn-radio').click();

      cy.get('.repository-item').eq(0).find('.btn-radio').should('not.have.class', 'btn-radio__active');
      cy.get('.repository-item').eq(1).find('.btn-radio').should('have.class', 'btn-radio__active');
      cy.get('.codecheck-repository-error').should('contain', 'cannot be the one containing');
    });

    it('clears the refusal once the repository is no longer hidden', () => {
      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').check();
      cy.get('.repository-item').eq(0).find('.btn-radio').click();
      cy.get('.codecheck-repository-error').should('exist');

      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').uncheck();

      cy.get('.codecheck-repository-error').should('not.exist');
    });

    it('offers no delete control on an author repository', () => {
      cy.get('.repository-item').eq(0).find('.pkpButton--close').should('not.exist');
      cy.get('.repository-item').eq(1).find('.pkpButton--close').should('exist');
    });

    it('offers no delete control on an author manifest entry', () => {
      cy.get('.manifest-row').eq(0).find('.pkpButton--close').should('not.exist');
      cy.get('.manifest-row').eq(1).find('.pkpButton--close').should('exist');
    });

    it('allows the output file name to be edited, including the author\'s', () => {
      cy.get('.manifest-row').eq(0).find('input.file-name')
        .should('have.value', 'figure2.png')
        .clear()
        .type('figures/figure2.png')
        .should('have.value', 'figures/figure2.png');

      cy.get('.manifest-row').eq(1).find('input.file-name')
        .should('have.value', 'extra.csv')
        .should('not.be.disabled');
    });

    it('still allows an author entry to be edited and hidden', () => {
      cy.get('.repository-item').eq(0).find('input[type="url"]')
        .should('not.be.disabled')
        .clear()
        .type('https://github.com/author/repo-corrected');

      cy.get('.repository-item').eq(0).find('.repo-hidden-checkbox').check().should('be.checked');
      cy.get('.manifest-row').eq(0).find('.manifest-hidden-checkbox').check().should('be.checked');
    });
  });
});

/**
 * The certificate among the article's references (#183): the button follows
 * the journal's setting, waits for a certificate and for the form to be saved,
 * and reports what the server wrote — or why it refused — in a dialog.
 */
describe('CodecheckMetadataForm certificate reference', () => {
  const ADD = 'plugins.generic.codecheck.certificateReference.add';
  const LINE = 'Jane Doe. (2026). CODECHECK certificate 2026-001. Zenodo. https://doi.org/10.5281/zenodo.1';

  const withCertificate = (mode, certificate = '2026-001') => {
    const body = metadataResponseBody();
    body.codecheck.certificate = certificate;
    body.settings = { enabledConfigVersions: ['2.0'], certificateReferenceMode: mode };
    cy.intercept('GET', '**/codecheck/metadata*', { statusCode: 200, body }).as('loadMetadata');
    mountForm();
    cy.wait('@loadMetadata');
  };

  const addButton = () => cy.contains('.certificate-reference button', ADD);

  it('offers no button when the journal does not list the certificate', () => {
    withCertificate('off');
    cy.get('.certificate-reference').should('not.exist');
  });

  it('offers no button when the response does not say', () => {
    interceptMetadata();
    mountForm();
    cy.wait('@loadMetadata');
    cy.get('.certificate-reference').should('not.exist');
  });

  it('waits for a certificate', () => {
    withCertificate('button', '');
    addButton().should('be.disabled');
    cy.get('.certificate-reference .field-description')
      .should('contain', 'plugins.generic.codecheck.certificateReference.needsCertificate');
  });

  it('waits for unsaved changes to be saved, since the server cites the saved check', () => {
    withCertificate('button');
    addButton().should('not.be.disabled');

    cy.get('input[type="url"]').first().type('https://doi.org/10.5281/zenodo.2');
    addButton().should('be.disabled');
    cy.get('.certificate-reference .field-description')
      .should('contain', 'plugins.generic.codecheck.certificateReference.saveFirst');
  });

  it('says when a version is published, too, in that mode', () => {
    withCertificate('publish');
    cy.get('.certificate-reference .field-description')
      .should('contain', 'plugins.generic.codecheck.certificateReference.hintPublish');
  });

  it('shows the line it wrote', () => {
    withCertificate('button');
    cy.intercept('POST', '**/codecheck/references?submissionId=1', {
      statusCode: 200,
      body: { success: true, line: LINE, changed: true }
    }).as('addReference');

    addButton().click();
    cy.wait('@addReference');
    cy.get('.pkp-mock-modal')
      .should('contain', 'plugins.generic.codecheck.certificateReference.added')
      .and('contain', LINE);
  });

  it('says so when the references already carried the line', () => {
    withCertificate('button');
    cy.intercept('POST', '**/codecheck/references?submissionId=1', {
      statusCode: 200,
      body: { success: true, line: LINE, changed: false }
    });

    addButton().click();
    cy.get('.pkp-mock-modal').should('contain', 'plugins.generic.codecheck.certificateReference.unchanged');
  });

  it('shows why the server refused', () => {
    withCertificate('button');
    cy.intercept('POST', '**/codecheck/references?submissionId=1', {
      statusCode: 400,
      body: { success: false, error: 'Create a new version first.' }
    });

    addButton().click();
    cy.get('.pkp-mock-modal').should('contain', 'Create a new version first.');
    addButton().should('not.be.disabled');
  });
});
