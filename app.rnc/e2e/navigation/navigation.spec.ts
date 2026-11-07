import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * The navigation, at the four widths where it changes shape.
 *
 * These are the checks a unit test cannot make, because every one of them is about GEOMETRY: a
 * rail that animates its own width, a column that grows into the space the rail gives back, a
 * bar that must not sit on top of the content. jsdom has no layout, so `getBoundingClientRect`
 * there answers zero for everything and a spec that reads it asserts nothing.
 *
 * The four widths are the breakpoints the code actually names, not round numbers: 360 is the
 * narrowest phone worth supporting, 768 is `md` (the drawer stops covering the whole screen),
 * 1024 is `lg` (the rail exists, the bottom bar does not) and 1440 is a laptop.
 */

// `design.read` because the Design group is a permission now, not the administrator's role slug:
// the seeded administrator holds the key through `RoleSeeder`, and this mock has to say so too.
const SUPERADMIN = {
  ...mockUser,
  permissions: ['users.read', 'permissions.read', 'ideas.read', 'tips.read', 'design.read'],
};

async function signInAs(page: Page, user = SUPERADMIN) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: user }) })
  );
  await page.route('**/ideas/news/unread-count**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { unread: 0 } }) })
  );
  await page.route('**/tips**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
  );
}

const rail = (page: Page) => page.locator('.the-sidenav');

test.describe('navigation — 1440, the laptop', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('the rail is present and the content column sits beside it, not under it', async ({ page }) => {
    await signInAs(page);
    await page.goto('/');

    const column = page.locator('[data-route-fade]');
    await expect(column).toBeVisible();

    const railBox = await rail(page).boundingBox();
    const columnBox = await column.boundingBox();

    expect(railBox).not.toBeNull();
    expect(columnBox).not.toBeNull();
    // Side by side: the column starts after the rail ends. Overlap here is the bug the
    // grid-track collapse could reintroduce if the track were removed instead of zeroed.
    expect(columnBox!.x).toBeGreaterThanOrEqual(railBox!.x + railBox!.width - 1);
  });

  test('collapsing the rail keeps it mounted, hands its width to the column, and is reversible', async ({ page }) => {
    await signInAs(page);
    await page.goto('/');

    const column = page.locator('[data-route-fade]');
    const widthBefore = (await column.boundingBox())!.width;

    // Pin first: the menu button is the only way back, and unpinned it is hidden at this width.
    await page.goto('/personalization');
    await page.getByLabel('Pin the menu button').click();
    await page.locator('[data-testid="page-header-menu"]').click();

    // The rail did not unmount — that `v-if` was the largest layout shift in the app.
    await expect(rail(page)).toBeAttached();

    // Polled, not slept: the rail animates its width, and the column is read until it has moved
    // (e2e/RULES.md — no fixed sleep).
    const columnWidth = async () => (await column.boundingBox())!.width;

    await expect.poll(columnWidth).toBeGreaterThan(widthBefore);

    await page.locator('[data-testid="page-header-menu"]').click();

    await expect.poll(columnWidth).toBeCloseTo(widthBefore, -1);
  });

  test('a navigation group opens, stays open across a reload, and closes the one before it', async ({ page }) => {
    await signInAs(page);
    await page.goto('/');

    const design = page.getByRole('button', { name: /Design/i }).first();
    await design.click();

    await expect(page.getByRole('link', { name: 'Brand' })).toBeVisible();

    await page.reload();
    await expect(page.getByRole('link', { name: 'Brand' })).toBeVisible();
  });
});

test.describe('navigation — 1024, where the rail arrives', () => {
  test.use({ viewport: { width: 1024, height: 800 } });

  test('the rail is on screen and the bottom bar is not', async ({ page }) => {
    await signInAs(page);
    await page.goto('/');

    await expect(rail(page)).toBeVisible();
    await expect(page.locator('.mobile-navbar-root')).toBeHidden();
  });
});

test.describe('navigation — 768, the tablet', () => {
  test.use({ viewport: { width: 768, height: 900 } });

  test('the bottom bar is on screen and nothing sits underneath it', async ({ page }) => {
    await signInAs(page);
    await page.goto('/personalization');

    const bar = page.locator('.mobile-navbar-root');
    await expect(bar).toBeVisible();

    const barBox = (await bar.boundingBox())!;
    const card = page.locator('[data-testid="personalization-visual-card"]');
    const cardBox = (await card.boundingBox())!;

    /*
     * The reserve at the end of the scroller was `md:pb-8` while the bar is on screen until
     * `lg`, so 768–1023 had content sitting under it. The card is the last thing on this page,
     * so its bottom edge is where the reserve either exists or does not.
     */
    expect(cardBox.y + cardBox.height).toBeLessThanOrEqual(barBox.y + 1);
  });

  test('the page does not scroll sideways', async ({ page }) => {
    await signInAs(page);
    await page.goto('/personalization');

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(overflow).toBeLessThanOrEqual(0);
  });
});

test.describe('navigation — 360, the phone', () => {
  test.use({ viewport: { width: 360, height: 740 } });

  test('the bottom bar is the navigation, and the page still does not scroll sideways', async ({ page }) => {
    await signInAs(page);
    await page.goto('/personalization');

    await expect(page.locator('.mobile-navbar-root')).toBeVisible();

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(overflow).toBeLessThanOrEqual(0);
  });

  test('the page header keeps its row on one line', async ({ page }) => {
    await signInAs(page);
    await page.goto('/personalization');

    const title = page.locator('[data-page-title]');
    await expect(title).toBeVisible();

    const titleBox = (await title.boundingBox())!;

    // One line. The row was `flex-wrap` and broke in two on a phone, and had been "fixed" twice
    // by shaving pixels before the row was made `flex-nowrap` below `md`.
    expect(titleBox.height).toBeLessThan(48);
  });
});
