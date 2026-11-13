import type { Page } from '@playwright/test';
import { test, expect, mockUser, paginated, seedAuthSession } from '../fixtures/auth';

/*
 * The shared controls a thumb meets on every screen, measured under a finger.
 *
 * `touch-target.spec.ts` proves the growth works and that the chrome clears 44px once it is
 * counted. It did not see three things, and each shipped:
 *
 * - two grown controls OVERLAPPING — the bottom bar's links sat 2–8px apart while each grew 6px,
 *   so the later sibling won the shared strip (celer-06, "gap-3 is not cosmetic");
 * - a grown control CLIPPED — the links live in an `overflow-x-auto` box, which clips on both
 *   axes, so the 6px above and below were never there;
 * - controls that never grow at all: a default-size icon button (the header CTA, the pager, the
 *   list row actions, the drawer's child rows) is not `.p-button-sm`, so it keeps its drawn size.
 *
 * A thumb is asked, not the stylesheet: `elementFromPoint` just outside each edge.
 */

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

const GROWTH = 6;
const FLOOR = 44;
const MIN_GAP = 12;

const DESIGN_USER = mockUser;

const PEOPLE = Array.from({ length: 5 }, (_, index) => ({
  ...mockUser,
  id: index + 1,
  name: `Person ${index + 1}`,
  username: `person${index + 1}`,
}));

function json(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signIn(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: DESIGN_USER })));
  await page.route('**/auth/users**', (route) =>
    route.fulfill(json({ data: { ...paginated(PEOPLE), last_page: 3, total: 45 } }))
  );
  // The dev server's inspector pill floats over the bottom bar; it is not the app.
  await page.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => {
      const style = document.createElement('style');
      style.textContent = '#__vue-devtools-container__, [id^="vue-devtools"] { display: none !important; }';
      document.head.appendChild(style);
    });
  });
}

/** A box mid-entrance is scaled or moved; measure it where it comes to rest. */
async function settle(page: Page) {
  await page.evaluate(async () => {
    const finite = document
      .getAnimations()
      .filter((animation) => animation.effect?.getComputedTiming().endTime !== Infinity);

    await Promise.all(finite.map((animation) => animation.finished.catch(() => undefined)));
  });
}

interface Measured {
  name: string;
  left: number;
  right: number;
  top: number;
  bottom: number;
  grows: boolean;
  hits: { above: boolean; below: boolean; left: boolean; right: boolean };
}

/** Each control's drawn box, whether it grows, and whether a tap 4px past each edge lands on it. */
async function measure(page: Page, selector: string): Promise<Measured[]> {
  return page.evaluate(
    ({ query, growth }) =>
      [...document.querySelectorAll(query)]
        .filter((element) => element.getBoundingClientRect().width > 0)
        .map((element) => {
          const box = element.getBoundingClientRect();
          const grows = element.matches('.p-button-sm, .touch-target');
          const reach = grows ? growth - 2 : -2;
          const middleX = box.left + box.width / 2;
          const middleY = box.top + box.height / 2;
          const lands = (x: number, y: number) => element.contains(document.elementFromPoint(x, y));

          return {
            name: element.getAttribute('aria-label') ?? element.className.toString().slice(0, 40),
            left: box.left,
            right: box.right,
            top: box.top,
            bottom: box.bottom,
            grows,
            hits: {
              above: lands(middleX, box.top - reach),
              below: lands(middleX, box.bottom + reach),
              left: lands(box.left - reach, middleY),
              right: lands(box.right + reach, middleY),
            },
          };
        }),
    { query: selector, growth: GROWTH }
  );
}

function reachOf(control: Measured) {
  const extra = control.grows ? GROWTH : 0;

  return {
    width: control.right - control.left + extra * 2,
    height: control.bottom - control.top + extra * 2,
    left: control.left - extra,
    right: control.right + extra,
  };
}

function expectThumbSized(controls: Measured[]) {
  expect(controls.length).toBeGreaterThan(0);

  for (const control of controls) {
    const reach = reachOf(control);

    expect(reach.width, `${control.name} width`).toBeGreaterThanOrEqual(FLOOR);
    expect(reach.height, `${control.name} height`).toBeGreaterThanOrEqual(FLOOR);
  }
}

/** Neighbours on one row: the areas a thumb lands on never overlap, and the drawn boxes keep 12px. */
function expectApart(controls: Measured[]) {
  /*
   * Grouped by row first, and that is not a detail: a control that wrapped onto the next line is
   * not a neighbour of the one before it, and comparing the two measures nothing. Found in
   * new.impressao, where the pager wraps at 390px because the Portuguese page report is a longer
   * sentence than the English one — the check then read the horizontal distance between a button on
   * line 1 and one on line 2 as a 4px gap and failed on a layout that is correct.
   */
  const ROW_TOLERANCE = 4;
  const rows: Measured[][] = [];

  for (const control of [...controls].sort((first, second) => first.top - second.top || first.left - second.left)) {
    const row = rows.find((candidate) => Math.abs(candidate[0]!.top - control.top) <= ROW_TOLERANCE);

    if (row) {
      row.push(control);
      continue;
    }

    rows.push([control]);
  }

  for (const row of rows) {
    const sorted = [...row].sort((first, second) => first.left - second.left);

    for (let index = 1; index < sorted.length; index += 1) {
      const previous = sorted[index - 1]!;
      const current = sorted[index]!;

      expect(
        reachOf(current).left - reachOf(previous).right,
        `${previous.name} → ${current.name}: hit areas overlap`
      ).toBeGreaterThanOrEqual(0);
      expect(current.left - previous.right, `${previous.name} → ${current.name}: drawn gap`).toBeGreaterThanOrEqual(
        MIN_GAP
      );
    }
  }
}

test('the bottom bar: every control is reached 4px past each edge, and no two hit areas overlap', async ({ page }) => {
  await signIn(page);
  await page.goto('/users');
  await page.locator('.mobile-navbar-root').waitFor();

  const controls = await measure(page, '.mobile-navbar-root button, .mobile-navbar-root a[href]');

  expect(controls.length).toBeGreaterThanOrEqual(3);
  expectThumbSized(controls);
  expectApart(controls);

  for (const control of controls.filter((candidate) => candidate.grows)) {
    expect(control.hits, control.name).toEqual({ above: true, below: true, left: true, right: true });
  }
});

test('the page header action is 44px on a phone', async ({ page }) => {
  await signIn(page);
  await page.goto('/users');
  await expect(page.getByText('Person 1 Smith')).toBeVisible();

  const action = await measure(page, '[data-testid="add-user"]');

  expect(action).toHaveLength(1);
  expectThumbSized(action);
});

test('the pager buttons are 44px and 12px apart under a finger', async ({ page }) => {
  await signIn(page);
  await page.goto('/users');
  await expect(page.locator('.p-paginator')).toBeVisible();

  const buttons = await measure(page, '.p-paginator [data-pc-group-section="pagebutton"]');

  expect(buttons).toHaveLength(4);
  expectThumbSized(buttons);
  expectApart(buttons);
});

test("the drawer's child rows are 44px tall", async ({ page }) => {
  await signIn(page);
  await page.goto('/painel');
  await page.locator('.mobile-navbar-root button').first().click();

  const drawer = page.locator('[data-pc-name="drawer"]');
  await drawer.locator('[aria-expanded="false"]').first().click();
  await expect(drawer.locator('ul a').first()).toBeVisible();
  // The drawer slides in and the group unfolds: a row measured mid-move is a scaled row, and it
  // measured 41px of a 44px box in new.impressao before this wait existed.
  await settle(page);

  const rows = await measure(page, '[data-pc-name="drawer"] ul a');

  expect(rows.length).toBeGreaterThan(0);

  for (const row of rows) {
    expect(row.bottom - row.top, row.name).toBeGreaterThanOrEqual(FLOOR);
  }
});

test('the close buttons of the drawer and of the sheet are 44px', async ({ page }) => {
  await signIn(page);
  await page.route('**/auth/roles**', (route) => route.fulfill(json({ data: [] })));
  await page.goto('/users');
  await page.locator('.mobile-navbar-root button').first().click();
  await expect(page.locator('[data-pc-name="drawer"] button[aria-label="Close"]')).toBeVisible();
  await settle(page);

  const drawerClose = await measure(page, '[data-pc-name="drawer"] button[aria-label="Close"]');

  expect(drawerClose).toHaveLength(1);
  expectThumbSized(drawerClose);

  await page.locator('[data-pc-name="drawer"] button[aria-label="Close"]').click();
  await page.getByTestId('add-user').click();
  await expect(page.locator('.main-dialog-root')).toBeVisible();
  await settle(page);

  expectThumbSized(await measure(page, '.main-dialog-root .p-dialog-close-button'));
});
