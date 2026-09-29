import { isValidOrcid, normalizeOrcid } from '../../../resources/js/orcid.js';

/**
 * The codechecker dialog checks an ORCID iD before it is stored (#180). The
 * check digit is what makes a mistyped iD distinguishable from a correct one;
 * the examples below are ORCID's own published test iDs.
 */
describe('isValidOrcid', () => {
  it('accepts a well-formed iD', () => {
    expect(isValidOrcid('0000-0002-1825-0097')).to.be.true;
    expect(isValidOrcid('0000-0001-5109-3700')).to.be.true;
  });

  it('accepts X as the check digit', () => {
    expect(isValidOrcid('0000-0002-1694-233X')).to.be.true;
  });

  it('refuses an iD whose check digit does not agree', () => {
    expect(isValidOrcid('0000-0002-1825-0098')).to.be.false;
  });

  it('refuses anything that is not sixteen digits in four groups', () => {
    expect(isValidOrcid('0000-0002-1825-009')).to.be.false;
    expect(isValidOrcid('0000000218250097')).to.be.false;
    expect(isValidOrcid('not an orcid')).to.be.false;
  });

  it('refuses the empty value, which the dialog treats as "none given" instead', () => {
    expect(isValidOrcid('')).to.be.false;
    expect(isValidOrcid(null)).to.be.false;
  });

  it('accepts an iD copied out of the address bar', () => {
    expect(isValidOrcid('https://orcid.org/0000-0002-1825-0097')).to.be.true;
    expect(isValidOrcid('https://sandbox.orcid.org/0000-0002-1825-0097/')).to.be.true;
  });
});

describe('normalizeOrcid', () => {
  it('reduces a pasted address to the bare iD, which is what is stored', () => {
    expect(normalizeOrcid('  https://orcid.org/0000-0002-1694-233x ')).to.equal('0000-0002-1694-233X');
  });

  it('leaves a bare iD alone', () => {
    expect(normalizeOrcid('0000-0002-1825-0097')).to.equal('0000-0002-1825-0097');
  });
});
