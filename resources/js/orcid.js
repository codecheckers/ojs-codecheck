/**
 * An ORCID iD is sixteen digits in four hyphenated groups, the last character
 * of which may be `X`, and it carries an ISO 7064 MOD 11-2 check digit — so a
 * mistyped one can be caught before it is stored, which is what the codechecker
 * dialog does with it (#180).
 */

const BARE = /^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/;
const WITH_HOST = /^(?:https?:\/\/)?(?:www\.|sandbox\.)*orcid\.org\//i;

/**
 * Reduces what someone pasted to the bare `0000-0000-0000-0000` form, so an
 * iD copied out of the address bar is accepted as the one typed by hand.
 *
 * @param {*} value
 * @returns {string}
 */
export function normalizeOrcid(value) {
  return String(value ?? '')
    .trim()
    // ORCID serves the iD at orcid.org, www.orcid.org and sandbox.orcid.org,
    // and the profile page carries a query string an editor copies with it.
    .replace(WITH_HOST, '')
    .replace(/[?#].*$/, '')
    .replace(/\/$/, '')
    .toUpperCase();
}

/**
 * @param {*} value an iD in either form; the empty string is not an iD
 * @returns {boolean} whether it is well formed and its check digit agrees
 */
export function isValidOrcid(value) {
  const id = normalizeOrcid(value);
  if (!BARE.test(id)) {
    return false;
  }

  const digits = id.replace(/-/g, '');
  let total = 0;
  for (const digit of digits.slice(0, 15)) {
    total = (total + Number(digit)) * 2;
  }

  const expected = (12 - (total % 11)) % 11;
  return (expected === 10 ? 'X' : String(expected)) === digits[15];
}
