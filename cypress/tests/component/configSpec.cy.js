import { missingMandatoryFields } from '../../../resources/js/configSpec.js';

/**
 * What version 2.0 of the CODECHECK config file specification requires of a
 * record. The form only warns with it, so these pin which fields are named,
 * not whether anything is refused.
 */
const complete = () => ({
  submission: {
    title: 'A paper',
    authors: [{ name: 'Josiah Carberry', orcid: 'https://orcid.org/0000-0002-1825-0097' }],
    doi: '10.1234/paper',
  },
  metadata: {
    manifest: [{ file: 'figure2.png' }],
    codecheckers: [{ name: 'Stephen J. Eglen', orcid: '0000-0001-8607-8025' }],
    summary: 'Everything reproduced.',
    certificate: '2025-042',
    report: 'https://doi.org/10.5281/zenodo.1',
  },
});

const keys = (version, record) =>
  missingMandatoryFields(version, record).map(({ key }) => key.replace('plugins.generic.codecheck.configSpec.missing.', ''));

describe('missingMandatoryFields', () => {
  it('names nothing for a complete 2.0 record', () => {
    expect(missingMandatoryFields('2.0', complete())).to.deep.equal([]);
  });

  it('names every field an empty 2.0 record lacks', () => {
    expect(keys('2.0', { submission: {}, metadata: {} })).to.deep.equal([
      'title', 'authors', 'reference', 'manifest', 'codechecker', 'summary', 'certificate', 'report',
    ]);
  });

  it('names the authors without an ORCID iD', () => {
    const record = complete();
    record.submission.authors = [
      { name: 'With a URI', orcid: 'https://orcid.org/0000-0002-1825-0097' },
      { name: 'Empty', orcid: '' },
      { name: 'Blank', orcid: '  ' },
      { name: 'Null', orcid: null },
    ];

    expect(missingMandatoryFields('2.0', record)).to.deep.equal([{
      key: 'plugins.generic.codecheck.configSpec.missing.authorOrcid',
      params: { names: 'Empty, Blank, Null' },
    }]);
  });

  it('takes only YYYY-NNN as a certificate identifier', () => {
    const record = complete();
    ['2025-42', '25-042', 'CODECHECK 2025-042', 'CODECHECK-2025-042', ' 2025-042 '].forEach((certificate) => {
      record.metadata.certificate = certificate;
      expect(keys('2.0', record), certificate).to.deep.equal(['certificate']);
    });
  });

  it('takes a sequence number past 999, as the register numbers them', () => {
    const record = complete();
    record.metadata.certificate = '2025-1000';
    expect(missingMandatoryFields('2.0', record)).to.deep.equal([]);
  });

  it('judges an author\'s ORCID iD as the file carries it', () => {
    const record = complete();
    record.submission.authors = [
      { name: 'Bare address', orcid: 'https://orcid.org/' },
      { name: 'Sandbox address', orcid: 'https://sandbox.orcid.org/0000-0002-1825-0097' },
      { name: 'Bare iD', orcid: '0000-0002-1825-0097' },
    ];

    expect(missingMandatoryFields('2.0', record)).to.deep.equal([{
      key: 'plugins.generic.codecheck.configSpec.missing.authorOrcid',
      params: { names: 'Bare address' },
    }]);
  });

  it('treats blank text as missing', () => {
    const record = complete();
    record.metadata.summary = '   ';
    record.submission.doi = '';
    expect(keys('2.0', record)).to.deep.equal(['reference', 'summary']);
  });

  it('requires nothing of a version it has no rules for', () => {
    expect(missingMandatoryFields('2.1', { submission: {}, metadata: {} })).to.deep.equal([]);
  });
});
