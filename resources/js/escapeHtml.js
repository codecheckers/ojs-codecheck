const ENTITIES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};

/**
 * Escapes text for use inside HTML the plugin builds as a string — the message
 * of an OJS dialog, above all, which is rendered as markup.
 *
 * Quotes are escaped as well, so the result is safe inside an attribute value
 * (`placeholder="…"`, `href="…"`). Anything that is not markup the plugin wrote
 * itself — a translation, a name a user chose for themselves — goes through here.
 *
 * @param {*} text null and undefined become the empty string
 * @returns {string}
 */
export function escapeHtml(text) {
  return String(text ?? '').replace(/[&<>"']/g, (character) => ENTITIES[character]);
}
