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
  /*
   * rnc-4 R20, A24: `auth/me` (and the sign-in answers) carry the person's firm and the platform.
   * The store keeps them beside the user, and `hasModule` answers for the firm's opt-in modules —
   * what the rail and the router hide and close. A payload without the keys (a profile save) keeps
   * what the session already knew; a firm-less account (the platform operator) has no opt-in module.
   */
  it('keeps the firm and the platform from the session, and answers hasModule from the firm (rnc-4 R20)', () => {
    const store = useUserStore();
    store.setUserAndPermissions({
      ...mockUser,
      firm: { modules: ['briefing', 'portfolio'], name: 'Alvorada Contabilidade', slug: 'alvorada' },
      platform: { is_operator: false, name: 'Hub da Reforma', view_as: null },
    } as never);

    expect(store.firm?.slug).toBe('alvorada');
    expect(store.platform?.name).toBe('Hub da Reforma');
    expect(store.hasModule('briefing')).toBe(true);
    expect(store.hasModule('partners')).toBe(false);
  });

  it('keeps the firm it knew when a later payload carries none, and drops it on clearUser', () => {
    const store = useUserStore();
    store.setUserAndPermissions({ ...mockUser, firm: { modules: ['partners'], name: 'F', slug: 'f' } } as never);
    store.setUserAndPermissions(mockUser as never);

    expect(store.hasModule('partners')).toBe(true);

    store.clearUser();

    expect(store.firm).toBeNull();
    expect(store.hasModule('partners')).toBe(false);
  });

  it('has no opt-in module for a firm-less account (the platform operator, rnc-4 A26)', () => {
    const store = useUserStore();
    store.setUserAndPermissions({
      ...mockUser,
      firm: null,
      platform: { is_operator: true, name: 'Hub', view_as: null },
    } as never);

    expect(store.firm).toBeNull();
    expect(store.hasModule('briefing')).toBe(false);
  });

  it("answers hasModule from the viewed firm's modules while the operator views as it (rnc-4 W93)", () => {
    const store = useUserStore();
    store.setUserAndPermissions({
      ...mockUser,
      firm: null,
      platform: {
        is_operator: true,
        name: 'Hub',
        view_as: { modules: ['partners', 'briefing'], name: 'Alvorada Contabilidade', slug: 'alvorada' },
      },
    } as never);

    expect(store.hasModule('partners')).toBe(true);
    expect(store.hasModule('briefing')).toBe(true);
    expect(store.hasModule('portfolio')).toBe(false);
  });
});
