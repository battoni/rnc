import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * The app column is as tall as the viewport a person can SEE, not as tall as the chain above it.
 *
 * `#app { height: 100% }` inherits from html and body, which follow the LAYOUT viewport — on a phone
 * the tall one, which excludes a collapsible URL bar that is on screen right now. The column came
 * out taller than the window and the document scrolled behind a column that was already scrolling.
 *
 * No emulator draws a retractable URL bar, so the test builds the same situation by hand: it makes
 * the chain taller than the window and asks whether `#app` followed the chain or the window.
 */

test.use({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: true });

test('#app follows the visible viewport, not the height of html and body', async ({ page }) => {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
  await page.goto('/painel');
  await page.locator('#app').waitFor();

  const measured = await page.evaluate(() => {
    document.documentElement.style.height = '1600px';
    document.body.style.height = '1600px';

    return {
      app: document.querySelector('#app')!.getBoundingClientRect().height,
      visible: window.innerHeight,
    };
  });

  expect(measured.app).toBe(measured.visible);
});
