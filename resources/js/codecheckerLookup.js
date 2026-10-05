/**
 * The GitHub username the CODECHECK community list gives for an ORCID iD
 * (#186), for the "add codechecker" dialog.
 *
 * A convenience: the dialog works without it, so a request that fails — or
 * that the caller's role may not make — answers nothing rather than an error.
 */

import { getCodecheckApi } from './codecheckApi.js';

/**
 * @param {string} orcid a bare, valid ORCID iD
 * @returns {Promise<string|null>}
 */
export async function lookupGithubUsername(orcid) {
  try {
    const data = await getCodecheckApi('codecheckers/lookup?orcid=' + encodeURIComponent(orcid));
    return data?.github || null;
  } catch (e) {
    return null;
  }
}
