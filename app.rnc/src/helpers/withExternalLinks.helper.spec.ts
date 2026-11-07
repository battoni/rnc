import { describe, expect, it } from 'vitest';
import { withExternalLinks } from '@Helpers';

// Ported from new.impressao (03c4a47). Tip content is stored without target/rel; the pair is added
// at render time so a stored target can never arrive without the rel that makes it safe.
describe('withExternalLinks', () => {
  it('opens a link in a new tab with the rel that makes it safe', () => {
    expect(withExternalLinks('<p>see <a href="https://x.test">here</a></p>')).toBe(
      '<p>see <a href="https://x.test" target="_blank" rel="noopener noreferrer">here</a></p>'
    );
  });

  it('never emits target without rel', () => {
    const RESULT = withExternalLinks('<a href="https://x.test">a</a>');

    expect(RESULT.includes('target="_blank"')).toBe(true);
    expect(RESULT.includes('rel="noopener noreferrer"')).toBe(true);
  });

  it('is idempotent, so a re-render never stacks attributes', () => {
    const ONCE = withExternalLinks('<a href="https://x.test">a</a>');

    expect(withExternalLinks(ONCE)).toBe(ONCE);
  });

  it('replaces a stored target instead of leaving two on the tag', () => {
    const RESULT = withExternalLinks('<a href="https://x.test" target="_self">a</a>');

    expect(RESULT.match(/target=/g)).toHaveLength(1);
    expect(RESULT.includes('target="_self"')).toBe(false);
  });

  it('handles several links and leaves other markup alone', () => {
    const RESULT = withExternalLinks('<p><a href="https://a.test">a</a> and <a href="https://b.test">b</a></p>');

    expect(RESULT.match(/rel="noopener noreferrer"/g)).toHaveLength(2);
    expect(RESULT.startsWith('<p>')).toBe(true);
  });

  it('leaves tags that only start with an a alone', () => {
    expect(withExternalLinks('<abbr title="x">x</abbr><aside>y</aside>')).toBe(
      '<abbr title="x">x</abbr><aside>y</aside>'
    );
  });

  it('returns an empty string for empty content', () => {
    expect(withExternalLinks('')).toBe('');
  });
});
