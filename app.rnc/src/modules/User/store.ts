import { defineStore } from 'pinia';
import type { User, UserFirm, UserPlatform } from '@UserModule';
import { ROLES } from '@AuthModule';
import { GetMeService } from './services';

interface UserState {
  firm: UserFirm | null;
  permissions: string[];
  platform: UserPlatform | null;
  user: User | null;
}

const SESSION_HINT_KEY = 'app.rnc:session-hint';

function persistSessionHint(user: User | null): void {
  if (user) {
    localStorage.setItem(SESSION_HINT_KEY, '1');
    return;
  }

  localStorage.removeItem(SESSION_HINT_KEY);
}

const useUserStore = defineStore('user', {
  state: (): UserState => ({
    firm: null,
    permissions: [],
    platform: null,
    user: null,
  }),
  getters: {
    isAuthenticated: (state): boolean => state.user !== null,
    hasEditAuth: (state): boolean => state.user?.role_slug === ROLES.SUPERADMIN,
  },
  actions: {
    /**
     * The session's user. `firm` and `platform` (rnc-4 R20) come only with `auth/me` and the sign-in
     * answers: a payload without the key (a profile save) keeps what the session already knew.
     */
    setUserAndPermissions(user: User | null, permissions: string[] = []): void {
      this.user = user;
      this.permissions = permissions ?? user?.permissions ?? [];

      if (user && 'firm' in user) this.firm = user.firm ?? null;

      if (user?.platform) this.platform = user.platform;

      persistSessionHint(user);
    },
    clearUser(): void {
      this.user = null;
      this.permissions = [];
      this.firm = null;
      this.platform = null;
      persistSessionHint(null);
    },
    /**
     * The API closed the operator's view-as (rnc-4 verify P2-S5): forget it at once, so the banner goes even when
     * the `auth/me` read that follows fails.
     */
    endViewAs(): void {
      if (!this.platform) return;

      this.platform = { ...this.platform, view_as: null };
    },
    hasSessionHint(): boolean {
      return localStorage.getItem(SESSION_HINT_KEY) === '1';
    },
    fetchMe(): Promise<void> {
      return GetMeService({ cacheBust: true }).then(({ data }) =>
        this.setUserAndPermissions(data, [...(data.permissions ?? [])])
      );
    },
    hasPermission(key: string): boolean {
      return this.permissions.includes(key);
    },
    /**
     * Whether the person's firm has an opt-in module on (rnc-4 R17, `FIRM_MODULE`). During a view-as
     * the viewed firm's modules answer (W93); otherwise a firm-less account — the platform operator,
     * whose requests run as the platform firm — has none.
     */
    hasModule(key: string): boolean {
      const modules = this.platform?.view_as?.modules ?? this.firm?.modules ?? [];

      return modules.includes(key);
    },
  },
});

export { useUserStore };
