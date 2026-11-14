import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * rnc-4 A24 (R17, R20), through a real router and a real rail: a firm with `partners` off shows no
 * partner entry — registry, trail, ledger — and its address is "not found", at the same URL, typed
 * or reached by a link. `/management` lands on the metrics while `briefing` is off. The unit specs
 * cover the guard's decision and the rail's list; this covers `TheRouteGuard`, the redirect and the
 * rail drawn on screen.
 */
const HELENA = {
  ...mockUser,
  id: 41,
  last_name: 'Alvorada',
  name: 'Helena',
  role: 'Gestão',
  role_slug: 'gestao',
  username: 'helena.alvorada',
  permissions: ['leads.read', 'metrics.read', 'briefing.read', 'partners.manage', 'referrals.read', 'ledger.read'],
};

const PARTNER_LINKS = ['/management/partners', '/management/trail', '/management/ledger'];

async function signInWith(page: Page, modules: string[]) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { ...HELENA, firm: { modules, name: 'Alvorada Contabilidade', slug: 'alvorada' } } }),
    })
  );
}

test.describe('modules per firm (rnc-4 A24)', () => {
  test('a firm without `partners` has no partner entry in the rail', async ({ page }) => {
    await signInWith(page, ['briefing']);

    await page.goto('/management/metrics');

    await expect(page.getByTestId('not-found')).toHaveCount(0);
    await expect(page.locator('a[href="/management/metrics"]').first()).toBeVisible();

    for (const href of PARTNER_LINKS) await expect(page.locator(`a[href="${href}"]`), href).toHaveCount(0);
  });

  test('the rail offers them while `partners` is on', async ({ page }) => {
    await signInWith(page, ['briefing', 'partners']);

    await page.goto('/management/metrics');

    await expect(page.locator('a[href="/management/partners"]').first()).toBeVisible();
  });

  test('`/gestao/parceiros` is not found at the same address while `partners` is off', async ({ page }) => {
    // In Portuguese, or the locale guard rewrites the address to its English twin first.
    await seedLanguage(page, 'pt-BR');
    await signInWith(page, ['briefing']);

    await page.goto('/gestao/parceiros');

    await expect(page.getByTestId('not-found')).toBeVisible();
    await expect(page).toHaveURL('/gestao/parceiros');
  });

  test('`/management` lands on the metrics while `briefing` is off', async ({ page }) => {
    await signInWith(page, []);

    await page.goto('/management');

    await expect(page).toHaveURL('/management/metrics');
    await expect(page.getByTestId('not-found')).toHaveCount(0);
  });
});
