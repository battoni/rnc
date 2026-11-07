import { test, expect, mockUser, mockRole, seedAuthSession } from '../fixtures/auth';

test.describe('Roles & Permissions page', () => {
  test.beforeEach(async ({ page }) => {
    await seedAuthSession(page);

    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.route('**/auth/roles**', (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: [mockRole] }),
        });
      }
      return route.continue();
    });
    await page.goto('/roles-and-permissions');
  });

  test('renders the roles page', async ({ page }) => {
    await expect(page).toHaveURL(/roles-and-permissions/, { timeout: 5000 });
  });

  test('shows role tab after data loads', async ({ page }) => {
    await expect(page.getByText('Admin')).toBeVisible({ timeout: 5000 });
  });

  test('shows permission group heading', async ({ page }) => {
    await expect(page.getByText('Admin')).toBeVisible({ timeout: 5000 });
    // Permission panel should be visible
    const body = page.locator('body');
    await expect(body).toContainText('Admin', { timeout: 5000 });
  });

  test('save permissions calls PATCH and view remains intact', async ({ page }) => {
    let patchCalled = false;
    await page.route('**/auth/roles/*/permissions', (route) => {
      patchCalled = true;
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockRole }),
      });
    });

    await expect(page.getByText('Admin')).toBeVisible({ timeout: 5000 });

    const saveButton = page.locator('[data-testid="save-permissions"]').first();

    // Asserted, never guarded: wrapped in `if (hasSaveButton)` this passed whenever the button was
    // absent — exactly the regression a rename causes.
    await expect(saveButton).toBeVisible({ timeout: 5000 });

    const responsePromise = page.waitForResponse('**/auth/roles/*/permissions');

    await saveButton.click();
    await responsePromise;

    expect(patchCalled).toBe(true);

    // Page still shows Admin role (use first() — 'Admin' appears in both the tab and the panel)
    await expect(page.getByText('Admin').first()).toBeVisible({ timeout: 3000 });
  });

  test('shows new role dialog when add button is clicked', async ({ page }) => {
    await expect(page.getByText('Admin')).toBeVisible({ timeout: 5000 });

    // A test id, not a text pattern: the filter matched any button whose label happened to contain
    // "new" or "perfil", drifted with the copy, and then hid behind `if (hasAddButton)`.
    const addButton = page.locator('[data-testid="add-profile"]');

    await expect(addButton).toBeVisible({ timeout: 5000 });
    await addButton.click();

    await expect(page.locator('[class*="p-dialog"], [role="dialog"]').first()).toBeVisible({ timeout: 3000 });
  });
});

/*
 * The nav hid the link from an account without `permissions.read`, and that was all: typing the
 * address opened the screen. The route declares the key now, and the guard sends the account home.
 */
test.describe('Roles & Permissions — an account without permissions.read', () => {
  test('typing the address lands on Home, not on the screen', async ({ page }) => {
    await seedAuthSession(page);

    await page.route('**/auth/me**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...mockUser, permissions: ['users.read'] } }),
      })
    );

    await page.goto('/roles-and-permissions');

    await expect(page).not.toHaveURL(/roles-and-permissions/, { timeout: 5000 });
    await expect(page.locator('[data-testid="add-profile"]')).toHaveCount(0);
  });
});
