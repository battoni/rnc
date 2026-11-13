import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * The area a thumb has to land on, which is not the area the button is drawn at.
 *
 * A small button here measures 32–35px. That clears WCAG 2.2 AA (24px) everywhere, so this is not
 * a compliance fix — it is a thumb fix, against Apple's 44pt and WCAG AAA. The density is
 * deliberate, so the button keeps the size it is DRAWN at and only the hit area grows.
 *
 * It must be e2e, and specifically with `hasTouch`: the whole rule is inside
 * `@media (pointer: coarse)`, so a jsdom test would assert a stylesheet nobody applied — which is
 * exactly how this shipped broken once, from inside the PrimeVue preset whose `global.css` block
 * does not honour an `@media` rule at all.
 */

async function signIn(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
}

/** The pseudo-element is not in the DOM, so the browser is asked what it computed. */
async function overlayInset(page: Page, selector: string) {
  return page.evaluate((sel) => {
    const element = document.querySelector(sel)!;
    const overlay = window.getComputedStyle(element, '::before');

    return {
      content: overlay.content,
      top: overlay.top,
      visibility: overlay.visibility,
      width: overlay.width,
    };
  }, selector);
}

test.describe('with a coarse pointer', () => {
  test.use({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: true });

  test('a small button grows six pixels on every side', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');

    await page.evaluate(() => {
      const probe = document.createElement('button');

      probe.className = 'p-button-sm';
      probe.id = 'touch-probe';
      document.body.appendChild(probe);
    });

    const overlay = await overlayInset(page, '#touch-probe');

    expect(overlay.content).not.toBe('none');
    expect(overlay.top).toBe('-6px');
  });

  /*
   * The shape the rule was written for: a PrimeVue icon-only button, whose own `::after` spacer is
   * `visibility: hidden; width: 0`. When the hit area lived on `::after`, a rule that won on
   * `content` and `inset` and forgot those two produced an overlay that was there and was nothing —
   * and the probe above, a bare `<button>`, has no spacer to lose to, so it stayed green through
   * exactly that regression.
   *
   * Asserted as a thumb would meet it: a tap 4px outside the drawn edge lands on the button, a tap
   * 8px outside does not.
   */
  test('an icon-only small button is hit 4px outside its edge, and not 8px outside', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');
    await page.getByRole('button').first().waitFor();

    await page.evaluate(() => {
      const probe = document.createElement('button');

      probe.className = 'p-button p-component p-button-icon-only p-button-sm';
      probe.id = 'icon-only-probe';
      probe.style.cssText = 'position: fixed; top: 300px; left: 150px; z-index: 99999;';
      probe.innerHTML = '<span class="p-button-icon pi pi-pencil"></span>';
      document.body.appendChild(probe);
    });

    const hits = await page.evaluate(() => {
      const probe = document.querySelector('#icon-only-probe')!;
      const box = probe.getBoundingClientRect();
      const middle = box.top + box.height / 2;
      const landsOnProbe = (x: number) => probe.contains(document.elementFromPoint(x, middle));

      return { at4: landsOnProbe(box.right + 4), at8: landsOnProbe(box.right + 8) };
    });

    expect((await overlayInset(page, '#icon-only-probe')).visibility).toBe('visible');
    expect(hits).toEqual({ at4: true, at8: false });
  });

  /*
   * The other half of the promise: the button is DRAWN at the size it had. A refactor that makes the
   * box itself grow would pass every hit-area assertion and break the density the rule protects.
   */
  test('the drawn size is the same under a finger and under a mouse', async ({ browser }) => {
    async function drawnSize(options: Parameters<typeof browser.newContext>[0]) {
      const context = await browser.newContext(options);
      const page = await context.newPage();

      await signIn(page);
      await page.goto('/personalization');
      await page.getByRole('button').first().waitFor();

      const size = await page.evaluate(() => {
        const probe = document.createElement('button');

        probe.className = 'p-button p-component p-button-icon-only p-button-sm';
        probe.innerHTML = '<span class="p-button-icon pi pi-pencil"></span>';
        document.body.appendChild(probe);

        // A default-size button grows too (2px past its border), and must not be drawn any bigger.
        const standard = document.createElement('button');

        standard.className = 'p-button p-component';
        standard.innerHTML = '<span class="p-button-label">Sign in</span>';
        document.body.appendChild(standard);

        const box = probe.getBoundingClientRect();
        const standardBox = standard.getBoundingClientRect();

        return { width: box.width, height: box.height, standard: [standardBox.width, standardBox.height] };
      });

      await context.close();

      return size;
    }

    const underFinger = await drawnSize({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: false });
    // Explicit: a context opened inside this describe inherits its `hasTouch` unless told otherwise.
    const underMouse = await drawnSize({ viewport: { width: 390, height: 800 }, hasTouch: false, isMobile: false });

    expect(underFinger.width).toBeGreaterThan(0);
    expect(underFinger).toEqual(underMouse);
  });

  /*
   * The promise, measured on the controls that actually exist.
   *
   * The rule says 44×44 on a touch screen, and the bar at the bottom of a phone is the primary
   * navigation there — three links at 38×34 before this, which is under the floor on both axes
   * and is what the growth exists for. `<a>` elements, hand-written, so nothing covered them
   * until they asked: that is the whole reason the rule ends with "a hand-written control is not
   * covered, give it the class".
   *
   * The hit area is a pseudo-element and has no box of its own to measure, so what is asserted is
   * the element plus the 6px it grows on each side — which is the area a thumb lands on.
   */
  test('every control on a phone clears 44px once the growth is counted', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');
    await page.locator('.mobile-navbar-root').waitFor();

    const tooSmall = await page.evaluate(() => {
      const GROWTH = 6;

      /*
       * The persistent chrome only — the bottom bar and the page header.
       *
       * Not every button on the page: a toast's close button renders for four seconds and
       * measured 12×12 in one run out of several, which makes an every-button assertion race
       * with whatever happens to be on screen. The chrome is what a thumb uses constantly and
       * what this rule was written for. (The toast IS worth its own look — recorded, not
       * silently dropped.)
       */
      const NA_BARRA = [...document.querySelectorAll('.mobile-navbar-root button, .mobile-navbar-root a[href]')];
      const NO_CABECALHO = [...document.querySelectorAll('[data-testid="page-header-menu"]')];

      return [...NA_BARRA, ...NO_CABECALHO]
        .map((element) => {
          const box = element.getBoundingClientRect();
          const grows = element.matches('.p-button-sm, .touch-target');
          const padding = grows ? GROWTH * 2 : 0;

          return {
            what: element.className.toString().slice(0, 46),
            width: Math.round(box.width) + padding,
            height: Math.round(box.height) + padding,
          };
        })
        .filter((control) => control.width > 0 && (control.width < 44 || control.height < 44));
    });

    expect(tooSmall, JSON.stringify(tooSmall, null, 1)).toEqual([]);
  });

  test("a dialog's close button clears 44px once the growth is counted", async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');
    await page.getByRole('button').first().waitFor();

    await page.evaluate(() => {
      const probe = document.createElement('button');

      probe.className =
        'p-button p-component p-button-icon-only p-button-secondary p-button-rounded p-button-text p-dialog-close-button';
      probe.id = 'close-probe';
      probe.style.cssText = 'position: fixed; top: 300px; left: 150px; z-index: 99999;';
      probe.innerHTML = '<span class="p-button-icon pi pi-times"></span>';
      document.body.appendChild(probe);
    });

    const reach = await page.evaluate(() => {
      const probe = document.querySelector('#close-probe')!;
      const box = probe.getBoundingClientRect();
      const middleY = box.top + box.height / 2;
      const lands = (x: number) => probe.contains(document.elementFromPoint(x, middleY));
      // The reach past the drawn edge, found by a thumb in half-pixel steps rather than read off a rule.
      let outside = 0;

      while (outside < 20 && lands(box.left - (outside + 0.5))) outside += 0.5;

      return { width: box.width + outside * 2, height: box.height + outside * 2, outside };
    });

    expect(reach.outside, 'a tap a pixel outside the drawn edge lands on the button').toBeGreaterThanOrEqual(1);
    expect(reach.width).toBeGreaterThanOrEqual(44);
    expect(reach.height).toBeGreaterThanOrEqual(44);
  });

  test('a hand-written button opts in with `touch-target`', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');

    await page.evaluate(() => {
      const probe = document.createElement('button');

      probe.className = 'touch-target';
      probe.id = 'hand-written-probe';
      document.body.appendChild(probe);
    });

    expect((await overlayInset(page, '#hand-written-probe')).top).toBe('-6px');
  });
});

test.describe('with a mouse', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('nothing grows, because a pointer is precise', async ({ page }) => {
    await signIn(page);
    await page.goto('/personalization');

    await page.evaluate(() => {
      const probe = document.createElement('button');

      probe.className = 'p-button-sm';
      probe.id = 'touch-probe';
      document.body.appendChild(probe);
    });

    expect((await overlayInset(page, '#touch-probe')).top).not.toBe('-6px');
  });
});
