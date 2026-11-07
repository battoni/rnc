import type { RouteLocationNormalized, RouteLocationNormalizedLoaded } from 'vue-router';
import { getLoginPath } from './i18n/getLoginPath.helper';

/**
 * Where a person should land once they are signed in.
 *
 * The auth guard parks the destination here on the way out, and this reads it back on the way in.
 * Both halves live in this file on purpose: the rule that makes it safe is one rule, and a second
 * copy of it is a second chance to get it wrong.
 */
const REDIRECT_QUERY_KEY = 'redirect';

/**
 * Only a path on this site is ever followed.
 *
 * A value naming a host — `//evil.test`, `https://evil.test`, anything that is not a plain
 * `/path` — is discarded. A login screen that forwards wherever its query string points is an
 * open redirect with extra steps, and a shared link is exactly how someone would reach it.
 */
function intendedPathFrom(query: RouteLocationNormalizedLoaded['query']): string | null {
  const REQUESTED = query?.[REDIRECT_QUERY_KEY];
  const PATH = Array.isArray(REQUESTED) ? REQUESTED[0] : REQUESTED;

  if (typeof PATH !== 'string') return null;

  const IS_RELATIVE_PATH = PATH.startsWith('/') && !PATH.startsWith('//');

  return IS_RELATIVE_PATH ? PATH : null;
}

/**
 * The login screen, carrying where the person was trying to go.
 *
 * Without this the destination is simply dropped: someone opening a link to an internal screen
 * signs in and lands on Home, with no way to know what they were sent to look at. A shared link
 * is the ordinary way that happens.
 *
 * Home is dropped rather than remembered — sending someone to the login screen and then back to
 * the page they would have landed on anyway adds a parameter to the URL and changes nothing. The
 * login screen itself is dropped for the obvious reason.
 *
 * One function for every exit to the login screen: the router guard and `TheRouteGuard` each had
 * their own, and the component's pushed a hard-coded `/entrar` that dropped the destination and
 * ignored the language.
 */
function loginWithReturnTo(to: Pick<RouteLocationNormalized, 'fullPath'> | undefined): {
  path: string;
  query?: Record<string, string>;
} {
  // Asked per navigation, never once at import. The logout in TheNavbar sent people to `/login`
  // while this guard sent them to `/entrar` — the same screen on two URLs, and which one you saw
  // depended on how you got there. A module-scope const would answer for whichever locale loaded
  // first, which is the same class of bug one level down.
  const LOGIN_PATH = getLoginPath();
  const DESTINATION = to?.fullPath;

  const IS_WORTH_REMEMBERING = typeof DESTINATION === 'string' && DESTINATION !== '/' && DESTINATION !== LOGIN_PATH;

  if (!IS_WORTH_REMEMBERING) return { path: LOGIN_PATH };

  return { path: LOGIN_PATH, query: { [REDIRECT_QUERY_KEY]: DESTINATION } };
}

export { intendedPathFrom, loginWithReturnTo, REDIRECT_QUERY_KEY };
