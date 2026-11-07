import { describe, expect, it } from 'vitest';
import { isReachableSignedOut } from '@Helpers';
import { DiagnosticModuleRoutes } from '@DiagnosticModule';
import { SITE_ROUTE_NAMES } from '@SiteModule';

/* AM15 (rnc-1 F3): the diagnostic takes the site CTA's path and route name, open to everyone. */
describe('DiagnosticModuleRoutes', () => {
  it("answers the site CTA's destination", () => {
    expect(DiagnosticModuleRoutes.map((route) => [route.path, route.name])).toEqual([
      ['/diagnostico', SITE_ROUTE_NAMES.diagnostic],
    ]);
  });

  it('is open to a signed-out visitor', () => {
    expect(isReachableSignedOut({ meta: DiagnosticModuleRoutes[0]!.meta ?? {} })).toBe(true);
  });
});
