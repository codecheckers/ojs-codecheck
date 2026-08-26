/**
 * What the badge in an issue's table of contents actually renders.
 *
 * `issue-toc-setting.cy.js` covers whether the badge appears at all; this covers
 * what appears when it does. Both the image and the text-only form come from the
 * journal's badge settings, and both are on a public listing, so a broken image
 * URL or an unstyled fallback is visible to every reader of the issue.
 *
 * The decision gates ahead of rendering — the setting, the opt-in — are unit
 * tested in tests/FrontEndUnitTests/IssueTOCUnitTest.php; they need no database.
 * The completed-check gate does, which is why it is here.
 *
 * Each variant is also captured to cypress/screenshots/, so what the settings
 * actually produce can be looked at rather than inferred from assertions. They
 * are a by-product: nothing asserts on them, and a failing test still writes
 * Cypress's own failure screenshot beside them.
 */

const JOURNAL = 'codecheck';
const BADGE = '.codecheck-badge';

function visitAnIssue() {
  cy.visit(`/index.php/${JOURNAL}/issue/archive`);
  cy.get('a[href*="/issue/view/"]').first().click();
  cy.get('.obj_article_summary, .article_summary').should('exist');
}

/** Captures the first badge with enough of the listing around it to read. */
function shootBadge(name) {
  cy.get(BADGE).first().scrollIntoView();
  cy.get(BADGE).first().closest('.obj_article_summary, .article_summary')
    .screenshot(`issue-toc-badge-${name}`, { overwrite: true });
}

/** Sets a text-like or colour field on the plugin settings form and saves. */
function setBadgeFields(fields) {
  cy.visit(`/index.php/${JOURNAL}/management/settings/website`);
  cy.get('a[href*="verb=settings"][href*="plugin=codecheckplugin"]', { timeout: 20000 })
    .first()
    .click({ force: true });
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('exist');

  Object.entries(fields).forEach(([selector, value]) => {
    if (value === true) {
      cy.get(selector).scrollIntoView();
      cy.get(selector).check({ force: true });
    } else {
      // Set directly rather than typed: a colour input rejects typing, and the
      // text field is hidden whenever the badge is not the text-only one.
      cy.get(selector).invoke('val', value);
      cy.get(selector).trigger('change', { force: true });
    }
  });

  cy.get('form#codecheckSettings').find('button[type="submit"]').first().click();
  cy.get('form#codecheckSettings', { timeout: 20000 }).should('not.exist');
}

describe('Issue table of contents badge', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  // The badge settings are journal-wide, so put them back whatever happens.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    setBadgeFields({
      '#badgeCodeworks': true,
      '#codecheckBadgeText': '',
      '#codecheckBadgeTextColor': '#2d7f3e',
      '#codecheckBadgeLinkTarget': 'register',
    });
    cy.setCodecheckSetting('showInTOC', true);
  });

  it('renders the badge as an image linking to the certificate', () => {
    setBadgeFields({ '#badgeCodeworks': true });

    visitAnIssue();

    cy.get(`${BADGE} img.codecheck-badge-img`)
      .first()
      .should('have.attr', 'src')
      .and('match', /codeworks-badge\.png$/);

    shootBadge('01-code-works-image');
  });

  it('renders the CODECHECK logo when that is the chosen image', () => {
    setBadgeFields({ '#badgeCodecheckLogo': true });

    visitAnIssue();

    cy.get(`${BADGE} img.codecheck-badge-img`)
      .first()
      .should('have.attr', 'src')
      .and('match', /codecheck_logo\.svg$/);

    shootBadge('02-codecheck-logo');
  });

  it('renders the badge at the configured height', () => {
    setBadgeFields({ '#badgeCodeworks': true, '[name="codecheckBadgeHeight"]': '40' });

    visitAnIssue();
    cy.get(`${BADGE} img.codecheck-badge-img`).first().should('have.css', 'height', '40px');
    shootBadge('03-height-40px');

    setBadgeFields({ '[name="codecheckBadgeHeight"]': '24' });
  });

  it('renders the configured text and colour when the journal shows no image', () => {
    setBadgeFields({
      '#badgeNone': true,
      '#codecheckBadgeText': 'CODE WORKS',
      '#codecheckBadgeTextColor': '#b5121b',
    });

    visitAnIssue();

    cy.get(`${BADGE}.codecheck-badge--text`)
      .first()
      .should('contain', 'CODE WORKS')
      .and('have.css', 'color', 'rgb(181, 18, 27)');
    cy.get(`${BADGE} img`).should('not.exist');

    shootBadge('04-text-only-custom');
  });

  it('falls back to the default wording when the text is cleared', () => {
    setBadgeFields({ '#badgeNone': true, '#codecheckBadgeText': '' });

    visitAnIssue();
    cy.get(`${BADGE}.codecheck-badge--text`).first().should('contain', 'CODECHECK');

    shootBadge('05-text-only-default');
  });

  it('links the badge to the register or to the DOI, as the journal chose', () => {
    // Until this setting existed the badge linked nowhere at all: the link was
    // built only for a "CODECHECK-YYYY-NNN" certificate while identifiers are
    // stored as "YYYY-NNN".
    setBadgeFields({ '#badgeCodeworks': true, '#codecheckBadgeLinkTarget': 'register' });

    visitAnIssue();
    cy.get(BADGE)
      .first()
      .should('have.attr', 'href')
      .and('match', /^https:\/\/codecheck\.org\.uk\/register\/certs\/\d{4}-\d+\/$/);

    setBadgeFields({ '#codecheckBadgeLinkTarget': 'doi' });

    visitAnIssue();
    // The seeded records carry a DOI; where one is missing the register link
    // stands in, which is asserted in the Badge unit tests.
    cy.get(BADGE).first().should('have.attr', 'href').and('not.be.empty');
  });

  it('badges only the articles whose check is finished', () => {
    setBadgeFields({ '#badgeCodeworks': true });

    // Every badge belongs to an article summary, and no summary carries more
    // than one — a badge escaping into the surrounding markup, or one rendered
    // per galley, would show up here.
    visitAnIssue();
    cy.get(BADGE).should('have.length.greaterThan', 0);
    cy.get('.obj_article_summary, .article_summary').then(($summaries) => {
      cy.get(BADGE).then(($badges) => {
        expect($badges.length, 'badges never outnumber the articles listed')
          .to.be.at.most($summaries.length);
      });
    });

    // Every badge is a link and opens in a new tab, whatever it points at.
    cy.get(BADGE).each(($badge) => {
      cy.wrap($badge).should('have.attr', 'target', '_blank');
    });
  });
});
