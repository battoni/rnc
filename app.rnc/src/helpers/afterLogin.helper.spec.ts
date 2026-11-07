import { describe, expect, it } from 'vitest';
import { intendedPathFrom, REDIRECT_QUERY_KEY } from './afterLogin.helper';

/*
 * A login screen that forwards wherever its query string points is an open redirect, and a shared
 * link is exactly how someone reaches one: the URL looks like this site, the sign-in is real, and
 * the page that opens afterwards is not ours. So the rule is a whitelist of one shape — a plain
 * `/path` — rather than a blacklist of the tricks people know about today.
 */
describe('intendedPathFrom', () => {
  it('follows a path on this site', () => {
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: '/users' })).toBe('/users');
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: '/ideas?area=api' })).toBe('/ideas?area=api');
  });

  it('refuses a protocol-relative URL, which is the one that looks like a path', () => {
    // `//evil.test` is the whole attack: it starts with a slash, so a naive check passes it, and
    // the browser reads it as "same scheme, different host".
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: '//evil.test' })).toBeNull();
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: '//evil.test/users' })).toBeNull();
  });

  it('refuses an absolute URL, whatever the scheme', () => {
    for (const hostile of [
      'https://evil.test/users',
      'http://evil.test',
      'javascript:alert(1)',
      'data:text/html,<script>alert(1)</script>',
    ]) {
      expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: hostile }), hostile).toBeNull();
    }
  });

  it('refuses anything that is not a string, and an absent key', () => {
    expect(intendedPathFrom({})).toBeNull();
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: null })).toBeNull();
    expect(intendedPathFrom({ other: '/users' })).toBeNull();
  });

  /*
   * Vue Router hands back an array when a key repeats — `?redirect=/users&redirect=//evil.test`.
   * Taking the first is deliberate and it is still checked: the shape decides, not the position.
   */
  it('reads the first of a repeated key, and still checks it', () => {
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: ['/users', '//evil.test'] })).toBe('/users');
    expect(intendedPathFrom({ [REDIRECT_QUERY_KEY]: ['//evil.test', '/users'] })).toBeNull();
  });
});
