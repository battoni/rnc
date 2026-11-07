import { defineStore } from 'pinia';
import type { User } from '@UserModule';
import { ROLES } from '@AuthModule';
import { GetMeService } from './services';

interface UserState {
  permissions: string[];
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
    permissions: [],
    user: null,
  }),
  getters: {
    isAuthenticated: (state): boolean => state.user !== null,
    hasEditAuth: (state): boolean => state.user?.role_slug === ROLES.SUPERADMIN,
  },
  actions: {
    setUserAndPermissions(user: User | null, permissions: string[] = []): void {
      this.user = user;
      this.permissions = permissions ?? user?.permissions ?? [];
      persistSessionHint(user);
    },
    clearUser(): void {
      this.user = null;
      this.permissions = [];
      persistSessionHint(null);
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
  },
});

export { useUserStore };
