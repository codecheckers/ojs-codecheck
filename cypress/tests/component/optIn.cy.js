import { isOptedIn, notOptedInReason, participationReason } from '../../../resources/js/optIn.js';

/**
 * The one rule every backend view asks about a submission's part in a
 * CODECHECK (#34). The CODECHECK tab, the review stage and the publication
 * Metadata page used to decide it separately, and explained the same
 * submission differently.
 */
describe('isOptedIn', () => {
  it('is the truthy flag', () => {
    expect(isOptedIn({ codecheckOptIn: true })).to.be.true;
    expect(isOptedIn({ codecheckOptIn: 1 })).to.be.true;
    expect(isOptedIn({ codecheckOptIn: false })).to.be.false;
    expect(isOptedIn({ codecheckOptIn: null })).to.be.false;
    expect(isOptedIn({})).to.be.false;
    expect(isOptedIn(null)).to.be.false;
  });
});

describe('notOptedInReason', () => {
  it('has no reason for a submission that takes part, whatever the mode', () => {
    ['opt-in', 'opt-out', 'mandatory'].forEach((mode) => {
      expect(notOptedInReason({ codecheckOptIn: true }, mode)).to.be.null;
    });
  });

  it('says the author did not opt in, in an opt-in or a mandatory journal', () => {
    expect(notOptedInReason({ codecheckOptIn: false }, 'opt-in'))
      .to.equal('plugins.generic.codecheck.warning.notOptedIn');
    expect(notOptedInReason({ codecheckOptIn: false }, 'mandatory'))
      .to.equal('plugins.generic.codecheck.warning.notOptedIn');
  });

  it('says the author opted out, in an opt-out journal', () => {
    expect(notOptedInReason({ codecheckOptIn: false }, 'opt-out'))
      .to.equal('plugins.generic.codecheck.warning.optedOut');
  });

  it('says no choice was recorded when the flag is unset, whatever the mode', () => {
    ['opt-in', 'opt-out', 'mandatory'].forEach((mode) => {
      expect(notOptedInReason({ codecheckOptIn: null }, mode))
        .to.equal('plugins.generic.codecheck.warning.noChoice');
      expect(notOptedInReason({}, mode))
        .to.equal('plugins.generic.codecheck.warning.noChoice');
    });
  });
});

/**
 * The codechecker is told whether the check is required by the journal or
 * optional (#31).
 */
describe('participationReason', () => {
  it('says the journal requires it in a mandatory journal', () => {
    expect(participationReason({ codecheckOptIn: true }, 'mandatory'))
      .to.equal('plugins.generic.codecheck.participation.mandatory');
  });

  it('says it is optional in an opt-in or an opt-out journal', () => {
    ['opt-in', 'opt-out'].forEach((mode) => {
      expect(participationReason({ codecheckOptIn: true }, mode))
        .to.equal('plugins.generic.codecheck.participation.optional');
    });
  });

  it('has nothing to say for a submission that takes no part', () => {
    ['opt-in', 'opt-out', 'mandatory'].forEach((mode) => {
      expect(participationReason({ codecheckOptIn: false }, mode)).to.be.null;
      expect(participationReason({}, mode)).to.be.null;
    });
  });
});
