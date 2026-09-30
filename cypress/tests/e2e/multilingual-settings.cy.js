/**
 * The journal's own wording for reader-facing texts, per language (#164): the
 * settings form offers a field per journal language, and a reader sees the one
 * for theirs, else the primary language's. The rule itself is unit tested
 * (Constants::localizedText()). The dataset's journal is English-only, so this
 * switches German on for it and back off afterwards.
 */

const JOURNAL = 'codecheck';
const CONTEXT_ID = 1;
const BADGE_TEXT = '.codecheck-badge.codecheck-badge--text';
const HEADING = '[data-testid="codecheck-article-availability"] h2';
const FIELDS = ['availabilityStatementHeading', 'codecheckBadgeText'];

const WORDING = {
  heading: { en: 'Data and code', de: 'Daten und Code' },
  badge: { en: 'CODE CHECKED', de: 'CODE GEPRÜFT' },
};

let originalLocales;
let articleId;
let issueId;

/** Reads or writes the journal's languages; asserts the call succeeded. */
function contextsApi(method, body) {
  return cy.ojsApi(method, `api/v1/contexts/${CONTEXT_ID}`, body).then((response) => {
    expect(response.status, `${method} contexts/${CONTEXT_ID}`).to.eq(200);
    return response;
  });
}

/**
 * Fills multilingual fields by name, since FBV gives them generated ids, and
 * saves.
 */
function setWording(values, badgeType = '#badgeNone') {
  cy.setCodecheckFields({
    [badgeType]: true,
    ...Object.fromEntries(Object.entries(values).map(([name, value]) => [`[name="${name}"]`, value])),
  });
}

/** Every field in every language, set to the one value. */
function allWording(value) {
  return Object.fromEntries(FIELDS.flatMap((field) => ['en', 'de'].map((l) => [`${field}[${l}]`, value])));
}

/** A multilingual journal carries the locale in the URL. */
function expectWordingIn(locale, heading, badge) {
  cy.visit(`/index.php/${JOURNAL}/${locale}/article/view/${articleId}`);
  cy.get(HEADING).should('have.text', heading);

  cy.visit(`/index.php/${JOURNAL}/${locale}/issue/view/${issueId}`);
  cy.get(BADGE_TEXT).first().should('contain', badge);
}

describe('Journal wording per language', () => {
  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    contextsApi('GET').then(({ body }) => {
      originalLocales = {
        supportedLocales: body.supportedLocales,
        supportedFormLocales: body.supportedFormLocales,
      };
    });
    contextsApi('PUT', { supportedLocales: ['en', 'de'], supportedFormLocales: ['en', 'de'] });

    cy.publishedArticleId().then((id) => {
      expect(id, 'a published submission to test against').to.exist;
      articleId = id;
    });

    cy.visit(`/index.php/${JOURNAL}/issue/archive`);
    cy.get('a[href*="/issue/view/"]').first().invoke('attr', 'href').then((href) => {
      issueId = href.match(/issue\/view\/([^/?#]+)/)[1];
    });
  });

  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  after(() => {
    cy.ojsLogin('admin', 'admin');
    // A test that failed with German withdrawn from the form would leave its
    // fields unrendered, and clearing them would fail before the restore.
    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    contextsApi('PUT', { supportedFormLocales: ['en', 'de'] });
    setWording(allWording(''), '#badgeCodeworks');
    contextsApi('PUT', originalLocales);
  });

  it('offers a field per journal language, and shows each reader theirs', () => {
    cy.openCodecheckSettings();
    FIELDS.forEach((field) => {
      cy.codecheckSettingsForm().find(`[name="${field}[en]"]`).should('exist');
      cy.codecheckSettingsForm().find(`[name="${field}[de]"]`).should('exist');
      // Installed for no one, so not offered.
      cy.codecheckSettingsForm().find(`[name="${field}[fr_FR]"]`).should('not.exist');
    });

    setWording({
      'availabilityStatementHeading[en]': WORDING.heading.en,
      'availabilityStatementHeading[de]': WORDING.heading.de,
      'codecheckBadgeText[en]': WORDING.badge.en,
      'codecheckBadgeText[de]': WORDING.badge.de,
    });

    expectWordingIn('en', WORDING.heading.en, WORDING.badge.en);
    expectWordingIn('de', WORDING.heading.de, WORDING.badge.de);
  });

  it('falls back to the primary language\'s wording, not the default, for a language left empty', () => {
    setWording({
      'availabilityStatementHeading[en]': WORDING.heading.en,
      'availabilityStatementHeading[de]': '',
      'codecheckBadgeText[en]': WORDING.badge.en,
      'codecheckBadgeText[de]': '',
    });

    expectWordingIn('de', WORDING.heading.en, WORDING.badge.en);
  });

  it('keeps the wording of a reader language the form no longer offers', () => {
    setWording({
      'availabilityStatementHeading[de]': WORDING.heading.de,
      'codecheckBadgeText[de]': WORDING.badge.de,
    });

    // German stays a reader language but stops being a form language.
    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    contextsApi('PUT', { supportedFormLocales: ['en'] });

    setWording({ 'availabilityStatementHeading[en]': WORDING.heading.en });

    expectWordingIn('de', WORDING.heading.de, WORDING.badge.de);

    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    contextsApi('PUT', { supportedFormLocales: ['en', 'de'] });
  });
});
