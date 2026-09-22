/**
 * The author's own entries in a CODECHECK record, as the submission wizard's
 * textareas hold them: one value per line.
 *
 * Only entries marked `providedByAuthor` are returned, and that is the whole
 * point. `CodecheckAuthorMetadata::merge()` treats the submitted list as the
 * author's complete list: an entry missing from it that is theirs is deleted,
 * and an entry present in it becomes theirs. So seeding the field with
 * everything would hand the codechecker's own additions to the author, who
 * could then delete them; seeding it with nothing — which is what the wizard
 * did before issue #170 — made every save look like the author had removed
 * everything they ever entered.
 *
 * @param {Array<object>|undefined} entries the stored entries
 * @param {string} key `url` for repositories, `file` for the manifest
 * @returns {string} one value per line, ready for the textarea
 */
export function authorProvidedLines(entries, key) {
  return (Array.isArray(entries) ? entries : [])
    .filter(entry => entry && entry.providedByAuthor)
    .map(entry => String(entry[key] ?? '').trim())
    .filter(value => value !== '')
    .join('\n');
}
