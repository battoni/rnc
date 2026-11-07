import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, paginated } from '../fixtures/auth';

/*
 * The mask paints the gutters, and the sheet takes the whole screen on a phone.
 *
 * The sheet does not fill its mask horizontally and the mask used to paint nothing, so the two
 * gutters were a hole straight through to the page: the cards behind slid down them as a rounded
 * edge every time a dialog opened. It shows at 768–1023 in particular, where the page's own
 * padding and the sheet's margin disagree.
 *
 * Every number here is measured in a real browser, because that is the only place a mask and a
 * sheet have positions at all.
 */

const USER = { ...mockUser, permissions: ['users.read', 'users.create'] };

async function openTheDialog(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: USER }) })
  );
  await page.route('**/auth/users**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: paginated([USER]) }) })
  );
  await page.route('**/auth/roles**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
  );

  await page.goto('/users');
  await page
    .getByRole('button', { name: /add|adicionar|novo|new/i })
    .first()
    .click();
  await page.locator('.p-dialog').waitFor();
  await animationsSettled(page);
}

/**
 * The sheet and its mask animate in, and their geometry is only final once that is over. Waited for
 * as an event — every finite animation on the page finishing — rather than slept (e2e/RULES.md).
 */
async function animationsSettled(page: Page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    const finite = document
      .getAnimations()
      .filter((animation) => animation.effect?.getComputedTiming().endTime !== Infinity);

    await Promise.all(finite.map((animation) => animation.finished.catch(() => undefined)));
  });
}

async function geometry(page: Page) {
  return page.evaluate(() => {
    const mask = document.querySelector('.p-dialog-mask') as HTMLElement;
    const sheet = document.querySelector('.p-dialog') as HTMLElement;
    const maskBox = mask.getBoundingClientRect();
    const sheetBox = sheet.getBoundingClientRect();

    return {
      gutterLeft: Math.round(sheetBox.left - maskBox.left),
      gutterRight: Math.round(maskBox.right - sheetBox.right),
      maskBackground: window.getComputedStyle(mask).backgroundColor,
      canvas: window.getComputedStyle(document.documentElement).getPropertyValue('--color-canvas').trim(),
      radius: window.getComputedStyle(sheet).borderTopLeftRadius,
    };
  });
}

test.describe('the dialog mask — from md up', () => {
  for (const width of [768, 1024, 1440]) {
    test(`paints its gutters at ${width}, so nothing shows through`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await openTheDialog(page);

      const measured = await geometry(page);

      // The sheet keeps the page's own 32px rhythm, which is what makes it read as resting ON a
      // desk rather than welded to the frame. Symmetric, or the sheet is not centred.
      expect(measured.gutterLeft).toBe(32);
      expect(measured.gutterRight).toBe(32);

      // Painted, not transparent. Canvas rather than a scrim: this mask is scoped to `<main>`, so
      // a dark frame would end in a hard line under the page header.
      expect(measured.maskBackground).not.toBe('rgba(0, 0, 0, 0)');
      expect(measured.maskBackground).not.toBe('transparent');
    });
  }
});

test.describe('the dialog on a phone', () => {
  test('takes the whole screen, with no gutters left to paint', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await openTheDialog(page);

    const measured = await geometry(page);

    expect(measured.gutterLeft).toBe(0);
    expect(measured.gutterRight).toBe(0);

    // Square, because a rounded sheet against the screen edge shows the corner as a notch.
    expect(measured.radius).toBe('0px');
  });
});

/*
 * On a real phone (`isMobile`) the layout viewport stretches to whatever overflows it. The form's
 * gap filler — `absolute bottom-0 w-full`, with no `left` — sat at its static position, 20px in,
 * and ended 20px past the sheet: the page became 410px wide, the phone zoomed to fit it, and the
 * mask, the sheet and `<main>` all measured 370px of a 390px screen, with a strip of page showing
 * on the right. Measured against the screen, not against the mask.
 */
test.describe('the dialog on a touch phone', () => {
  test.use({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: true });

  test('spans the whole screen and nothing in it runs past the edge', async ({ page }) => {
    await openTheDialog(page);

    const measured = await page.evaluate(() => {
      const mask = document.querySelector('.p-dialog-mask')!.getBoundingClientRect();
      const sheet = document.querySelector('.p-dialog')!.getBoundingClientRect();

      return {
        pageWidth: document.documentElement.scrollWidth,
        mask: [mask.left, mask.width],
        sheet: [sheet.left, sheet.width],
      };
    });

    expect(measured.pageWidth, 'the page is wider than the screen').toBe(390);
    expect(measured.mask).toEqual([0, 390]);
    expect(measured.sheet).toEqual([0, 390]);
  });
});
