import { authorProvidedLines } from '../../../resources/js/authorEntries.js';

/**
 * Issue #170. What the submission wizard puts in its textareas is what
 * `CodecheckAuthorMetadata::merge()` reads as the author's complete list, so
 * the contents of these two lines decide whether entries survive a save.
 */
describe('authorProvidedLines', () => {
  const REPOSITORIES = [
    {url: 'https://github.com/author/one', providedByAuthor: true},
    {url: 'https://github.com/codechecker/added'},
    {url: 'https://github.com/author/two', providedByAuthor: true, hidden: true},
  ];

  it('returns the author\'s own entries, one per line', () => {
    expect(authorProvidedLines(REPOSITORIES, 'url')).to.equal(
      'https://github.com/author/one\nhttps://github.com/author/two'
    );
  });

  /**
   * An entry the codechecker added must not come back in the author's list:
   * merge() marks everything submitted as `providedByAuthor`, so seeding it
   * here would hand that entry to the author, who could then delete it.
   */
  it('leaves out what the codechecker added', () => {
    expect(authorProvidedLines(REPOSITORIES, 'url')).to.not.contain('codechecker/added');
  });

  /** A hidden repository is still the author's, and still has to round-trip. */
  it('includes an entry the codechecker hid', () => {
    expect(authorProvidedLines(REPOSITORIES, 'url')).to.contain('https://github.com/author/two');
  });

  it('reads the manifest by its own key', () => {
    expect(
      authorProvidedLines(
        [
          {file: 'figure2.png', providedByAuthor: true},
          {file: 'added-by-codechecker.png'},
        ],
        'file'
      )
    ).to.equal('figure2.png');
  });

  it('writes a manifest comment the way the wizard field reads it back', () => {
    expect(
      authorProvidedLines(
        [
          {file: 'figure1.png', comment: 'Figure 1', providedByAuthor: true},
          {file: 'table1.csv', comment: '', providedByAuthor: true},
        ],
        'file',
        'comment'
      )
    ).to.equal('figure1.png - Figure 1\ntable1.csv');
  });

  it('skips entries with no value rather than emitting a blank line', () => {
    expect(
      authorProvidedLines(
        [
          {url: '   ', providedByAuthor: true},
          {url: 'https://github.com/author/one', providedByAuthor: true},
        ],
        'url'
      )
    ).to.equal('https://github.com/author/one');
  });

  it('survives a record with no entries at all', () => {
    expect(authorProvidedLines(undefined, 'url')).to.equal('');
    expect(authorProvidedLines(null, 'url')).to.equal('');
    expect(authorProvidedLines([], 'url')).to.equal('');
  });
});
