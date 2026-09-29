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

/** Needs a backend page open, for the CSRF token. */
function contextsApi(method, body) {
  return cy.getCsrfToken().then((csrfToken) => cy.request({
    method,
    url: `/index.php/${JOURNAL}/api/v1/contexts/${CONTEXT_ID}`,
    headers: { 'X-Csrf-Token': csrfToken },
    body,
  }));
}

function openSettings() {
  cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
  cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
    .first()
    .click({ force: true });
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('exist');
}

/**
 * Fills multilingual fields by name — FBV gives them generated ids, and puts
 * the non-primary languages in a popover hidden until focus — and saves.
 */
function setWording(values, badgeType = '#badgeNone') {
  openSettings();
  cy.get(badgeType).check({ force: true });
  Object.entries(values).forEach(([name, value]) => {
    cy.get(`form#codecheckSettings [name="${name}"]`).invoke('val', value);
  });
  cy.get('form#codecheckSettings').find('button[type="submit"]').first().click();
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('not.exist');
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
    contextsApi('PUT', { supportedLocales: ['en', 'de'], supportedFormLocales: ['en', 'de'] })
      .its('status').should('eq', 200);

    cy.getCsrfToken().then((csrfToken) => cy.request({
      url: `/index.php/${JOURNAL}/api/v1/submissions?status[]=3&count=1`,
      headers: { 'X-Csrf-Token': csrfToken },
    })).then(({ body }) => {
      articleId = body.items[0].id;
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
    setWording(allWording(''), '#badgeCodeworks');
    contextsApi('PUT', originalLocales).its('status').should('eq', 200);
  });

  it('offers a field per journal language, and shows each reader theirs', () => {
    openSettings();
    FIELDS.forEach((field) => {
      cy.get(`form#codecheckSettings [name="${field}[en]"]`).should('exist');
      cy.get(`form#codecheckSettings [name="${field}[de]"]`).should('exist');
      // Installed for no one, so not offered.
      cy.get(`form#codecheckSettings [name="${field}[fr_FR]"]`).should('not.exist');
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
    contextsApi('PUT', { supportedFormLocales: ['en'] }).its('status').should('eq', 200);

    setWording({ 'availabilityStatementHeading[en]': WORDING.heading.en });

    expectWordingIn('de', WORDING.heading.de, WORDING.badge.de);

    cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
    contextsApi('PUT', { supportedFormLocales: ['en', 'de'] }).its('status').should('eq', 200);
  });
});
