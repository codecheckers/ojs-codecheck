/**
 * Whether a submission takes part in a CODECHECK, and if not, why — the one
 * rule every backend view asks, so the CODECHECK tab, the review stage and the
 * publication Metadata page cannot explain the same submission differently
 * (#34).
 *
 * "Opted in" is the truthy flag: the opt-in checkbox stores a boolean, and
 * opt-out and mandatory journals tick it at submission rather than reading an
 * unset flag as consent. An unset flag therefore means no choice was recorded
 * at all — typically a submission that predates the plugin being enabled.
 */

/** Marks a locale key for the extractor without translating it here. */
const tk = (key) => key;

/** Whether the submission takes part in a CODECHECK. */
export function isOptedIn(submission) {
  return !!submission?.codecheckOptIn;
}

/**
 * The locale key saying why the submission takes no part, or null when it
 * does.
 *
 * @param {object} submission carries `codecheckOptIn`
 * @param {string} mode the journal's CODECHECK mode: opt-in, opt-out or mandatory
 */
export function notOptedInReason(submission, mode) {
  if (isOptedIn(submission)) {
    return null;
  }
  const optIn = submission?.codecheckOptIn;
  if (optIn === null || optIn === undefined) {
    return tk('plugins.generic.codecheck.warning.noChoice');
  }
  return mode === 'opt-out'
    ? tk('plugins.generic.codecheck.warning.optedOut')
    : tk('plugins.generic.codecheck.warning.notOptedIn');
}

/**
 * The locale key saying whether the check is required by the journal or
 * optional, or null when the submission takes no part (#31).
 *
 * @param {object} submission carries `codecheckOptIn`
 * @param {string} mode the journal's CODECHECK mode: opt-in, opt-out or mandatory
 */
export function participationReason(submission, mode) {
  if (!isOptedIn(submission)) {
    return null;
  }
  return mode === 'mandatory'
    ? tk('plugins.generic.codecheck.participation.mandatory')
    : tk('plugins.generic.codecheck.participation.optional');
}
