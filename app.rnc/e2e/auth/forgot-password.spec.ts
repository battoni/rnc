import { test, expect, mockUser } from '../fixtures/auth';

// /forgot-password has meta.public (not isPublic), so TheRouteGuard requires
// auth/me to succeed. Mock it so the page renders.

test.describe('Forgot password page', () => {
  test.use({ authenticated: undefined });

  test.beforeEach(async ({ page }) => {
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/forgot-password');
  });

  test('renders the forgot-password page', async ({ page }) => {
    await expect(page).toHaveURL(/forgot-password/, { timeout: 5000 });
  });

  test('shows form content on the forgot-password page', async ({ page }) => {
    const body = page.locator('body');
    await expect(body).not.toBeEmpty({ timeout: 5000 });
    const hasContent = await page
      .locator('form, input, button, h1, h2')
      .first()
      .isVisible()
      .catch(() => false);
    expect(hasContent).toBe(true);
  });

  test('/support alias renders the support page', async ({ page }) => {
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/support');
    await expect(page).toHaveURL(/support/, { timeout: 5000 });
  });
});
