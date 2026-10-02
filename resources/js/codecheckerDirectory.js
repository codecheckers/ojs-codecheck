/**
 * The journal's directory of codecheckers and the GitHub username suggested
 * for an ORCID iD (#186), for the "add codechecker" dialog.
 *
 * Both are conveniences: the dialog works without either, so a request that
 * fails — or that the caller's role may not make — answers nothing rather
 * than an error.
 */

async function get(path) {
  const response = await fetch(pkp.context.apiBaseUrl + 'codecheck/' + path, {
    headers: {
      'Content-Type': 'application/json',
      'X-Csrf-Token': pkp.currentUser.csrfToken
    }
  });
  if (!response.ok) {
    return null;
  }
  return response.json();
}

/**
 * @returns {Promise<Array<{name: string, orcid: string, github: string}>>}
 */
export async function fetchCodecheckerDirectory() {
  try {
    const data = await get('codecheckers');
    return Array.isArray(data?.codecheckers) ? data.codecheckers : [];
  } catch (e) {
    return [];
  }
}

/**
 * @param {string} orcid a bare, valid ORCID iD
 * @returns {Promise<{github: string, source: string}|null>}
 */
export async function lookupGithubUsername(orcid) {
  try {
    const data = await get('codecheckers/lookup?orcid=' + encodeURIComponent(orcid));
    return data?.github ? { github: data.github, source: data.source } : null;
  } catch (e) {
    return null;
  }
}
