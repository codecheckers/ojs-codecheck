import { serverMessage } from '../../../resources/js/serverMessage.js';

describe('serverMessage', () => {
  it('prefers the translated sentence', () => {
    expect(serverMessage({ error: 'user.authorization.roleBasedAccessDenied', errorMessage: 'Not allowed.' })).to.eq('Not allowed.');
  });

  it('falls back to the server\'s own error, then to the given text', () => {
    expect(serverMessage({ error: 'Bad request' })).to.eq('Bad request');
    expect(serverMessage({}, 'Failed')).to.eq('Failed');
    expect(serverMessage(undefined)).to.eq(null);
  });
});
