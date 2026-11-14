import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * rnc-4 F1b — the firm comes from the host (R2, R3, R5, R6; A6 in the browser, A7).
 *
 * The suite's `baseURL` is `http://franco.localhost:<port>` (D31), so every call the SPA makes leaves
 * same-origin at `/api` naming `X-Firm: franco`. A 403 `firms.mismatch` — a session that belongs to
 * another firm — clears the session and lands on this firm's login screen. A subdomain the API does
 * not know shows "Firm not found" on any path.
 */
const TEAM_MEMBER = {
  ...mockUser,
  username: 'patricia',
  role: 'Equipe',
  role_slug: 'equipe',
  permissions: ['leads.read', 'leads.update', 'agenda.read'],
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

function hostUrl(baseURL: string | undefined, host: string, path: string) {
  const url = new URL(path, baseURL);

  url.hostname = host;

  return url.toString();
}

async function signInAs(page: Page, firms: (string | null)[], paths: string[]) {
  await seedAuthSession(page);

  // Every request the page sends, whichever mock answers it: what reached the network layer is what
  // the SPA asked for.
  page.on('request', (request) => {
    if (!['fetch', 'xhr'].includes(request.resourceType())) return;

    const url = new URL(request.url());

    firms.push(request.headers()['x-firm'] ?? null);
    // Origin and path: same-origin is half of the rule (D4), so a call to another host fails too.
    paths.push(`${url.origin}${url.pathname}`);
  });

  await page.route('**/api/auth/me**', (route) => route.fulfill(json({ data: TEAM_MEMBER })));
}

test.describe('Firm by host', () => {
  test('every call goes to /api naming the firm of the host (A6)', async ({ page, baseURL }) => {
    const firms: (string | null)[] = [];
    const paths: string[] = [];

    await signInAs(page, firms, paths);
    await page.route(/\/api\/leads(\?.*)?$/, (route) => route.fulfill(json({ data: { columns: [] } })));

    await page.goto('/equipe/pipeline');
    await expect.poll(() => paths.some((path) => path.includes('/api/leads'))).toBe(true);

    const API_BASE = `${new URL(baseURL ?? '').origin}/api/`;

    expect(paths.length).toBeGreaterThan(1);
    expect(paths.filter((path) => !path.startsWith(API_BASE))).toEqual([]);
    expect(new Set(firms)).toEqual(new Set(['franco']));
  });

  test('a 403 firms.mismatch clears the session and shows the login screen (A7)', async ({ page }) => {
    await signInAs(page, [], []);
    await page.route(/\/api\/leads(\?.*)?$/, (route) =>
      route.fulfill(json({ data: null, error: 'firms.mismatch', status_code: 403 }, 403))
    );

    await page.goto('/equipe/pipeline');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByTestId('login-username')).toBeVisible();
    // Signed out for real: the hint that made the guard ask for a session is gone.
    expect(await page.evaluate(() => localStorage.getItem('app.rnc:session-hint'))).toBeNull();
  });

  test('a subdomain the API does not know shows "Firm not found" on any path (R3)', async ({ page, baseURL }) => {
    await page.route('**/api/**', (route) => route.abort('connectionrefused'));
    await page.route('**/api/public/firm**', (route) =>
      route.fulfill(json({ data: null, error: 'firms.notFound', status_code: 404 }, 404))
    );

    await page.goto(hostUrl(baseURL, 'xyz.localhost', '/login'));

    await expect(page.getByTestId('firm-not-found')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Firm not found' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Go to the home page' })).toHaveAttribute(
      'href',
      hostUrl(baseURL, 'localhost', '/')
    );
  });
});
