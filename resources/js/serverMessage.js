/**
 * The sentence to show for a refused or failed request.
 *
 * A refusal from PKP (and from `roleRefusal()`) carries `errorMessage`, already
 * translated; the plugin's own answers carry `error`, the server's English.
 *
 * @param {object} data the parsed response body
 * @param {?string} fallback what to show when the body names neither
 * @returns {?string}
 */
export function serverMessage(data, fallback = null) {
  return data?.errorMessage || data?.error || fallback;
}
