import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { RouteLocationNormalized } from 'vue-router';

const userStoreState: { permissions: string[] } = { permissions: [] };

vi.mock('@UserModule', () => ({
  useUserStore: () => ({
    user: { id: 1 },
    fetchMe: vi.fn(),
    hasSessionHint: () => true,
    hasPermission: (key: string) => userStoreState.permissions.includes(key),
  }),
}));

vi.mock('@Composables', () => ({
  useGlobalAbortController: () => ({ cancelAllRequests: vi.fn(), createNewController: vi.fn() }),
}));

const { default: attachAuthGuard } = await import('@Helpers/attachAuthGuard.helper');
const { default: TipRoutes } = await import('./routes');

type Guard = (to: RouteLocationNormalized, from: { name?: string }, next: ReturnType<typeof vi.fn>) => unknown;

const NAMED_ROUTES = TipRoutes.filter((route) => route.name);

function captureGuard(): Guard {
  let guard: Guard | undefined;

  attachAuthGuard({ beforeEach: (registered: Guard) => (guard = registered) } as never);

  if (!guard) throw new Error('guard was not registered');

  return guard;
}

function visit(route: (typeof NAMED_ROUTES)[number]) {
  const next = vi.fn();

  captureGuard()(
    { fullPath: route.path, meta: route.meta ?? {}, name: route.name, query: {} } as RouteLocationNormalized,
    { name: 'home' },
    next
  );

  return next;
}

/*
 * The navigation hid the link from anyone without `tips.read`, and that was all: typing `/tips`
 * opened a screen whose every request the API refuses without the same key.
 */
describe('Tip routes', () => {
  beforeEach(() => (userStoreState.permissions = []));

  it('declares both named routes', () => {
    expect(NAMED_ROUTES.map((route) => route.name)).toEqual(['tips-en', 'tips-pt-BR']);
  });

  it.each(NAMED_ROUTES.map((route) => [route.name, route]))(
    '%s turns away a signed-in account without tips.read',
    (_name, route) => {
      userStoreState.permissions = ['users.read'];

      expect(visit(route)).toHaveBeenCalledWith({ name: 'home' });
    }
  );

  it.each(NAMED_ROUTES.map((route) => [route.name, route]))(
    '%s opens for an account holding tips.read',
    (_name, route) => {
      userStoreState.permissions = ['tips.read'];

      expect(visit(route)).toHaveBeenCalledWith();
    }
  );
});
