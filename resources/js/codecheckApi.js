/**
 * Reading CODECHECK data from the plugin's endpoints, and moving the workflow
 * to the CODECHECK tab — shared by the panels outside that tab and the
 * codechecker dialog, so they read and fail the same way (#65).
 */

import { workflowStore } from './piniaStore.js';

/** The key of the CODECHECK item that main.js adds to the workflow menu. */
export const CODECHECK_MENU_KEY = 'codecheck';

/**
 * getCodecheckApi() for a submission-scoped endpoint.
 *
 * @param {string} endpoint relative to `api/v1/codecheck/`, e.g. `metadata`
 * @param {number|string} submissionId
 */
export function getCodecheckJson(endpoint, submissionId) {
  return getCodecheckApi(`${endpoint}?submissionId=${submissionId}`);
}

/**
 * A GET endpoint's answer, or an error already worded for a panel.
 * `error.status` carries the HTTP status, for a caller to whom some refusal
 * is an ordinary state.
 *
 * @param {string} path relative to `api/v1/codecheck/`, with its query string
 */
export async function getCodecheckApi(path) {
  const response = await fetch(
    `${pkp.context.apiBaseUrl}codecheck/${path}`,
    { headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken } }
  );
  // An error page need not be JSON: a PHP fatal answers HTML.
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.success === false) {
    const { t } = pkp.modules.useLocalize.useLocalize();
    const error = new Error(`${t('plugins.generic.codecheck.loadError')}: [HTTP ${response.status}] ${data.error ?? ''}`);
    error.status = response.status;
    throw error;
  }
  return data;
}

/** Moves the workflow to the CODECHECK tab, through the workflow store's own navigation. */
export function openCodecheckTab() {
  workflowStore()?.navigateToMenu(CODECHECK_MENU_KEY);
}
