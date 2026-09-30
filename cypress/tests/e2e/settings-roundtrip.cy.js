/**
 * Every value on the CODECHECK settings form must survive a save.
 *
 * A setting is wired up in three separate places — initData(), readInputData()
 * and execute() — and missing it from any one of them loses the value silently:
 * getData() returns null, updateSetting() writes the null, and the field simply
 * comes back empty next time the form is opened. Nothing fails, nothing logs.
 *
 * The fields are read from whatever the form renders rather than listed here,
 * so a setting added without being wired up fails without anyone remembering to
 * extend this spec.
 *
 * Fields are given distinctive values before saving. Simply saving unchanged
 * would prove very little: most settings are empty in the test dataset, and
 * "empty survived the save" is true whether or not the field round-trips.
 *
 * Note: saving calls SettingsForm::checkRegisterRepository(), which makes live
 * GitHub API calls, so saves are kept to what these assertions need.
 */

/**
 * Not asserted on:
 * - githubPersonalAccessToken is deliberately rendered blank and only written
 *   when non-empty, so it cannot round-trip by design.
 * - the register organisation and repository are left alone because changing
 *   them sends the save's GitHub lookup at a repository that does not exist.
 *   They are non-empty in the dataset, so the unchanged comparison still
 *   covers them.
 */
const DO_NOT_MODIFY = [
  'githubPersonalAccessToken',
  'githubRegisterOrganization',
  'githubRegisterRepository',
];

const NOT_ASSERTED = ['githubPersonalAccessToken', 'csrfToken'];

/** Values written into text-like fields, chosen to be obviously from a test. */
const TEXT_VALUE = {
  url: 'https://example.org/codecheck-roundtrip',
  number: '37',
  default: 'codecheck-roundtrip',
};

/** Captured before anything is changed; restored in after(). */
let originalFields;

function isTextLike(el) {
  return el.tagName === 'TEXTAREA'
    || ['text', 'url', 'number', 'email'].includes(el.type);
}

/** Read every named control the form renders into a plain {name: value} map. */
function captureFields() {
  return cy.codecheckSettingsForm().then(($form) => {
    const captured = {};

    $form.find('input[name], select[name], textarea[name]').each((_, el) => {
      const name = el.getAttribute('name');
      if (!name || NOT_ASSERTED.includes(name)) {
        return;
      }

      if (el.type === 'checkbox') {
        if (name.endsWith('[]')) {
          captured[name] = captured[name] || [];
          if (el.checked) {
            captured[name].push(el.value);
          }
        } else {
          captured[name] = el.checked;
        }
      } else if (el.type === 'radio') {
        if (el.checked) {
          captured[name] = el.value;
        }
      } else {
        captured[name] = el.value;
      }
    });

    Object.keys(captured).forEach((k) => {
      if (Array.isArray(captured[k])) {
        captured[k].sort();
      }
    });

    return captured;
  });
}

/**
 * Give every modifiable control a value different from the one it has, and
 * return the map that should come back after saving.
 */
function changeEveryField() {
  return cy.codecheckSettingsForm().then(($form) => {
    const expected = {};

    $form.find('input[name], select[name], textarea[name]').each((_, el) => {
      const name = el.getAttribute('name');
      if (!name || NOT_ASSERTED.includes(name)) {
        return;
      }

      if (DO_NOT_MODIFY.includes(name)) {
        // Left as-is, but still asserted on.
        if (el.type === 'checkbox') {
          expected[name] = el.checked;
        } else if (el.type !== 'radio' || el.checked) {
          expected[name] = el.value;
        }
        return;
      }

      if (el.type === 'checkbox' && !name.endsWith('[]')) {
        el.checked = !el.checked;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        expected[name] = el.checked;
      } else if (isTextLike(el)) {
        const value = el.type === 'url' ? TEXT_VALUE.url
          : el.type === 'number' ? TEXT_VALUE.number
            : TEXT_VALUE.default;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        expected[name] = value;
      } else if (el.type === 'checkbox') {
        // Checkbox group: leave membership alone, just record it.
        expected[name] = expected[name] || [];
        if (el.checked) {
          expected[name].push(el.value);
        }
      } else if (el.type === 'radio') {
        if (el.checked) {
          expected[name] = el.value;
        }
      } else {
        expected[name] = el.value;
      }
    });

    Object.keys(expected).forEach((k) => {
      if (Array.isArray(expected[k])) {
        expected[k].sort();
      }
    });

    return expected;
  });
}

/** Put the form back into a previously captured state. */
function applyFields(fields) {
  cy.codecheckSettingsForm().then(($form) => {
    $form.find('input[name], select[name], textarea[name]').each((_, el) => {
      const name = el.getAttribute('name');
      if (!name || !(name in fields) || DO_NOT_MODIFY.includes(name)) {
        return;
      }

      if (el.type === 'checkbox' && !name.endsWith('[]')) {
        el.checked = fields[name];
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (isTextLike(el)) {
        el.value = fields[name];
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });
}

describe('Settings round-trip', () => {
  beforeEach(() => {
    cy.ojsLogin('admin', 'admin');
  });

  before(() => {
    cy.ojsLogin('admin', 'admin');
    cy.openCodecheckSettings();
    captureFields().then((fields) => {
      originalFields = fields;
    });
  });

  // Restore whatever the journal had, so the rest of the suite is unaffected
  // even if an assertion above failed partway through.
  after(() => {
    cy.ojsLogin('admin', 'admin');
    cy.openCodecheckSettings();
    applyFields(originalFields);
    cy.saveCodecheckSettings();
  });

  it('renders a form with settings to check', () => {
    cy.openCodecheckSettings();

    // Guards the assertions below against passing on an empty form.
    captureFields().then((fields) => {
      expect(Object.keys(fields).length, 'settings fields on the form').to.be.greaterThan(5);
    });
  });

  it('returns every changed value after a save', () => {
    cy.openCodecheckSettings();

    changeEveryField().then((expected) => {
      cy.saveCodecheckSettings();
      cy.openCodecheckSettings();

      captureFields().then((actual) => {
        // Compared per field so a failure names the setting that was lost.
        Object.keys(expected).forEach((name) => {
          expect(actual, `field ${name} still on the form`).to.have.property(name);
          expect(actual[name], `value of ${name} survived the save`)
            .to.deep.equal(expected[name]);
        });
      });
    });
  });

  /**
   * The round-trip above deliberately leaves checkbox-group membership alone,
   * so the one multi-value setting is recorded but never varied. The config
   * versions are the group that matters: what is stored is narrowed on save
   * and resolved on read, and neither side is pinned by anything else. An
   * empty selection is the interesting case — nothing is posted at all, so the
   * row is written empty and the form must still offer the default.
   */
  it('keeps a config version unticked, and offers the default when none is left', () => {
    const versions = () => cy.get('input[name="codecheckEnabledConfigVersions[]"]');

    cy.openCodecheckSettings();
    versions().should('have.length.greaterThan', 1);

    // Tick every version, so the state under test is one this save produced.
    versions().check({ force: true });
    cy.saveCodecheckSettings();
    cy.openCodecheckSettings();
    versions().each(($el) => expect($el[0].checked, `${$el.val()} ticked`).to.be.true);

    versions().first().uncheck({ force: true });
    versions().first().invoke('val').then((unticked) => {
      cy.saveCodecheckSettings();
      cy.openCodecheckSettings();

      versions().each(($el) => {
        expect($el[0].checked, `${$el.val()} after unticking ${unticked}`)
          .to.equal($el.val() !== unticked);
      });
    });

    // Nothing ticked posts no value at all; the default stands in on read.
    versions().uncheck({ force: true });
    cy.saveCodecheckSettings();
    cy.openCodecheckSettings();
    versions().filter(':checked').should('have.length', 1);
    versions().filter(':checked').should('have.value', '1.0');

    // The dataset ships no row at all for this setting, and no form can put
    // that state back; the after() hook's save records the default the form
    // renders, which resolves to the same list.
  });

  it('returns the original values once they are written back', () => {
    cy.openCodecheckSettings();
    applyFields(originalFields);
    cy.saveCodecheckSettings();

    cy.openCodecheckSettings();
    captureFields().then((actual) => {
      Object.keys(originalFields).forEach((name) => {
        if (DO_NOT_MODIFY.includes(name)) {
          return;
        }
        expect(actual[name], `${name} restored`).to.deep.equal(originalFields[name]);
      });
    });
  });
});
