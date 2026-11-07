import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * The relief utilities resolve to something.
 *
 * This exists because they did not, for two weeks, in fourteen places. `shadow-lift` and
 * `shadow-sink` are hand-written `@utility` declarations; the markup that wears them arrived in
 * an earlier back-port and the declarations did not follow. An undeclared utility class is not
 * an error — Tailwind simply never generates it — so every raised surface rendered flat with
 * nothing red, nothing logged, and nothing to notice except by looking at two products side by
 * side.
 *
 * A unit test cannot make this check: jsdom has no cascade, so `getComputedStyle` there returns
 * whatever was set inline. It has to be a real browser, and it has to read the COMPUTED value —
 * asserting that the class is in the class list is asserting the bug.
 */

async function signIn(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
}

test.describe('relief', () => {
  test.use({ viewport: { width: 360, height: 740 } });

  test('a lifted surface computes a real box-shadow', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');
    await page.locator('.mobile-navbar-root').waitFor();

    const shadows = await page.evaluate(() =>
      [...document.querySelectorAll('[class*="shadow-lift"], [class*="shadow-sink"]')].map(
        (element) => window.getComputedStyle(element).boxShadow
      )
    );

    expect(shadows.length).toBeGreaterThan(0);

    for (const shadow of shadows) {
      expect(shadow).not.toBe('none');
      expect(shadow).not.toBe('');
    }
  });

  test('every relief token the utilities read is defined by the active theme', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');

    const tokens = await page.evaluate(() => {
      const root = window.getComputedStyle(document.documentElement);
      return ['--relief-lift', '--relief-lift-lg', '--relief-sink', '--relief-edge-top'].map((name) => [
        name,
        root.getPropertyValue(name).trim(),
      ]);
    });

    // A theme that omits one of these ships an app whose surfaces are silently flat, so the
    // template marks them REQUIRED and this is what holds the template to it.
    for (const [name, value] of tokens) {
      expect(value, `${name} is not defined by the active theme`).not.toBe('');
    }
  });
});
