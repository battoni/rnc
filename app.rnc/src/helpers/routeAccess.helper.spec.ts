import { describe, expect, it } from 'vitest';
import { isOpenRoute, isPublicRoute, isReachableSignedOut } from './routeAccess.helper';

/*
 * The router guard (`attachAuthGuard`) and the component guard (`TheRouteGuard`) both decide who may
 * see a route. They used to read `meta.isPublic` / `meta.isOpen` by hand, three times, and a rule
 * kept in three copies is a rule that one copy eventually gets wrong.
 */
describe('routeAccess.helper', () => {
  const PUBLIC = { meta: { isPublic: true } };
  const OPEN = { meta: { isOpen: true } };
  const PRIVATE = { meta: {} };

  it('names a public route by its meta', () => {
    expect(isPublicRoute(PUBLIC)).toBe(true);
    expect(isPublicRoute(OPEN)).toBe(false);
    expect(isPublicRoute(PRIVATE)).toBe(false);
  });

  it('names an open route by its meta', () => {
    expect(isOpenRoute(OPEN)).toBe(true);
    expect(isOpenRoute(PUBLIC)).toBe(false);
    expect(isOpenRoute(PRIVATE)).toBe(false);
  });

  // The not-found screen answers a signed-out person too, and is never replaced by the login screen.
  it('lets a signed-out person reach a public or an open route, and nothing else', () => {
    expect(isReachableSignedOut(PUBLIC)).toBe(true);
    expect(isReachableSignedOut(OPEN)).toBe(true);
    expect(isReachableSignedOut(PRIVATE)).toBe(false);
  });

  // `TheRouteGuard` reads the target before the router has resolved one.
  it('treats a route that is not there yet as private', () => {
    expect(isPublicRoute(undefined)).toBe(false);
    expect(isOpenRoute(undefined)).toBe(false);
    expect(isReachableSignedOut(undefined)).toBe(false);
  });
});
