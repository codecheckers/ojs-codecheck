import { doiUrl } from '../../../resources/js/isWebUrl.js';

/**
 * Issue #190. `doiUrl()` mirrors `Constants::bareDoi()`, so the editor's link
 * to an existing check names the DOI the server resolves.
 */
describe('doiUrl', () => {
  [
    ['10.5281/zenodo.1', 'https://doi.org/10.5281/zenodo.1'],
    ['doi: 10.5281/zenodo.1', 'https://doi.org/10.5281/zenodo.1'],
    ['http://dx.doi.org/10.5281/zenodo.1', 'https://doi.org/10.5281/zenodo.1'],
    ['doi.org/10.5281/zenodo.1', 'https://doi.org/10.5281/zenodo.1'],
    ['10.5281', null],
    ['10.5281/zenodo 1', null],
    ['https://zenodo.org/records/1', null],
    [undefined, null],
  ].forEach(([value, expected]) => {
    it(`reads ${JSON.stringify(value)}`, () => {
      expect(doiUrl(value)).to.equal(expected);
    });
  });
});
