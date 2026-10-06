/**
 * The reviewers assigned to a submission, whom its codecheckers are linked to
 * (#13): who they are, saving a GitHub username to one's account, and closing
 * a codechecker's review once the check is completed.
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
 * The codecheckers' reviews not submitted yet, once the check is completed.
 *
 * @returns {Promise<Array<{reviewAssignmentId: number, userId: number, name: string, round: number}>>}
 */
export async function fetchOpenCodecheckerReviews(submissionId) {
  const data = await getCodecheckJson('codecheckers/reviews', submissionId);
  return Array.isArray(data?.reviews) ? data.reviews : [];
}

/**
 * Closes a codechecker's review.
 *
 * @returns {Promise<{reviews?: Array, error?: string}>} the reviews still open, or why not
 */
export function closeCodecheckerReview(submissionId, reviewAssignmentId) {
  return postCodecheckers(`codecheckers/reviews/close?submissionId=${submissionId}`, { reviewAssignmentId });
}

/**
 * Saves a GitHub username to a reviewer's account that has none.
 *
 * @returns {Promise<{github?: string, error?: string}>} the stored username, or why not
 */
export function saveReviewerGithubUsername(submissionId, userId, github) {
  return postCodecheckers(`codecheckers/github?submissionId=${submissionId}`, { userId, github });
}

/**
 * POSTs to a codechecker endpoint, answering its body, or `{error}` as the
 * server worded it — which a dialog shows as it is.
 */
async function postCodecheckers(path, body) {
  try {
    const response = await fetch(`${pkp.context.apiBaseUrl}codecheck/${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Csrf-Token': pkp.currentUser.csrfToken },
      body: JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({}));
    return response.ok && data.success
      ? data
      : { error: data.errorMessage || data.error || `HTTP ${response.status}` };
  } catch (error) {
    return { error: error.message };
  }
}
