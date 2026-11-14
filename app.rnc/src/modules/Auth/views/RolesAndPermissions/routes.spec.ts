import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { RouteLocationNormalized } from 'vue-router';

const userStoreState: { permissions: string[]; roleSlug: string } = { permissions: [], roleSlug: 'operador' };

vi.mock('@UserModule', () => ({
  useUserStore: () => ({
    user: { id: 1, role_slug: userStoreState.roleSlug },
    fetchMe: vi.fn(),
    hasSessionHint: () => true,
    hasPermission: (key: string) => userStoreState.permissions.includes(key),
  }),
}));

vi.mock('@Composables', () => ({
  useGlobalAbortController: () => ({ cancelAllRequests: vi.fn(), createNewController: vi.fn() }),
}));

const { default: attachAuthGuard } = await import('@Helpers/attachAuthGuard.helper');
const { default: RolesAndPermissionsRoutes } = await import('./routes');

type Guard = (to: RouteLocationNormalized, from: { name?: string }, next: ReturnType<typeof vi.fn>) => unknown;

const NAMED_ROUTES = RolesAndPermissionsRoutes.filter((route) => route.name);

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
 * The navigation hid the link from anyone without `permissions.read`, and that was all: typing the
 * address opened the permissions screen for any signed-in account.
 */
describe('RolesAndPermissions routes', () => {
  beforeEach(() => {
    userStoreState.permissions = [];
    userStoreState.roleSlug = 'operador';
  });

  it('declares both named routes', () => {
    expect(NAMED_ROUTES.map((route) => route.name)).toEqual([
      'roles-and-permissions-en',
      'roles-and-permissions-pt-BR',
    ]);
  });

  it.each(NAMED_ROUTES.map((route) => [route.name, route]))(
    '%s turns away a signed-in account without permissions.read',
    (_name, route) => {
      userStoreState.permissions = ['users.read'];

      expect(visit(route)).toHaveBeenCalledWith({ name: 'home' });
    }
  );

  it.each(NAMED_ROUTES.map((route) => [route.name, route]))(
    '%s opens for the operator holding permissions.read',
    (_name, route) => {
      userStoreState.permissions = ['permissions.read'];

      expect(visit(route)).toHaveBeenCalledWith();
    }
  );

  // rnc-4 verify: role writes are the operator's alone on the API (403 `platform.operatorOnly`) — a firm manager
  // granted the keys is still turned away (`meta.role`).
  it.each(NAMED_ROUTES.map((route) => [route.name, route]))(
    '%s turns away a firm manager even holding permissions.read and permissions.update',
    (_name, route) => {
      userStoreState.permissions = ['permissions.read', 'permissions.update'];
      userStoreState.roleSlug = 'gestao';

      expect(visit(route)).toHaveBeenCalledWith({ name: 'home' });
    }
  );
});
