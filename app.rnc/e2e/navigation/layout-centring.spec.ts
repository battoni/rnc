import type { Page } from '@playwright/test';
import { expect, mockUser, paginated, seedAuthSession, test } from '../fixtures/auth';

/*
 * The narrow layout is CENTRED, with the rail present and with it collapsed.
 *
 * In lifeshifter it was not: the outer wrapper was capped by a breakpoint ladder and the rail +
 * content ensemble by the reading column plus the rail's footprint — two numbers from two places,
 * which agreed at 1440 and did not at 1920, where the page sat 192px from the left edge against
 * 280 from the right. This asserts the outcome rather than either number, so it holds whichever
 * number moves next.
 */

async function openWith(page: Page, isNavCollapsed: '0' | '1', width: number) {
  await page.setViewportSize({ width, height: 1000 });
  await seedAuthSession(page);
  await page.addInitScript((collapsed) => {
    localStorage.setItem('app.rnc:fullscreen', '0');
    localStorage.setItem('app.rnc:nav-collapsed', collapsed);
    // A collapsed rail is refused unless the menu button is pinned — otherwise it is a trap.
    localStorage.setItem('app.rnc:menu-button-pinned', '1');
  }, isNavCollapsed);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
  await page.route('**/auth/users**', (route) =>
    route.request().resourceType() === 'document'
      ? route.fallback()
      : route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(paginated([])) })
  );
  await page.goto('/users');
  await expect(page.locator('main')).toBeVisible();
}

/**
 * Left margin minus right margin of the rail + content ensemble, inside the box that centres it.
 *
 * Measured against that box and not against the window: the centring container is narrower than the
 * viewport by the scrollbar gutter the browser reserves (11px in headless Chromium), and a layout
 * cannot centre in space it was never given. Against the window every width read "11", which is
 * the gutter, not a skew.
 */
async function skew(page: Page): Promise<number> {
  return page.evaluate(() => {
    const ENSEMBLE = document.querySelector('main')!.parentElement!.parentElement!;
    const CONTAINER = ENSEMBLE.parentElement!.getBoundingClientRect();
    const BOX = ENSEMBLE.getBoundingClientRect();

    return Math.round(BOX.left - CONTAINER.left) - Math.round(CONTAINER.right - BOX.right);
  });
}

test.describe('the narrow layout is centred', () => {
  for (const [label, collapsed] of [
    ['with the rail', '0'],
    ['with the rail collapsed', '1'],
  ] as const) {
    for (const width of [1440, 1920, 2560]) {
      test(`${label}, ${width}px`, async ({ page }) => {
        await openWith(page, collapsed, width);

        // One pixel of slack for sub-pixel rounding.
        expect(Math.abs(await skew(page))).toBeLessThanOrEqual(1);
      });
    }
  }
});
