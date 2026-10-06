/**
 * The reviewers assigned to a submission, whom its codecheckers are linked to
 * (#13), and saving a GitHub username to one's account.
 */

import { getCodecheckJson } from './codecheckApi.js';

/**
 * @param {number|string} submissionId
 * @returns {Promise<Array<{userId: number, name: string, orcid: string, github: string,
 *   doubleAnonymous: boolean, githubSuggestion: ?string}>>}
 */
export async function fetchAssignedReviewers(submissionId) {
  const data = await getCodecheckJson('codecheckers/reviewers', submissionId);
  return Array.isArray(data?.reviewers) ? data.reviewers : [];
}

/**
 * Saves a GitHub username to a reviewer's account.
 *
 * @returns {Promise<{github?: string, error?: string}>} the stored username, or why not
 */
export async function saveReviewerGithubUsername(submissionId, userId, github) {
  try {
    const response = await fetch(
      `${pkp.context.apiBaseUrl}codecheck/codecheckers/github?submissionId=${submissionId}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Csrf-Token': pkp.currentUser.csrfToken },
        body: JSON.stringify({ userId, github })
      }
    );
    const data = await response.json().catch(() => ({}));
    return response.ok && data.success
      ? { github: data.github }
      : { error: data.error || data.errorMessage || `HTTP ${response.status}` };
  } catch (error) {
    return { error: error.message };
  }
}
