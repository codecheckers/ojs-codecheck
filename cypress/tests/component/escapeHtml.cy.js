import { escapeHtml } from '../../../resources/js/escapeHtml.js';

/**
 * OJS renders a dialog's message as markup, and the plugin builds that markup
 * as a string — so a user's name, a file name or a translation reaches it as
 * HTML unless it goes through here.
 */
describe('escapeHtml', () => {
  it('turns markup into text', () => {
    expect(escapeHtml('<img src=x onerror="alert(1)">')).to.equal(
      '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;'
    );
  });

  it('escapes both quotes, so the result is safe inside an attribute value', () => {
    expect(escapeHtml(`a"b'c`)).to.equal('a&quot;b&#39;c');
  });

  it('escapes the ampersand first, so nothing is escaped twice', () => {
    expect(escapeHtml('&lt; & <')).to.equal('&amp;lt; &amp; &lt;');
  });

  it('returns the empty string for null and undefined', () => {
    expect(escapeHtml(null)).to.equal('');
    expect(escapeHtml(undefined)).to.equal('');
  });

  it('renders numbers as text', () => {
    expect(escapeHtml(42)).to.equal('42');
  });
});
