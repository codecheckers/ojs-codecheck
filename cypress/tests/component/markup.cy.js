import { escapeHtml, html, raw, toHtml } from '../../../resources/js/markup.js';

/**
 * OJS renders a dialog's message as markup, and the wizard's review panel is
 * written into `innerHTML` — so a user's name, a file name or a translation
 * reaches the page as HTML unless it goes through here.
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

describe('html', () => {
  it('escapes an interpolated value', () => {
    expect(toHtml(html`<p>${'<img src=x>'}</p>`)).to.equal('<p>&lt;img src=x&gt;</p>');
  });

  it('leaves the markup around the values alone', () => {
    expect(toHtml(html`<p class="a">${'b'}</p>`)).to.equal('<p class="a">b</p>');
  });

  it('interpolates markup the plugin marked as its own', () => {
    expect(toHtml(html`<p>${raw('<a href="#">x</a>')}</p>`)).to.equal('<p><a href="#">x</a></p>');
  });

  it('escapes a value that only looks like a marker', () => {
    expect(toHtml(html`<p>${{ MARKUP: '<b>' }}</p>`)).to.equal('<p>[object Object]</p>');
  });

  it('joins an array without a separator, escaping each entry', () => {
    expect(toHtml(html`<p>${['<a>', '<b>']}</p>`)).to.equal('<p>&lt;a&gt;&lt;b&gt;</p>');
  });

  it('renders null and undefined as nothing', () => {
    expect(toHtml(html`<p>${null}${undefined}</p>`)).to.equal('<p></p>');
  });

  it('is safe inside an attribute value', () => {
    expect(toHtml(html`<a href="${'" onclick="alert(1)'}">x</a>`)).to.equal(
      '<a href="&quot; onclick=&quot;alert(1)">x</a>'
    );
  });

  /**
   * `html` answers a marker, so one nests inside another with no `raw()` in
   * between — which is what keeps `raw()` rare enough to be worth looking at.
   */
  it('nests inside itself without escaping the inner result', () => {
    const link = html`<a href="${'#'}">${'<b>'}</a>`;
    expect(toHtml(html`<p>${link}</p>`)).to.equal('<p><a href="#">&lt;b&gt;</a></p>');
  });

  it('is idempotent under raw()', () => {
    expect(toHtml(raw(html`<b>${'x'}</b>`))).to.equal('<b>x</b>');
  });
});

describe('toHtml', () => {
  it('passes a plain string through, for markup that was never marked', () => {
    expect(toHtml('<b>x</b>')).to.equal('<b>x</b>');
    expect(toHtml(null)).to.equal('');
  });
});
