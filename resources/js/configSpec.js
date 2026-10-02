/**
 * What each version of the CODECHECK config file specification requires of a
 * `codecheck.yml`, as far as the metadata form can tell before generating one.
 *
 * Only a warning reads this: the form still saves a record that lacks one of
 * these, and publishing is not blocked. Several of the fields are not the
 * codechecker's to fill in — the authors' ORCID iDs and the DOI come from OJS,
 * and the DOI is often assigned just before publication — so refusing would
 * stop a check at a point nobody in the form can resolve.
 *
 * A future version is one more entry in `RULES`. A version with no entry
 * requires nothing that the form knows of.
 *
 * @see https://codecheck.org.uk/spec/config/2.0/
 */

import { normalizeOrcid } from './orcid.js';

// Marks a locale key for `i18nExtractKeys.vite.js` without translating it,
// which is what gets the message into the bundle's key list.
const tk = (key) => key;

// The specification's `YYYY-NNN`, as the file carries it. Stricter than
// `Constants::getRegisterCertificateUrl()`, which tolerates an older record's
// `CODECHECK-` prefix to build a link: `buildYaml()` writes the stored value
// verbatim, so a prefixed or padded identifier is one the file gets wrong. Three
// digits or more, as the register's numbering continues past 999.
const CERTIFICATE_IDENTIFIER = /^\d{4}-\d{3,}$/;

const isBlank = (value) => String(value ?? '').trim() === '';
const isEmpty = (list) => (list ?? []).length === 0;

/** A rule naming `key` when `isMissing` holds for the record. */
const requires = (key, isMissing) => (record) => (isMissing(record) ? {key} : null);

const RULES = {
  '2.0': [
    requires(tk('plugins.generic.codecheck.configSpec.missing.title'), ({submission}) => isBlank(submission.title)),
    requires(tk('plugins.generic.codecheck.configSpec.missing.authors'), ({submission}) => isEmpty(submission.authors)),
    ({submission}) => {
      const names = (submission.authors ?? [])
        // What the file carries: the iD with any address around it removed,
        // so a bare `https://orcid.org/` is no iD.
        .filter((author) => normalizeOrcid(author?.orcid) === '')
        .map((author) => author?.name ?? '');
      return names.length === 0 ? null : {
        key: tk('plugins.generic.codecheck.configSpec.missing.authorOrcid'),
        params: {names: names.join(', ')},
      };
    },
    requires(tk('plugins.generic.codecheck.configSpec.missing.reference'), ({submission}) => isBlank(submission.doi)),
    requires(tk('plugins.generic.codecheck.configSpec.missing.manifest'), ({metadata}) => isEmpty(metadata.manifest)),
    requires(tk('plugins.generic.codecheck.configSpec.missing.codechecker'), ({metadata}) => isEmpty(metadata.codecheckers)),
    requires(tk('plugins.generic.codecheck.configSpec.missing.summary'), ({metadata}) => isBlank(metadata.summary)),
    requires(
      tk('plugins.generic.codecheck.configSpec.missing.certificate'),
      ({metadata}) => !CERTIFICATE_IDENTIFIER.test(String(metadata.certificate ?? ''))
    ),
    requires(tk('plugins.generic.codecheck.configSpec.missing.report'), ({metadata}) => isBlank(metadata.report)),
  ],
};

/**
 * The fields `version` requires that the record does not have yet.
 *
 * @param {string} version
 * @param {{metadata: object, submission: object}} record
 * @returns {Array<{key: string, params?: object}>} a locale key naming each
 *   missing field, and the parameters its message takes
 */
export function missingMandatoryFields(version, {metadata = {}, submission = {}} = {}) {
  return (RULES[version] ?? [])
    .map((rule) => rule({metadata, submission}))
    .filter((missing) => missing !== null);
}
