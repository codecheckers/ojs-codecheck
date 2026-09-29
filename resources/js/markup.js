/**
 * Building markup as a string, safely.
 *
 * OJS renders a dialog's message as markup, and the submission wizard's review
 * panel is written straight into `innerHTML` — so a user's name, a file name or
 * a translation reaches the page as HTML unless something escapes it. That used
 * to be an `escapeHtml()` call each author had to remember, and the two defects
 * #179 came from are what forgetting looks like: a closing tag dropped by a
 * missing `+`, and a name in the status history that could run script in an
 * editor's browser.
 *
 * So the rule here is the reverse. `html` escapes everything interpolated into
 * it, and markup the plugin wrote itself has to say so with `raw()`. A `raw()`
 * in a diff is the thing to look at twice; everything else is safe by
 * construction.
 */

const ENTITIES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};

const MARKUP = Symbol('codecheck.markup');

/**
 * Escapes text for use inside HTML the plugin builds as a string.
 *
 * Quotes are escaped as well, so the result is safe inside an attribute value
 * (`placeholder="…"`, `href="…"`). Prefer `html` — this is for the few places
 * that still concatenate.
 *
 * @param {*} text null and undefined become the empty string
 * @returns {string}
 */
export function escapeHtml(text) {
  return String(text ?? '').replace(/[&<>"']/g, (character) => ENTITIES[character]);
}

/**
 * Marks a string as markup the plugin built itself, so `html` interpolates it
 * unescaped. Never hand it a value that came from a user, a translation or the
 * server. A result of `html` is already marked and needs no `raw()`.
 *
 * @param {string|object} markup
 * @returns {object} an opaque marker
 */
export function raw(markup) {
  return isMarkup(markup) ? markup : { [MARKUP]: String(markup ?? '') };
}

function isMarkup(value) {
  return Boolean(value) && typeof value === 'object' && MARKUP in value;
}

function interpolate(value) {
  if (Array.isArray(value)) {
    return value.map(interpolate).join('');
  }
  return isMarkup(value) ? value[MARKUP] : escapeHtml(value);
}

/**
 * Tagged template for markup: `html`<p>${name}</p>`` escapes `name`, and
 * arrays are joined without a separator so a list of rows maps cleanly.
 *
 * The result is itself marked as markup, so one `html` nests inside another
 * without a `raw()` in between — which is what keeps `raw()` rare enough to
 * mean something.
 *
 * @returns {object} a marker; `toHtml()` turns it into the string
 */
export function html(strings, ...values) {
  return raw(strings.reduce(
    (markup, chunk, index) => markup + interpolate(values[index - 1]) + chunk
  ));
}

/**
 * The string, for handing to `innerHTML` or to OJS's dialog.
 *
 * @param {string|object} markup a marker, or a string that is already markup
 * @returns {string}
 */
export function toHtml(markup) {
  return isMarkup(markup) ? markup[MARKUP] : String(markup ?? '');
}

/**
 * A stand-in for a translated message's parameter whose value is markup.
 * It is a private-use character, so no message or address can contain it.
 *
 * @see htmlSentence
 */
export const MARKUP_PLACEHOLDER = 'codecheckMarkup';

/**
 * A translated sentence one of whose parameters is markup the plugin built —
 * a link, above all.
 *
 * One sentence is one key (see the i18n section of CLAUDE.md), so the link
 * label and the words around it cannot be separate messages. But `t()`
 * substitutes inside the message, so the result cannot be escaped afterwards
 * without escaping the link, nor beforehand without leaving the sentence
 * unescaped. The parameter is therefore passed as `MARKUP_PLACEHOLDER`, which
 * survives escaping, and is swapped for the markup here.
 *
 * @param {string} translated the result of `t(key, {name: MARKUP_PLACEHOLDER})`
 * @param {string|object} markup what the placeholder stands for
 * @returns {object} a marker
 */
export function htmlSentence(translated, markup) {
  // A function replacement, or `$&` and its friends in the markup would be
  // read as substitution patterns rather than inserted.
  return raw(escapeHtml(translated).replace(MARKUP_PLACEHOLDER, () => toHtml(markup)));
}
