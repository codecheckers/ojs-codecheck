/**
 * A GitHub username, which is what a register issue is assigned to (#186).
 * Mirrors `CodecheckCodecheckers::normalizeGithubUsername()` and
 * `isGithubUsername()` in PHP; the two must agree, or the dialog accepts what
 * the server refuses.
 */

const USERNAME = /^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/;
const WITH_HOST = /^(?:https?:\/\/)?(?:www\.)?github\.com\//i;

/**
 * Reduces what someone pasted to the bare name: `@name`, as the community
 * list and GitHub's mentions write it, or the profile's address.
 *
 * @param {*} value
 * @returns {string}
 */
export function normalizeGithubUsername(value) {
  return String(value ?? '')
    .trim()
    .replace(WITH_HOST, '')
    .replace(/[?#].*$/, '')
    .replace(/\/+$/, '')
    .replace(/^@+/, '');
}

/**
 * @param {*} value a username in any of the forms above; the empty string is not one
 * @returns {boolean}
 */
export function isValidGithubUsername(value) {
  return USERNAME.test(normalizeGithubUsername(value));
}
