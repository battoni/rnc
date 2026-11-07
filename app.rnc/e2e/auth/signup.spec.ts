import { test, expect, mockUser } from '../fixtures/auth';

// Sign-up (/onboarding) has meta.public (not isPublic), so TheRouteGuard
// treats it as protected — needs GET /auth/me mocked.

test.describe('Sign-up page', () => {
  test.use({ authenticated: undefined });

  test.beforeEach(async ({ page }) => {
    // Mock auth/me for the route guard
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/onboarding');
  });

  test('renders the sign-up page', async ({ page }) => {
    // The page should render (not redirect) since user is authenticated
    await expect(page).toHaveURL(/onboarding/, { timeout: 5000 });
  });

  test('shows a form or content on the sign-up page', async ({ page }) => {
    // Wait for any content to appear
    const body = page.locator('body');
    await expect(body).not.toBeEmpty({ timeout: 5000 });
    // There should be some form element
    const hasContent = await page
      .locator('form, input, button')
      .first()
      .isVisible()
      .catch(() => false);
    expect(hasContent).toBe(true);
  });
});
