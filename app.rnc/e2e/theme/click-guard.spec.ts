import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, paginated } from '../fixtures/auth';

/*
 * One intent, one action.
 *
 * A second click landing before the first one's work is done would run the handler twice — on a
 * "create" that is two rows in somebody's database, and on a vote or a delete it is worse. The
 * guard is a single capture-phase listener on `document`, so nothing opts in and a button written
 * next month is covered without its author knowing it exists.
 *
 * This has to be e2e: the guard's whole mechanism is the real DOM event path — capture phase,
 * `stopImmediatePropagation`, and a form's implicit submit. jsdom can be made to fake that, and
 * the unit suite does, but only a browser proves the seat is actually reached.
 */

const USER = { ...mockUser, permissions: ['users.read', 'users.create'] };

async function signIn(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: USER }) })
  );
  await page.route('**/auth/users**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: paginated([]) }) })
  );
  await page.route('**/auth/roles**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
  );
}

test.use({ viewport: { width: 1280, height: 900 } });

test('a second click on the same button is thrown away', async ({ page }) => {
  await signIn(page);
  await page.goto('/users');

  // A button of our own, so the test states the mechanism rather than depending on a screen.
  const counted = await page.evaluate(async () => {
    const button = document.createElement('button');
    let runs = 0;

    button.textContent = 'probe';
    button.addEventListener('click', () => {
      runs += 1;
    });
    document.body.appendChild(button);

    button.click();
    button.click();
    button.click();

    const out = runs;

    button.remove();

    return out;
  });

  expect(counted, 'three clicks reached the handler; the guard is not installed').toBe(1);
});

test('an opted-out button keeps every click', async ({ page }) => {
  await signIn(page);
  await page.goto('/users');

  const counted = await page.evaluate(async () => {
    const button = document.createElement('button');
    let runs = 0;

    // The escape hatch the guard documents, for a control that is genuinely repeatable.
    button.setAttribute('data-allow-rapid-click', '');
    button.addEventListener('click', () => {
      runs += 1;
    });
    document.body.appendChild(button);

    button.click();
    button.click();

    const out = runs;

    button.remove();

    return out;
  });

  expect(counted).toBe(2);
});
