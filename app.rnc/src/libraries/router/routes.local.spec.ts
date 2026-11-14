import { describe, expect, it } from 'vitest';
import type { RouteRecordRaw } from 'vue-router';
import localRoutes from './routes.local';

/*
 * rnc-4 R17, A24: every screen of an opt-in module declares it (`meta.module`), and
 * `attachAuthGuard` shows not-found when the firm has it off. One place says which module owns which
 * screens — the same split the API's `module:<key>` middleware draws.
 */
function moduleOf(name: string): unknown {
  const route = localRoutes.find((candidate) => candidate.name === name) as RouteRecordRaw | undefined;

  if (!route) throw new Error(`no route ${name}`);

  return (route.meta as { module?: string } | undefined)?.module;
}

describe('localRoutes — modules per firm (rnc-4 R17)', () => {
  it.each([
    ['partners-pt-BR', 'partners'],
    ['referral-trail-pt-BR', 'partners'],
    ['network-ledger-pt-BR', 'partners'],
    ['portal-pt-BR', 'partners'],
    ['repricing-pt-BR', 'portfolio'],
    ['nfe-readiness-pt-BR', 'fiscal'],
    ['supplier-credit-pt-BR', 'fiscal'],
    ['nfe-imports-pt-BR', 'fiscal'],
    ['portfolio-radar-pt-BR', 'radar'],
    ['contract-alert-pt-BR', 'contracts'],
    ['assistant-pt-BR', 'assistant'],
    ['assistant-knowledge-en', 'assistant'],
    ['briefing-pt-BR', 'briefing'],
    ['brands-en', 'brands'],
    ['channel-en', 'channel'],
    ['management-channel-pt-BR', 'channel'],
  ])('closes %s behind `%s`', (name, module) => {
    expect(moduleOf(name)).toBe(module);
  });

  it.each(['pipeline-pt-BR', 'agenda-pt-BR', 'metrics-pt-BR', 'site-diagnostic'])(
    'leaves the core screen %s open',
    (name) => {
      expect(moduleOf(name)).toBeUndefined();
    }
  );

  it('declares a module on every route of the opt-in sets, both languages alike', () => {
    const withModule = localRoutes.filter((route) => (route.meta as { module?: string } | undefined)?.module);

    for (const route of withModule) {
      const twin = String(route.name).replace(/-(pt-BR|en)$/, (_, locale) => (locale === 'en' ? '-pt-BR' : '-en'));
      const twinRoute = localRoutes.find((candidate) => candidate.name === twin);

      if (twinRoute) expect(moduleOf(twin), twin).toBe((route.meta as { module: string }).module);
    }
  });

  /* `/gestao` lands on the briefing; without `briefing` the Gestão link opens the metrics instead. */
  it('gives the management landing a fallback for when the briefing is off', () => {
    const landing = localRoutes.filter((route) => String(route.name).startsWith('management-home'));

    expect(landing.map((route) => [route.name, (route.meta as { moduleFallback?: string })?.moduleFallback])).toEqual([
      ['management-home-en', '/management/metrics'],
      ['management-home-pt-BR', '/gestao/metricas'],
    ]);
  });
});
