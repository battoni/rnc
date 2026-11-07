import { describe, expect, it } from 'vitest';
import { isReachableSignedOut } from '@Helpers';
import { DIAGNOSTIC_ROUTE_NAMES, DiagnosticModuleRoutes } from '@DiagnosticModule';
import { SITE_ROUTE_NAMES } from '@SiteModule';

/*
 * AM15 (rnc-1 F3, F4): the diagnostic takes the site CTA's path and route name, and the "Relatório
 * de Impacto" the e-mail links lives at `/relatorio/:token` — both open to everyone.
 */
describe('DiagnosticModuleRoutes', () => {
  it("answers the site CTA's destination and the report's link", () => {
    expect(DiagnosticModuleRoutes.map((route) => [route.path, route.name])).toEqual([
      ['/diagnostico', SITE_ROUTE_NAMES.diagnostic],
      ['/relatorio/:token', DIAGNOSTIC_ROUTE_NAMES.report],
    ]);
  });

  it('is open to a signed-out visitor', () => {
    expect(DiagnosticModuleRoutes.every((route) => isReachableSignedOut({ meta: route.meta ?? {} }))).toBe(true);
  });
});
