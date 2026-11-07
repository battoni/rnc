import { test, expect, mockUser, paginated, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * A narrow list folds its row actions into ⋮, and an action in that menu opens a confirmation
 * anchored to the button that was clicked — which sits inside a popover. Unit specs stub both
 * PrimeVue overlays, so only a browser proves the anchor survives and the popup actually opens.
 */

const OTHER_USER = { ...mockUser, id: 2, name: 'Bob', last_name: 'Jones', username: 'bob', status: 'active' };

test.use({ viewport: { width: 390, height: 844 } });

test.beforeEach(async ({ page }) => {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
  await page.route('**/auth/users**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: paginated([mockUser, OTHER_USER]) }),
    })
  );
  await page.goto('/users');
  await expect(page.getByText('Bob Jones')).toBeVisible({ timeout: 5000 });
});

test('a row folds its actions into ⋮ and the menu confirms before archiving', async ({ page }) => {
  const menuTriggers = page.getByRole('button', { name: /mais ações/i });

  await expect(menuTriggers).toHaveCount(2);

  await menuTriggers.nth(1).click();

  const archive = page.locator('.p-popover').getByRole('button', { name: /arquivar/i });

  await expect(archive).toBeVisible();

  await archive.click();

  await expect(page.locator('.p-confirmpopup')).toBeVisible();
});

test('the signed-in administrator is never offered their own deactivation', async ({ page }) => {
  await page
    .getByRole('button', { name: /mais ações/i })
    .first()
    .click();

  const popover = page.locator('.p-popover');

  await expect(popover.getByRole('button', { name: /editar/i })).toBeVisible();
  await expect(popover.getByRole('button', { name: /arquivar/i })).toHaveCount(0);
});
