import { describe, expect, it } from 'vitest';
import { RadarModuleRoutes } from './routes';

/*
 * "Radar da carteira" (rnc-2 F10, D14): Reinaldo's screen behind `radar.manage`, in both languages,
 * so `attachAuthGuard` sends anyone without the key (the team) to Home.
 */
describe('RadarModuleRoutes', () => {
  it('declares the Radar in both languages behind `radar.manage`', () => {
    expect(
      RadarModuleRoutes.map((route) => [route.path, route.name, (route.meta as { permission?: string }).permission])
    ).toEqual([
      ['/management/radar', 'portfolio-radar-en', 'radar.manage'],
      ['/gestao/radar', 'portfolio-radar-pt-BR', 'radar.manage'],
    ]);
  });
});
