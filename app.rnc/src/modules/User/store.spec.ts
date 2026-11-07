import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const getMeService = vi.fn();

vi.mock('./services', () => ({
  GetMeService: getMeService,
}));

vi.mock('@AuthModule', () => ({
  ROLES: { SUPERADMIN: 'administrator', ADMIN: 'admin', SUPPORT: 'support', USER: 'user' },
}));

// Import after mocks are registered
const { useUserStore } = await import('./store');

const mockUser = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'admin',
  permissions: ['users.read', 'users.create'],
};

describe('useUserStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    getMeService.mockReset();
    localStorage.clear();
  });

  it('initialises with null user and empty permissions', () => {
    const store = useUserStore();
    expect(store.user).toBeNull();
    expect(store.permissions).toEqual([]);
  });

  it('isAuthenticated is false when user is null', () => {
    expect(useUserStore().isAuthenticated).toBe(false);
  });

  it('isAuthenticated is true after setUserAndPermissions', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never);
    expect(store.isAuthenticated).toBe(true);
  });

  it('setUserAndPermissions stores the user and permissions', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never, ['users.read']);
    expect(store.user).toEqual(mockUser);
    expect(store.permissions).toEqual(['users.read']);
  });

  it('uses the empty-array default when no permissions argument is passed', () => {
    // The default parameter `permissions = []` is never null/undefined, so
    // `[] ?? user?.permissions` short-circuits and `user.permissions` is ignored.
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never);
    expect(store.permissions).toEqual([]);
  });

  it('clearUser resets user and permissions to initial state', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never, ['users.read']);
    store.clearUser();
    expect(store.user).toBeNull();
    expect(store.permissions).toEqual([]);
  });

  it('hasPermission returns true for a held permission', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never, ['users.read', 'users.create']);
    expect(store.hasPermission('users.read')).toBe(true);
  });

  it('hasPermission returns false for a missing permission', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never, ['users.read']);
    expect(store.hasPermission('users.delete')).toBe(false);
  });

  it('hasEditAuth is true for superadmin role slug', () => {
    const store = useUserStore();
    store.setUserAndPermissions({ ...mockUser, role_slug: 'administrator' } as never);
    expect(store.hasEditAuth).toBe(true);
  });

  it('hasEditAuth is false for non-superadmin role slug', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never);
    expect(store.hasEditAuth).toBe(false);
  });

  it('hasSessionHint is false before any session is established', () => {
    expect(useUserStore().hasSessionHint()).toBe(false);
  });

  it('setUserAndPermissions persists the session hint', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never);
    expect(store.hasSessionHint()).toBe(true);
  });

  it('clearUser removes the session hint', () => {
    const store = useUserStore();
    store.setUserAndPermissions(mockUser as never);
    store.clearUser();
    expect(store.hasSessionHint()).toBe(false);
  });

  it('fetchMe calls GetMeService and hydrates the store', async () => {
    getMeService.mockResolvedValue({ data: mockUser });
    const store = useUserStore();
    await store.fetchMe();
    expect(getMeService).toHaveBeenCalledOnce();
    expect(store.user).toEqual(mockUser);
  });
});
