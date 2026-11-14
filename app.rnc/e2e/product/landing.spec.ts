import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * rnc-4 A34 (R28, R29): the product's own site on the root host — the platform firm, `localhost`, no
 * subdomain. The landing for accounting firms renders under the platform's brand, never offers the
 * diagnostic, holds at 390, 1024, 1280 and 1920 px with nothing scrolling sideways, reads at 4.5:1
 * (3:1 for large text) in light and dark, and the beta form submits against the mocked API — from the
 * root, with no `X-Firm` (F6a: the beta routes 404 on any firm, `X-Firm: hub` included).
 */
const PLATFORM_FIRM = {
  address: null,
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  business_hours: [{ weekdays: [1, 2, 3, 4, 5], from: '09:00', to: '18:00' }],
  is_platform: true,
  logo_text: 'Hub da Reforma',
  mailbox: null,
  modules: [],
  name: 'Hub da Reforma',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  proof: null,
  short_name: 'Hub da Reforma',
  slug: 'hub',
};

const CONSENT = {
  text: 'Autorizo a equipe do Hub da Reforma a entrar em contato comigo e com o meu escritório sobre o programa beta.',
  version: 2,
};

interface SentSignup {
  body: Record<string, unknown>;
  firm: string | null;
}

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

/** The root host: the suite's base URL with the firm's subdomain taken off. */
function rootUrl(baseURL: string | undefined, path = '/') {
  const url = new URL(path, baseURL);

  url.hostname = 'localhost';

  return url.toString();
}

async function mockProductApi(page: Page, signups: SentSignup[] = []) {
  await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: PLATFORM_FIRM })));
  await page.route('**/api/public/beta-signups/consent', (route) => route.fulfill(json({ data: CONSENT })));
  await page.route('**/api/public/beta-signups', (route) => {
    const request = route.request();

    signups.push({
      body: request.postDataJSON() as Record<string, unknown>,
      firm: request.headers()['x-firm'] ?? null,
    });

    return route.fulfill(json({ data: { channel: 'beta', company: 'Contábil Horizonte' } }, 201));
  });
  await page.route('**/api/public/events', (route) => route.fulfill(json([], 201)));
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/**
 * Every visible piece of text with its colour over the ground it is painted on. The ground is
 * composited the way the eye sees it: translucent backgrounds up the tree laid over the first opaque
 * one (or the page), read back as `rgb()` through a canvas, which also reads the theme's `oklab`.
 */
async function textPairs(page: Page) {
  return page.evaluate(() => {
    const context = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!;

    function paint(layers: string[]): number[] {
      context.clearRect(0, 0, 1, 1);
      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, 1, 1);
      layers.forEach((layer) => {
        context.fillStyle = layer;
        context.fillRect(0, 0, 1, 1);
      });

      return [...context.getImageData(0, 0, 1, 1).data.slice(0, 3)];
    }

    // The alpha is the 4th number of `rgba(…)`, or what follows the slash of `oklab(… / a)`: an
    // `rgb(242, 169, 0)` — an accent with no blue — ends in ", 0)" and is perfectly opaque.
    function alphaOf(colour: string): number {
      if (colour === 'transparent') return 0;

      const slash = colour.match(/\/\s*([\d.]+%?)\s*\)$/);

      if (slash) return slash[1]!.endsWith('%') ? parseFloat(slash[1]!) / 100 : parseFloat(slash[1]!);

      const numbers = colour.match(/^rgba\(([^)]*)\)$/)?.[1]?.split(',');

      return numbers && numbers.length === 4 ? parseFloat(numbers[3]!) : 1;
    }

    function isTransparent(colour: string) {
      return alphaOf(colour) === 0;
    }

    function isOpaque(colour: string) {
      return alphaOf(colour) >= 1;
    }

    function groundLayers(element: Element): string[] {
      const layers: string[] = [];

      for (let node: Element | null = element; node; node = node.parentElement) {
        const background = window.getComputedStyle(node).backgroundColor;

        if (isTransparent(background)) continue;

        layers.unshift(background);

        if (isOpaque(background)) return layers;
      }

      return [window.getComputedStyle(document.body).backgroundColor, ...layers];
    }

    const elements = [...document.body.querySelectorAll('*')].filter((element) => {
      const hasText = [...element.childNodes].some(
        (node) => node.nodeType === Node.TEXT_NODE && node.textContent!.trim()
      );
      const style = window.getComputedStyle(element);
      const box = element.getBoundingClientRect();

      return hasText && style.visibility === 'visible' && style.display !== 'none' && box.width > 0 && box.height > 0;
    });

    return elements.map((element) => {
      const style = window.getComputedStyle(element);
      const ground = groundLayers(element);
      const size = parseFloat(style.fontSize);
      const isLarge = size >= 24 || (size >= 18.66 && Number(style.fontWeight) >= 700);

      return {
        background: paint(ground),
        colour: paint([...ground, style.color]),
        isLarge,
        label: `${element.tagName.toLowerCase()} "${element.textContent!.trim().slice(0, 32)}"`,
      };
    });
  });
}

function luminance(rgb: number[]) {
  const [red, green, blue] = rgb.map((value) => {
    const channel = value / 255;

    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * red! + 0.7152 * green! + 0.0722 * blue!;
}

function contrastRatio(first: number[], second: number[]) {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((one, other) => other - one);

  return (lighter! + 0.05) / (darker! + 0.05);
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
});

test.describe('the product landing on the root', () => {
  test('speaks to accounting firms under the platform brand, with the CBS date and the beta CTA', async ({
    page,
    baseURL,
  }) => {
    await mockProductApi(page);
    await page.goto(rootUrl(baseURL));

    await expect(page.getByTestId('product-landing')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Seus clientes vão perguntar da Reforma.');
    await expect(page.getByTestId('product-name')).toHaveText('Hub da Reforma');
    await expect(page.getByRole('banner')).toContainText('Hub da Reforma');
    await expect(page.getByTestId('header-cta')).toHaveText('Quero participar do beta');
    await expect(page.getByTestId('countdown').locator('[data-part]')).toHaveCount(4);
    await expect(page.getByTestId('product-fact')).toHaveCount(3);
    await expect(page.getByTestId('product-demo-link')).toHaveAttribute(
      'href',
      rootUrl(baseURL).replace('localhost', 'alvorada.localhost')
    );
    await expect(page.getByTestId('product-beta')).toContainText(
      'Preço promocional para os escritórios do beta — fale com a gente'
    );
    await expect(page).toHaveTitle('Hub da Reforma Tributária para escritórios de contabilidade · Hub da Reforma');
    await expect(page.locator('a[href*="diagnostico"]')).toHaveCount(0);
    await expect(page.getByTestId('hero-cta')).toHaveCount(0);
  });

  test("sends a visitor who asks for the diagnostic to the product's landing", async ({ page, baseURL }) => {
    await mockProductApi(page);
    await page.goto(rootUrl(baseURL, '/diagnostico'));

    await expect(page.getByTestId('product-landing')).toBeVisible();
    await expect(page).toHaveURL(rootUrl(baseURL));
    await expect(page.getByTestId('diagnostic')).toHaveCount(0);
  });

  test('takes the header CTA to the beta form', async ({ page, baseURL }) => {
    await mockProductApi(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(rootUrl(baseURL));

    await page.getByTestId('header-cta').click();

    await expect(page.getByTestId('beta-form')).toBeInViewport();
  });

  for (const width of [390, 1024, 1280, 1920]) {
    test(`nothing scrolls sideways at ${width} px`, async ({ page, baseURL }) => {
      await mockProductApi(page);
      await page.setViewportSize({ width, height: 900 });
      await page.goto(rootUrl(baseURL));
      await expect(page.getByTestId('beta-consent-text')).toBeVisible();

      expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

      const cta = await page.getByTestId('header-cta').boundingBox();

      expect(cta!.x + cta!.width).toBeLessThanOrEqual(width);
    });
  }

  for (const scheme of ['light', 'dark'] as const) {
    test(`every text reads at 4.5:1 (large text 3:1), ${scheme}`, async ({ page, baseURL }) => {
      await mockProductApi(page);
      await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(rootUrl(baseURL));
      await expect(page.locator('html')).toHaveAttribute('data-theme', scheme);
      await expect(page.getByTestId('beta-consent-text')).toBeVisible();
      await expect(page.getByRole('banner').getByText('Hub da Reforma').first()).toBeVisible();
      await page.addStyleTag({
        content: '*, *::before, *::after { transition: none !important; animation: none !important; }',
      });

      const pairs = await textPairs(page);

      expect(pairs.length).toBeGreaterThan(60);

      const failing = pairs
        .map((pair) => ({ ...pair, ratio: contrastRatio(pair.colour, pair.background) }))
        .filter((pair) => pair.ratio < (pair.isLarge ? 3 : 4.5))
        .map((pair) => `${pair.label}: ${pair.ratio.toFixed(2)}:1 rgb(${pair.colour}) on rgb(${pair.background})`);

      expect(failing).toEqual([]);
    });
  }
});

test.describe('the beta form', () => {
  test('submits the firm against the mocked API from the root, with no X-Firm, and shows the success state', async ({
    page,
    baseURL,
  }) => {
    const signups: SentSignup[] = [];

    await mockProductApi(page, signups);
    await page.goto(rootUrl(baseURL, '/#beta'));
    await expect(page.getByTestId('beta-consent-text')).toHaveText(CONSENT.text);

    await page.getByTestId('beta-company').fill('Contábil Horizonte');
    await page.getByTestId('beta-name').fill('Marina Duarte');
    await page.getByTestId('beta-role').fill('Sócia');
    await page.getByTestId('beta-email').fill('marina@horizonte.example');
    await page.getByTestId('beta-phone').fill('(31) 98888-7777');
    await page.getByTestId('beta-city').fill('Belo Horizonte');
    await page.getByTestId('beta-uf').click();
    await page.getByRole('option', { name: 'Minas Gerais' }).click();
    await page.getByTestId('beta-band-de_101_a_300').click();
    await page.getByTestId('beta-consent').click();
    await page.getByTestId('beta-submit').click();

    await expect(page.getByTestId('beta-success')).toContainText('Contábil Horizonte');
    expect(signups).toHaveLength(1);
    expect(signups[0]!.firm).toBeNull();
    expect(signups[0]!.body).toMatchObject({
      clients_band: 'de_101_a_300',
      company: 'Contábil Horizonte',
      consent: true,
      email: 'marina@horizonte.example',
      uf: 'MG',
    });
  });

  test('refuses an empty form on its fields and sends nothing', async ({ page, baseURL }) => {
    const signups: SentSignup[] = [];

    await mockProductApi(page, signups);
    await page.goto(rootUrl(baseURL, '/#beta'));
    await expect(page.getByTestId('beta-consent-text')).toBeVisible();

    await page.getByTestId('beta-submit').click();

    await expect(page.getByTestId('beta-error-company')).toHaveText('Preencha este campo.');
    await expect(page.getByTestId('beta-error-consent')).toBeVisible();
    expect(signups).toHaveLength(0);
  });
});
