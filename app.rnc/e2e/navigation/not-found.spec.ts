import { expect, mockUser, seedAuthSession, test } from '../fixtures/auth';

/*
 * An address nobody can serve, reached the way people reach it: typed or pasted, a hard navigation.
 *
 * Both audiences are asserted because the two flags the guards knew were each wrong for one of
 * them: as a public route a signed-in person was sent Home, as a private one a signed-out visitor
 * was sent to the login screen. Either way the typo was silently replaced by a page nobody asked
 * for. The unit specs cover the guard's decision; only a real navigation covers `TheRouteGuard`.
 */

const TYPO = '/pagina-que-nao-existe';

test.describe('an address that matches nothing', () => {
  test('tells a signed-out visitor so, instead of asking them to sign in', async ({ page }) => {
    await page.goto(TYPO);

    await expect(page.getByTestId('not-found')).toBeVisible();
    await expect(page).toHaveURL(TYPO);
  });

  test('tells a signed-in person so, instead of sending them home', async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );

    await page.goto(TYPO);

    await expect(page.getByTestId('not-found')).toBeVisible();
    await expect(page).toHaveURL(TYPO);
  });

  // Leaving 404 via path push (`push('/')`) under out-in left Home empty until a hard reload.
  // Named push in NotFoundView is the fix — do not drop out-in on App.vue for this (that invented
  // global page scrollbars).
  test('returns a signed-in person to a visible Home without reloading', async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );

    await page.goto(TYPO);
    await expect(page.getByTestId('not-found')).toBeVisible();

    await page.getByRole('button', { name: /back to start|voltar ao início/i }).click();

    await expect(page).toHaveURL('/');
    await expect(page.getByTestId('not-found')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Home' })).toBeVisible();
  });
});
