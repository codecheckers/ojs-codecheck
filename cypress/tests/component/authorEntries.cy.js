import { addLines, authorProvidedLines, manifestFileOf, manifestLine, parseManifestLine } from '../../../resources/js/authorEntries.js';

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

/**
 * Issue #190. Loading an existing check adds to the author's list and never
 * replaces it, since the field is the author's complete list.
 */
describe('addLines', () => {
  it('adds new entries after the author\'s own', () => {
    expect(addLines('https://github.com/a/one', ['https://github.com/b/two'])).to.deep.equal({
      text: 'https://github.com/a/one\nhttps://github.com/b/two',
      added: 1,
    });
  });

  it('adds nothing twice', () => {
    expect(addLines('https://github.com/a/one\n', ['https://github.com/a/one', ' ', 'https://github.com/a/one']))
      .to.deep.equal({ text: 'https://github.com/a/one', added: 0 });
  });

  it('keeps the author\'s comment on a file the check also lists', () => {
    expect(addLines('fig.png - my comment', ['fig.png - Figure 1', 'table.csv'], manifestFileOf)).to.deep.equal({
      text: 'fig.png - my comment\ntable.csv',
      added: 1,
    });
  });

  it('fills an empty field', () => {
    expect(addLines('', ['a.png'])).to.deep.equal({ text: 'a.png', added: 1 });
  });
});

/** One reading of the wizard's `file - comment` line, the server's (#190). */
describe('manifest lines', () => {
  it('splits at the first separator only', () => {
    expect(parseManifestLine(' fig.png - left - right panels ')).to.deep.equal({ file: 'fig.png', comment: 'left - right panels' });
  });

  it('reads a line without a comment as the file alone', () => {
    expect(parseManifestLine('table.csv')).to.deep.equal({ file: 'table.csv', comment: '' });
    expect(manifestFileOf('table.csv - Table 1')).to.equal('table.csv');
  });

  it('writes the comment only when there is one', () => {
    expect(manifestLine(' fig.png ', ' Figure 1 ')).to.equal('fig.png - Figure 1');
    expect(manifestLine('fig.png', '  ')).to.equal('fig.png');
  });
});
