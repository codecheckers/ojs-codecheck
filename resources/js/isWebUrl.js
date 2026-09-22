/**
 * Whether an address may be stored and rendered as a repository link.
 *
 * The mirror of `Constants::isWebUrl()` in PHP, deliberately the same
 * expression: the scheme decides, case does not, and anything else —
 * `github.com/me/project`, `git@github.com:foo/bar.git`, `javascript://x` — is
 * refused. `new URL()` is not a substitute; it validates the syntax `scheme:…`
 * and says nothing about which scheme (issue #154).
 *
 * The two copies disagreeing is itself the bug this closes: the wizard used
 * `startsWith('http://')`, so an author could enter `HTTP://example.org`, be
 * told it was wrong, and have PHP accept it anyway (issue #170).
 *
 * @param {string} url
 * @returns {boolean}
 */
export function isWebUrl(url) {
  return /^https?:\/\//i.test(String(url ?? '').trim());
}
