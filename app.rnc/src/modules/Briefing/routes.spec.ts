import { describe, expect, it } from 'vitest';
import { BriefingModuleRoutes } from './routes';

/*
 * "Briefing do dia" (rnc-3 F9, R26): Reinaldo's screen behind `briefing.read`, in both languages,
 * so `attachAuthGuard` sends anyone without the key (the team, a partner) to Home.
 */
describe('BriefingModuleRoutes', () => {
  it('declares the briefing in both languages behind `briefing.read`', () => {
    expect(
      BriefingModuleRoutes.map((route) => [route.path, route.name, (route.meta as { permission?: string }).permission])
    ).toEqual([
      ['/ceo/briefing', 'briefing-en', 'briefing.read'],
      ['/reinaldo/briefing', 'briefing-pt-BR', 'briefing.read'],
    ]);
  });
});
