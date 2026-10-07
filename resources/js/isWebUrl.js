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

/**
 * The doi.org link for a DOI as `Constants::bareDoi()` reads one — bare,
 * `doi:10.…`, or a doi.org link — or null when the value names no DOI. The
 * mirror of that PHP rule, as `isWebUrl()` mirrors its own (#190).
 *
 * @param {string} value
 * @returns {?string}
 */
export function doiUrl(value) {
  const match = /^(?:doi:\s*|(?:https?:\/\/)?(?:dx\.)?doi\.org\/)?(10\.\d{4,9}\/\S+)$/i.exec(String(value ?? '').trim());
  return match ? `https://doi.org/${match[1]}` : null;
}
