import { expect, test } from '@playwright/test';

// VITE_LOGIN_FLOW=username → LoginUsernamePasswordFlowView → MLoginUsernamePassword
// data-testid attributes: login-username, login-submit (added to source).
// #loginPassword is the PrimeVue Password inputId — forwarded to the inner <input>.

const mockUser = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'administrator',
  status: 'active',
  permissions: [],
};

test.describe('Login page — renders', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    // Wait for the lazy-loaded LoginUsernamePasswordFlowView chunk to hydrate
    await page.waitForLoadState('networkidle');
  });

  test('loads the login form', async ({ page }) => {
    await expect(page.locator('[data-testid="login-username"]')).toBeVisible({ timeout: 10000 });
  });

  test('shows the username input', async ({ page }) => {
    await expect(page.locator('[data-testid="login-username"]')).toBeVisible({ timeout: 10000 });
  });

  test('shows the password input', async ({ page }) => {
    await expect(page.locator('#loginPassword')).toBeVisible({ timeout: 10000 });
  });

  test('shows the submit button', async ({ page }) => {
    await expect(page.locator('[data-testid="login-submit"]')).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Login page — username mode with mocked API', () => {
  test('logs in and redirects to home on valid credentials', async ({ page }) => {
    await page.route('**/auth/login**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockUser }),
      })
    );
    // auth/me must be UNAUTHENTICATED at page load, or the public-route guard sees an
    // existing session and redirects off /login before the form renders. Login then
    // authenticates via the mocked auth/login below.
    await page.route('**/auth/me**', (route) =>
      route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unauthenticated' }),
      })
    );

    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="login-username"]')).toBeVisible({ timeout: 10000 });

    await page.locator('[data-testid="login-username"]').fill('alice');
    await page.locator('#loginPassword').fill('password123');
    await page.locator('[data-testid="login-submit"]').click();

    await expect(page).not.toHaveURL(/login/, { timeout: 10000 });
  });
});

test.describe('Login page — validation', () => {
  test('shows validation error when submitting empty form', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="login-username"]')).toBeVisible({ timeout: 10000 });
    await page.locator('[data-testid="login-submit"]').click();
    const errorEl = page.locator('[class*="p-message-error"], [class*="error"], [severity="error"]').first();
    await expect(errorEl).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Login page — /entrar alias', () => {
  test('/entrar renders the same login form', async ({ page }) => {
    await page.goto('/entrar');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="login-username"]')).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Login page — email flow', () => {
  // Pre-warm the lazy-compiled LoginUsernameFlowView chunk so the actual test runs
  // against an already-compiled module. Without this, first-run Vite compilation can
  // exceed the test timeout.
  // `browser.newPage()` does not inherit the project's `baseURL`, so it is handed over: a fixed
  // `localhost:5173` warmed whatever else answered there (or nothing) whenever the suite ran on
  // another port, and the `.catch` hid it.
  test.beforeAll(async ({ browser }, testInfo) => {
    const warmupPage = await browser.newPage({ baseURL: testInfo.project.use.baseURL });
    await warmupPage.goto('/login-email').catch(() => {});
    await warmupPage.waitForLoadState('networkidle').catch(() => {});
    await warmupPage.close();
  });

  test('/login-email renders a form', async ({ page }) => {
    await page.goto('/login-email');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="login-email"]')).toBeVisible({ timeout: 15000 });
  });
});
