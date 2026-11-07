import { describe, expect, it } from 'vitest';
import { PortalModuleRoutes } from './routes';

/*
 * The partner's portal (rnc-2 R3, A4): `/parceiro` and its English twin, each closed by the portal
 * key — so `attachAuthGuard` sends anyone without it to Home, and nothing staff-only opens here.
 */
describe('PortalModuleRoutes', () => {
  it('declares the landing in both languages, behind portal.access', () => {
    expect(
      PortalModuleRoutes.map((route) => [route.path, route.name, (route.meta as { permission?: string }).permission])
    ).toEqual([
      ['/partner', 'portal-en', 'portal.access'],
      ['/parceiro', 'portal-pt-BR', 'portal.access'],
    ]);
  });
});
