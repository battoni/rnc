import { describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { BriefingModuleRoutes } from '@BriefingModule';
import { MetricsModuleRoutes } from '@MetricsModule';
import WorkspaceRoutes from './routes';

/*
 * Reinaldo's landing (rnc-3 F9, R26, A23): the rail's "Reinaldo" link and the home card open
 * `/gestao` (`/management`), which lands on "Briefing do dia" — no longer on the metrics (rnc-1 F7). The
 * team's landing is still its pipeline.
 */
function routerWith() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [...WorkspaceRoutes, ...BriefingModuleRoutes, ...MetricsModuleRoutes],
  });
}

describe('WorkspaceRoutes', () => {
  it('lands /gestao on the daily briefing', async () => {
    const router = routerWith();

    await router.push('/gestao');

    expect(router.currentRoute.value.path).toBe('/gestao/briefing');
    expect(router.currentRoute.value.name).toBe('briefing-pt-BR');
  });

  it('lands /management on the daily briefing', async () => {
    const router = routerWith();

    await router.push('/management');

    expect(router.currentRoute.value.path).toBe('/management/briefing');
    expect(router.currentRoute.value.name).toBe('briefing-en');
  });

  it("keeps the team's landing on its pipeline", () => {
    const redirects = Object.fromEntries(WorkspaceRoutes.map((route) => [route.path, route.redirect]));

    expect(redirects['/equipe']).toBe('/equipe/pipeline');
    expect(redirects['/team']).toBe('/team/pipeline');
  });

  // rnc-4 F2c1 (R14, A13): the old person-named paths are gone, with no redirect left behind.
  it.each(['/reinaldo', '/reinaldo/briefing', '/ceo', '/ceo/briefing'])('matches no route at %s', (path) => {
    expect(routerWith().resolve(path).matched).toEqual([]);
  });
});
