import { describe, expect, it } from 'vitest';
import { isReachableSignedOut } from '@Helpers';
import { SiteRoutes } from '@SiteModule';

/*
 * AM15 (rnc-1): the public site answers everyone, signed in or not, outside the auth guard — a
 * member of the team opening the landing must not be bounced to the back office, and a visitor
 * must not be sent to the login screen.
 */
describe('SiteRoutes', () => {
  it('serves the landing and the sector pages', () => {
    expect(SiteRoutes.map((route) => route.path)).toEqual(['/', '/reforma/:slug']);
  });

  it('opens every site route to a signed-out visitor', () => {
    for (const route of SiteRoutes) {
      expect(isReachableSignedOut({ meta: route.meta ?? {} })).toBe(true);
    }
  });
});
