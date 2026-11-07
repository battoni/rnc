import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * The scrollbars the stylesheet promises, as the browser computed them.
 *
 * `scrollbar-color` takes TWO colours, thumb then track. It was written with one, which is an
 * invalid declaration: the browser drops it and paints its own default, and Chrome 121+ also stops
 * reading the `::-webkit-scrollbar` rules once `scrollbar-width` is set — so no rule of this file
 * reached Chrome or Firefox at all. And `scrollbar-brand` was used by the rail and the drawer and
 * defined nowhere. A unit test cannot see either: jsdom does not validate a declaration.
 */

test.use({ viewport: { width: 1280, height: 800 } });

test('every scroll surface gets the themed thumb over a canvas track, and scrollbar-brand exists', async ({ page }) => {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
  );
  await page.goto('/');

  const measured = await page.evaluate(() => {
    const resolve = (value: string) => {
      const probe = document.createElement('span');
      probe.style.color = value;
      document.body.appendChild(probe);
      const colour = window.getComputedStyle(probe).color;
      probe.remove();

      return colour;
    };

    const plain = document.createElement('div');
    const brand = document.createElement('div');
    brand.className = 'scrollbar-brand';
    document.body.append(plain, brand);

    const result = {
      plain: window.getComputedStyle(plain).scrollbarColor,
      brand: window.getComputedStyle(brand).scrollbarColor,
      brandWidth: window.getComputedStyle(brand).scrollbarWidth,
      expectedPlain: `${resolve('var(--color-line-strong)')} ${resolve('var(--color-canvas)')}`,
      expectedBrandThumb: resolve('var(--color-primary-700)'),
    };

    plain.remove();
    brand.remove();

    return result;
  });

  expect(measured.plain).toBe(measured.expectedPlain);
  expect(measured.brand).toBe(`${measured.expectedBrandThumb} rgba(0, 0, 0, 0)`);
  expect(measured.brandWidth).toBe('thin');
});
