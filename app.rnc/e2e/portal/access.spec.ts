import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * The partner's way in (rnc-2 F1, R3, A4), with the API mocked: a partner signs in on the same
 * screen and lands on `/parceiro`; its rail holds the portal and none of the back office's groups;
 * `/painel` and every back-office route bounce it back to the portal. Staff — the operator
 * included, who holds the portal key — never see a portal link and are sent away from it.
 */
const PARTNER_LOGIN = {
  ...mockUser,
  id: 21,
  name: 'NexoERP (exemplo)',
  last_name: '',
  username: 'parceiro.3',
  role: 'Parceiro',
  role_slug: 'parceiro',
  partner_id: 3,
  permissions: ['portal.access'],
};

const REINALDO = {
  ...mockUser,
  id: 2,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'ceo',
  partner_id: null,
  permissions: ['home.read', 'leads.read', 'metrics.read', 'partners.manage', 'referrals.read'],
};

const OPERATOR = {
  ...mockUser,
  partner_id: null,
  permissions: [...mockUser.permissions, 'home.read', 'leads.read', 'metrics.read', 'portal.access'],
};

const PORTAL_ME = {
  firm: { name: 'Franco Contabilidade', slug: 'franco' },
  partner: { id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signedInAs(page: Page, user: Record<string, unknown>) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: user })));
  await page.route('**/portal/me**', (route) => route.fulfill(json({ data: PORTAL_ME })));
  await page.route('**/portal/referrals**', (route) => route.fulfill(json({ data: [] })));
}

/** A rail entry, by its visible name (the rail renders links, the page header a heading). */
const railLink = (page: Page, name: string) => page.getByRole('link', { name, exact: false });

test.describe('Partner portal — the partner', () => {
  test('signs in on the same screen and lands on its portal', async ({ page }) => {
    await seedLanguage(page, 'pt-BR');
    await page.route('**/api/**', (route) => route.abort('connectionrefused'));
    await page.route('**/auth/me**', (route) => route.fulfill(json({ message: 'Unauthenticated' }, 401)));
    await page.route('**/auth/login**', (route) => route.fulfill(json({ data: PARTNER_LOGIN })));
    await page.route('**/portal/me**', (route) => route.fulfill(json({ data: PORTAL_ME })));
    await page.route('**/portal/referrals**', (route) => route.fulfill(json({ data: [] })));

    await page.goto('/entrar');
    await page.locator('[data-testid="login-username"]').fill('parceiro.3');
    await page.locator('#loginPassword').fill('franco-local');
    await page.locator('[data-testid="login-submit"]').click();

    await expect(page).toHaveURL(/\/parceiro$/, { timeout: 10000 });
    await expect(page.getByTestId('portal-partner')).toContainText('NexoERP (exemplo)');
  });

  test('sees only the portal in its rail', async ({ page }) => {
    await signedInAs(page, PARTNER_LOGIN);
    await page.setViewportSize({ width: 1440, height: 900 });

    await page.goto('/parceiro');
    await expect(page.getByTestId('portal-partner')).toBeVisible();

    await expect(railLink(page, 'Portal do parceiro')).toBeVisible();
    for (const group of ['Equipe', 'Reinaldo', 'Operador', 'Parceiros', 'Usuários']) {
      await expect(railLink(page, group)).toHaveCount(0);
    }
  });

  for (const path of ['/painel', '/reinaldo/parceiros', '/equipe/pipeline', '/operador']) {
    test(`is bounced from ${path} to its portal`, async ({ page }) => {
      await signedInAs(page, PARTNER_LOGIN);

      await page.goto(path);

      await expect(page).toHaveURL(/\/parceiro$/, { timeout: 10000 });
      await expect(page.getByTestId('portal-partner')).toBeVisible();
    });
  }
});

test.describe('Partner portal — staff', () => {
  for (const [who, user] of [
    ['Reinaldo', REINALDO],
    ['the operator', OPERATOR],
  ] as const) {
    test(`${who} never sees a portal link`, async ({ page }) => {
      await signedInAs(page, user);
      await page.setViewportSize({ width: 1440, height: 900 });

      await page.goto('/painel');
      await expect(page.getByTestId('workspace-card').first()).toBeVisible();

      await expect(railLink(page, 'Reinaldo')).toBeVisible();
      await expect(railLink(page, 'Portal do parceiro')).toHaveCount(0);
    });
  }

  test('Reinaldo, without the portal key, is sent from /parceiro to Home', async ({ page }) => {
    await signedInAs(page, REINALDO);

    await page.goto('/parceiro');

    await expect(page).toHaveURL(/\/painel$/, { timeout: 10000 });
  });
});
