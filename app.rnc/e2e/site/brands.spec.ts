import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * A2 and A6 (rnc-3 F2, R2, R3, R5), with the API mocked by `X-Brand`: `?marca=express` re-themes the
 * whole site — colours on the document root, the brand's name in the header and the title — over
 * Franco's own phone; a reload keeps it, `?marca=franco` drops it, a brand the API refuses lands on
 * Franco. The operator's "Ver como" tab (`previa=1`) wears the banner; a visitor's does not. Under each
 * sub-brand, in both schemes, what the site paints in the brand's colours still reads (4,5:1), and
 * nothing scrolls sideways at 390 or 1440.
 */
const FIRM = {
  name: 'Franco Contabilidade',
  slug: 'franco',
  brand: null,
  logo_text: 'Franco Contabilidade',
  short_name: null,
  tagline: null,
  proof: null,
  brand_primary: '#00375a',
  brand_accent: '#14c3ff',
  address: 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
  phone: '11 3732-5500',
  phone_href: 'tel:+551137325500',
  mailbox: 'saf@franco-rnc.com.br',
  business_hours: [
    { weekdays: [1, 2, 3, 4], from: '08:00', to: '18:00' },
    { weekdays: [5], from: '08:00', to: '17:00' },
  ],
};

const BRANDS = {
  express: {
    ...FIRM,
    name: 'Franco Express Contabilidade',
    brand: 'express',
    logo_text: 'Franco Express Contabilidade',
    short_name: 'Franco Express',
    tagline: 'A contabilidade da Franco no ritmo da pequena empresa',
    brand_primary: '#3b2a86',
    brand_accent: '#ff8a3d',
    proof: [
      { value: '35 anos', label: 'da Franco em São Paulo' },
      { value: 'Pequenas', label: 'empresas e Simples Nacional' },
      { value: 'Online', label: 'rotina sem papel' },
      { value: 'RNC', label: 'Rede Nacional de Contabilidade' },
    ],
  },
  prime: {
    ...FIRM,
    name: 'Franco Prime Contabilidade',
    brand: 'prime',
    logo_text: 'Franco Prime Contabilidade',
    short_name: 'Franco Prime',
    tagline: 'A Franco dedicada às empresas de maior porte',
    brand_primary: '#24262b',
    brand_accent: '#c9a35a',
    proof: [
      { value: '35 anos', label: 'da Franco em São Paulo' },
      { value: 'Médias e grandes', label: 'empresas, Presumido e Real' },
      { value: 'Sócio', label: 'dedicado a cada conta' },
      { value: 'ISO 9001', label: 'qualidade certificada' },
    ],
  },
} as const;

const brandsSent: (string | null)[] = [];

async function mockSiteApi(page: Page) {
  const json = (data: unknown, status = 200) => ({
    status,
    contentType: 'application/json',
    body: JSON.stringify(data),
  });

  await page.route('**/public/firm', (route) => {
    const brand = route.request().headers()['x-brand'] ?? null;

    brandsSent.push(brand);

    if (!brand) return route.fulfill(json({ data: FIRM }));

    const known = BRANDS[brand as keyof typeof BRANDS];

    return route.fulfill(
      known ? json({ data: known }) : json({ data: null, error: 'brands.notFound', status_code: 404 }, 404)
    );
  });
  await page.route('**/public/sectors', (route) => route.fulfill(json({ data: [] })));
  await page.route('**/public/events', (route) =>
    route.fulfill({ status: 201, contentType: 'application/json', body: '[]' })
  );
}

function rootColour(page: Page, property: string) {
  return page.evaluate((name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim(), property);
}

function luminance([r, g, b]: number[]) {
  const channels = [r, g, b].map((value) => {
    const srgb = value! / 255;

    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * channels[0]! + 0.7152 * channels[1]! + 0.0722 * channels[2]!;
}

function contrastRatio(first: number[], second: number[]) {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

  return (lighter! + 0.05) / (darker! + 0.05);
}

function parseRgb(colour: string) {
  return (colour.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map(Number);
}

/**
 * Each element's text colour over the first painted background at or above it, both read back as
 * `rgb()` through a canvas: the theme mixes in `oklab` (`bg-canvas/90`), which no regex reads, and a
 * translucent ground is laid over the page's own canvas as the eye sees it.
 */
async function renderedPairs(page: Page, selector: string) {
  return page.evaluate((query) => {
    const context = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!;
    const page = window.getComputedStyle(document.body).backgroundColor;

    function toRgb(colour: string, under = page): string {
      context.clearRect(0, 0, 1, 1);
      context.fillStyle = under;
      context.fillRect(0, 0, 1, 1);
      context.fillStyle = colour;
      context.fillRect(0, 0, 1, 1);

      const [red, green, blue] = context.getImageData(0, 0, 1, 1).data;

      return `rgb(${red}, ${green}, ${blue})`;
    }

    function ground(element: Element | null): string {
      for (let node = element; node; node = node.parentElement) {
        const background = window.getComputedStyle(node).backgroundColor;

        if (background !== 'rgba(0, 0, 0, 0)' && !background.endsWith(', 0)') && !background.endsWith('/ 0)')) {
          return toRgb(background);
        }
      }

      return toRgb(page, '#ffffff');
    }

    return [...document.querySelectorAll(query)].map((element) => {
      const background = ground(element);

      return {
        label: `${query} "${element.textContent?.trim().slice(0, 24)}"`,
        colour: toRgb(window.getComputedStyle(element).color, background),
        background,
      };
    });
  }, selector);
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

test.beforeEach(async ({ page }) => {
  brandsSent.length = 0;
  await seedLanguage(page, 'pt-BR');
  await mockSiteApi(page);
});

test.describe('the site under a sub-brand (A2)', () => {
  test("re-themes to Express over Franco's phone, keeps it on reload and drops it on ?marca=franco", async ({
    page,
  }) => {
    await page.goto('/?marca=express');

    await expect(page.getByRole('banner')).toContainText('Franco Express Contabilidade');
    await expect(page.getByTestId('hero-kicker')).toContainText(
      'A contabilidade da Franco no ritmo da pequena empresa'
    );
    await expect(page.getByTestId('proof')).toContainText('Pequenas');
    await expect(page.getByTestId('site-footer').locator('a[href="tel:+551137325500"]')).toBeVisible();
    await expect(page).toHaveTitle(/· Franco Express Contabilidade$/);
    await expect(page.locator('html')).toHaveAttribute('data-brand', 'express');
    expect(await rootColour(page, '--color-primary-500')).toBe('#3b2a86');

    await page.goto('/');

    await expect(page.getByRole('banner')).toContainText('Franco Express Contabilidade');
    // Every public read carries the brand, except the host firm's own boot read (rnc-4 D10), which
    // paints the firm's colours under the back office and is never the brand's.
    expect(brandsSent.filter((brand) => brand !== null).every((brand) => brand === 'express')).toBe(true);
    expect(brandsSent).toContain('express');

    await page.goto('/?marca=franco');

    await expect(page.getByRole('banner')).toContainText('Franco Contabilidade');
    await expect(page.getByRole('banner')).not.toContainText('Express');
    // rnc-4 R10: back on the main brand the root wears the FIRM's palette, painted from its row.
    await expect(page.locator('html')).toHaveAttribute('data-brand', 'franco');
    expect(await rootColour(page, '--color-primary-500')).toBe('#00375a');
  });

  // Chrome QA 4.11/4.12: a lead started after `?marca=prime` then `?marca=principal` is the main brand's.
  test('starts the diagnostic under the main brand after ?marca=prime then ?marca=principal', async ({ page }) => {
    const startedUnder: (string | null)[] = [];

    await page.route('**/public/diagnostics', (route) => {
      startedUnder.push(route.request().headers()['x-brand'] ?? null);

      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            cnpj: null,
            company: null,
            consent: { text: 'Autorizo a Franco Contabilidade a me apresentar', version: 1 },
            has_contact: false,
            has_email: false,
            last_step: 'empresa',
            path: 'quiz',
            profile: {},
            stage: 'visitante',
            token: 'tok-main',
          },
        }),
      });
    });

    await page.goto('/diagnostico?marca=prime');
    await page.getByTestId('path-quiz').click();
    await expect.poll(() => startedUnder.length).toBe(1);

    await page.goto('/?marca=principal');
    await expect(page.getByRole('banner')).not.toContainText('Prime');
    await page.goto('/diagnostico');
    await expect(page).toHaveTitle(/· Franco Contabilidade$/);
    await page.getByTestId('path-quiz').click();
    await expect.poll(() => startedUnder.length).toBe(2);

    // The first proves the header is seen; the second is the main brand's.
    expect(startedUnder).toEqual(['prime', null]);
    expect(brandsSent.slice(brandsSent.lastIndexOf('prime') + 1)).not.toContain('prime');
  });

  test('lands a brand the API refuses on Franco, out of the address bar too (AM21)', async ({ page }) => {
    await page.goto('/?marca=velha');

    await expect(page.getByRole('banner')).toContainText('Franco Contabilidade');
    await expect(page).toHaveURL('/');
    // The refused brand is asked again as the main brand (the host firm's boot read may come first).
    expect(brandsSent).toContain('velha');
    expect(brandsSent.slice(brandsSent.indexOf('velha') + 1)).toContain(null);
  });
});

test.describe('"Ver como" (A6)', () => {
  test("wears the preview banner on the operator's tab, never on a visitor's", async ({ page, browser }) => {
    await page.goto('/?marca=express&previa=1');

    await expect(page.getByTestId('brand-preview-banner')).toHaveText(
      'Prévia: o site como a Franco Express Contabilidade aparece ao visitante.'
    );

    const visitor = await browser.newPage();

    await seedLanguage(visitor, 'pt-BR');
    await mockSiteApi(visitor);
    await visitor.goto('/?marca=express');

    await expect(visitor.getByRole('banner')).toContainText('Franco Express Contabilidade');
    await expect(visitor.getByTestId('brand-preview-banner')).toHaveCount(0);
    await visitor.close();
  });
});

const BRAND_PAINTED = [
  '[data-testid="header-cta"]',
  '[data-testid="hero-cta"]',
  // The wordmark's words, and the "F" tile of the narrow header (white on the brand's fill).
  'header .text-brand-word > span:not([role="img"])',
  'header [role="img"]',
  'footer .text-brand-word > span',
  '[data-testid="brand-preview-banner"] p',
];

for (const slug of Object.keys(BRANDS)) {
  for (const scheme of ['light', 'dark'] as const) {
    test(`${slug}: what the site paints in the brand's colours reads at 4.5:1, ${scheme}`, async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 });
      await page.emulateMedia({ colorScheme: scheme });
      await page.goto(`/?marca=${slug}&previa=1`);
      await expect(page.locator('html')).toHaveAttribute('data-brand', slug);
      await expect(page.locator('html')).toHaveAttribute('data-theme', scheme);
      await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; }' });

      const pairs = (await Promise.all(BRAND_PAINTED.map((selector) => renderedPairs(page, selector)))).flat();

      expect(pairs.length).toBeGreaterThanOrEqual(BRAND_PAINTED.length);

      for (const pair of pairs) {
        const ratio = contrastRatio(parseRgb(pair.colour), parseRgb(pair.background));

        expect(
          ratio,
          `${pair.label} (${scheme}): ${ratio.toFixed(2)}:1 ${pair.colour} on ${pair.background}`
        ).toBeGreaterThanOrEqual(4.5);
      }
    });
  }

  for (const width of [390, 1440]) {
    test(`${slug}: nothing scrolls sideways at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/?marca=${slug}&previa=1`);

      await expect(page.getByTestId('proof')).toContainText(BRANDS[slug as keyof typeof BRANDS].proof[1].value);
      expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
    });
  }
}
