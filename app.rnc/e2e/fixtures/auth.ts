import { test as base, expect } from '@playwright/test';
import type { Page } from '@playwright/test';

// The app sets this localStorage hint on login; the route guard skips the /auth/me probe when it
// is absent. Mocked-session tests must seed it so they simulate a properly logged-in user — see
// SESSION_HINT_KEY in src/modules/User/store.ts. Call BEFORE page.goto so it lands before app boot.
export async function seedAuthSession(page: Page): Promise<void> {
  await page.addInitScript(() => localStorage.setItem('app.rnc:session-hint', '1'));

  /*
   * Registered first, so every mock a spec adds afterwards takes precedence: an API call nobody
   * mocked is refused. Without it a call went to a running API — the test server proxies `/api` to
   * http://localhost:8000 (rnc-4 D4) — and a 401 from there reads as "signed out", so the spec
   * failed somewhere unrelated (lifeshifter, T0 sync).
   */
  await page.route('**/api/**', (route) => route.abort('connectionrefused'));

  /*
   * The firm of the host (rnc-4 F1b). On `franco.localhost` the route guard asks `GET public/firm`
   * at every hard navigation, to show "Firm not found" on a subdomain the API does not know; refused,
   * that read was one console error on every back-office screen whose spec counts them. A spec about
   * the public firm registers its own answer afterwards, which takes precedence.
   */
  await page.route('**/api/public/firm**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: FRANCO_FIRM }) })
  );
}

/** The minimal public firm the route guard needs to know the host's firm exists. */
const FRANCO_FIRM = {
  brand_accent: '#14c3ff',
  brand_primary: '#00375a',
  logo_text: 'Franco Contabilidade',
  name: 'Franco Contabilidade',
  slug: 'franco',
};

/**
 * Run this spec in the language people use. The suite runs in English (`.env.test`), and a
 * Portuguese label is often much longer — "Planejamento" needs 124px where "Planning" needs 90 —
 * so a spec that measures a narrow screen in English passes on a layout that clips on a real
 * phone. Seeds the same key the language switch writes (`LOCALE_STORAGE_KEY`). Call BEFORE
 * page.goto.
 */
export async function seedLanguage(page: Page, locale: 'en' | 'pt-BR'): Promise<void> {
  await page.addInitScript((value) => localStorage.setItem('app.rnc:locale', value), locale);
}

/**
 * A Laravel `LengthAwarePaginator` payload around `rows`. `GET /auth/users` is paginated on the
 * server, so a mock that returns a bare array renders an empty list with no error anywhere.
 */
export function paginated<T>(rows: T[]) {
  return { current_page: 1, data: rows, last_page: 1, per_page: 15, total: rows.length };
}

// Mock API user returned by GET /auth/me
export const mockUser = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'operador',
  status: 'active',
  /*
   * The session's firm (rnc-4 R20) with EVERY opt-in module on, as Franco is seeded: the rail and
   * the router hide a module's screens while the firm has it off (F3c), so a spec about a screen
   * needs its module. A spec about a module being off overrides `firm` (`tenancy/modules.spec.ts`).
   */
  firm: {
    modules: ['portfolio', 'fiscal', 'radar', 'partners', 'contracts', 'chat', 'assistant', 'briefing', 'brands', 'channel'],
    name: 'Franco Contabilidade',
    slug: 'franco',
  },
  // `permissions.*` because the roles screen closes its route on `permissions.read` (e2e/RULES.md).
  permissions: [
    'users.read',
    'users.create',
    'users.update',
    'users.archive',
    'users.delete',
    'permissions.read',
    'permissions.update',
  ],
};

export const mockRole = {
  id: 1,
  name: 'Admin',
  slug: 'admin',
  description: '',
  users: [],
  permissionGroups: [
    {
      id: 'group-users',
      nameKey: 'permissions.users',
      icon: 'pi pi-user',
      permissions: [
        { key: 'users.read', labelKey: 'permissions.users.read', value: true },
        { key: 'users.create', labelKey: 'permissions.users.create', value: false },
      ],
    },
  ],
};

// Fixture that intercepts GET /auth/me so protected routes render as authenticated.
export const test = base.extend<{ authenticated: void }>({
  authenticated: [
    async ({ page }, use) => {
      await seedAuthSession(page);

      // Intercept all API calls for the test session
      await page.route('**/auth/me**', (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
      );
      await page.route('**/auth/users**', (route) =>
        route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: paginated([mockUser]) }),
        })
      );
      await page.route('**/auth/roles**', (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [mockRole] }) })
      );
      await use();
    },
    { auto: false },
  ],
});

export { expect };
