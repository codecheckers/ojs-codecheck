// This file mocks the OJS pkp global object for component tests
import poSource from '../../locale/en/locale.po?raw';

/**
 * The message catalogue, read from the real `locale/en/locale.po` so the mock
 * knows which placeholders each message declares. Only single-line
 * `msgid`/`msgstr` pairs are read — the multi-line continuation syntax is used
 * by the file header alone, which carries no key.
 */
const messages = (() => {
  const catalogue = {};
  const pattern = /^msgid "((?:[^"\\]|\\.)*)"\r?\nmsgstr "((?:[^"\\]|\\.)*)"/gm;
  let match;
  while ((match = pattern.exec(poSource)) !== null) {
    if (match[1] === '') continue;
    catalogue[unescapePo(match[1])] = unescapePo(match[2]);
  }
  return catalogue;
})();

function unescapePo(value) {
  return value.replace(/\\n/g, '\n').replace(/\\"/g, '"').replace(/\\\\/g, '\\');
}

const PLACEHOLDER_PATTERN = /\{\$([a-zA-Z0-9_]+)\}/g;
const PLUGIN_KEY_PREFIX = 'plugins.generic.codecheck.';

function declaredPlaceholders(message) {
  return Array.from(message.matchAll(PLACEHOLDER_PATTERN), (m) => m[1]);
}

/**
 * Stand-in for OJS's own `t()`.
 *
 * Messages without placeholders resolve to the locale key, so component specs
 * can assert on a stable identifier rather than on English copy that changes
 * whenever the wording is edited.
 *
 * Messages *with* placeholders resolve to the translated text with the
 * parameters substituted, because the rendered result is the thing worth
 * asserting on — a link built from `{$specLink}`, an error carrying
 * `{$errorMessage}`. Any mismatch between the message and the call throws, so
 * a placeholder that was renamed in `locale.po` but not at the call site (or
 * the reverse) fails the spec instead of silently rendering `{$specLink}` to
 * the user. A plugin key with no message at all throws for the same reason;
 * keys outside the plugin's own namespace come from OJS and are passed
 * through unchecked.
 */
function t(key, params = {}) {
  const message = messages[key];
  if (message === undefined) {
    // Components also use OJS's own keys (`common.loading` and friends), which
    // live in the core locale files rather than in the plugin's. Only the
    // plugin's own keys can be checked against the catalogue.
    if (key.startsWith(PLUGIN_KEY_PREFIX)) {
      throw new Error(
        `t('${key}') has no entry in locale/en/locale.po — the key is misspelled or the message is missing.`
      );
    }
    return key;
  }

  const expected = declaredPlaceholders(message);
  const provided = Object.keys(params);

  const missing = expected.filter((name) => !provided.includes(name));
  if (missing.length) {
    throw new Error(
      `t('${key}') is missing the parameter(s) ${missing.join(', ')} declared by its message: "${message}"`
    );
  }

  const unused = provided.filter((name) => !expected.includes(name));
  if (unused.length) {
    throw new Error(
      `t('${key}') was passed the parameter(s) ${unused.join(', ')}, which its message does not use: "${message}"`
    );
  }

  if (!expected.length) {
    return key;
  }

  return message.replace(PLACEHOLDER_PATTERN, (_, name) => params[name]);
}

if (typeof window !== 'undefined') {
  window.pkp = {
    currentUser: {
      csrfToken: 'test-csrf-token'
    },
    context: {
      apiBaseUrl: 'http://localhost:3000/api/v1/'
    },
    modules: {
      useLocalize: {
        useLocalize: () => ({
          t,
          localize: (obj) => obj
        })
      },
      useModal: () => ({
        openDialog: () => {}
      })
    },
    const: {
      WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: 3
    },
    registry: {
      getPiniaStore: () => ({
        selectedMenuState: null
      })
    }
  };
}
