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
 * The manifest's lines carry the comment as well, `file - comment`, which is
 * how the wizard's field writes and reads them; without it an author who
 * reopened a draft found their comments gone.
 *
 * @param {Array<object>|undefined} entries the stored entries
 * @param {string} key `url` for repositories, `file` for the manifest
 * @param {string|null} commentKey `comment` for the manifest, else nothing
 * @returns {string} one value per line, ready for the textarea
 */
export function authorProvidedLines(entries, key, commentKey = null) {
  return (Array.isArray(entries) ? entries : [])
    .filter(entry => entry && entry.providedByAuthor)
    .map(entry => {
      const value = String(entry[key] ?? '').trim();
      return value ? manifestLine(value, commentKey ? entry[commentKey] : '') : '';
    })
    .filter(value => value !== '')
    .join('\n');
}

/**
 * Add lines to a wizard field's value without touching what is there: a line
 * whose key the field already holds is left out, so loading an existing check
 * twice, or one listing an address the author typed, adds nothing twice (#190).
 *
 * The field is the author's complete list (see above), so nothing is replaced
 * or removed here.
 *
 * @param {string} text the field's current value, one entry per line
 * @param {string[]} lines the entries to add, in the field's line format
 * @param {function(string): string} keyOf what identifies an entry
 * @returns {{text: string, added: number}}
 */
export function addLines(text, lines, keyOf = line => line) {
  const current = String(text ?? '').split('\n').map(line => line.trim()).filter(Boolean);
  const known = new Set(current.map(keyOf));
  const added = [];

  for (const line of lines.map(value => String(value ?? '').trim())) {
    if (line === '' || known.has(keyOf(line))) continue;
    known.add(keyOf(line));
    added.push(line);
  }

  return { text: [...current, ...added].join('\n'), added: added.length };
}

/** A manifest line as the wizard's field writes it: `file - comment`, or the file alone. */
export function manifestLine(file, comment) {
  const trimmed = String(comment ?? '').trim();
  return trimmed ? `${String(file).trim()} - ${trimmed}` : String(file).trim();
}

/**
 * A manifest line split into the file and the comment on it. Only the first
 * ` - ` separates them, as on the server
 * (`CodecheckAuthorMetadata::parseManifestLine()`): a comment may hold one.
 *
 * @param {string} line
 * @returns {{file: string, comment: string}}
 */
export function parseManifestLine(line) {
  const text = String(line ?? '');
  const at = text.indexOf(' - ');
  return at === -1
    ? { file: text.trim(), comment: '' }
    : { file: text.slice(0, at).trim(), comment: text.slice(at + 3).trim() };
}

/** The file a manifest line names. */
export function manifestFileOf(line) {
  return parseManifestLine(line).file;
}
