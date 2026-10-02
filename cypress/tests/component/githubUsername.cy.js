import { isValidGithubUsername, normalizeGithubUsername } from '../../../resources/js/githubUsername.js';

/**
 * Mirrors `CodecheckCodecheckers::normalizeGithubUsername()` / `isGithubUsername()`
 * — the cases are the PHPUnit ones, so the browser and the server agree (#186).
 */
describe('githubUsername', () => {
  ['nuest', '@nuest', '  nuest  ', 'https://github.com/nuest', 'github.com/nuest/?tab=repositories', 'https://www.github.com/nuest']
    .forEach((pasted) => {
      it(`reads ${JSON.stringify(pasted)} as nuest`, () => {
        expect(normalizeGithubUsername(pasted)).to.equal('nuest');
        expect(isValidGithubUsername(pasted)).to.equal(true);
      });
    });

  ['', 'daniel nuest', '-nuest', 'nuest-', 'nu--est', 'a'.repeat(40), 'nu_est', 'github.com/codecheckers/register']
    .forEach((value) => {
      it(`refuses ${JSON.stringify(value)}`, () => {
        expect(isValidGithubUsername(value)).to.equal(false);
      });
    });

  it('accepts thirty-nine characters and single hyphens', () => {
    expect(isValidGithubUsername('a'.repeat(39))).to.equal(true);
    expect(isValidGithubUsername('Nathan-Skene-2')).to.equal(true);
  });
});
