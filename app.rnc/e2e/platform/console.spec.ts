import type { BrowserContext, Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, test } from '../fixtures/auth';

/*
 * rnc-4 A30 (R24, R25), API mocked: the platform operator — firm-less, at the root host — lands on the
 * console, creates "Ipê Contábil" (`ipe`) and opens its subdomain link; the new firm's site and the
 * console itself read at 390 and 1440, light and dark, with no horizontal scroll.
 */
const SIZES = [
  { height: 844, width: 390 },
  { height: 900, width: 1440 },
];

const SCHEMES = ['light', 'dark'] as const;

const OPERATOR = {
  ...mockUser,
  firm: null,
  id: 1,
  last_name: 'Battoni',
  name: 'Guilherme',
  permissions: ['home.read', 'users.read', 'permissions.read', 'platform.firms', 'platform.consent'],
  platform: { is_operator: true, name: 'Hub da Reforma', view_as: null },
  role: 'Operador',
  role_slug: 'operador',
  username: 'battoni',
};

const HUB_PUBLIC = {
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  is_platform: true,
  logo_text: 'Hub da Reforma',
  modules: [],
  name: 'Hub da Reforma',
  slug: 'hub',
};

const IPE_PUBLIC = {
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  is_platform: false,
  logo_text: 'Ipê Contábil',
  modules: ['briefing'],
  name: 'Ipê Contábil',
  proof: null,
  short_name: 'Ipê Contábil',
  slug: 'ipe',
  tagline: null,
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

function hostUrl(baseURL: string | undefined, host: string, path: string) {
  const url = new URL(path, baseURL);

  url.hostname = host;

  return url.toString();
}

function platformFirm(id: number, slug: string, name: string, siteUrl: string, extra: object = {}) {
  return {
    brand_accent: '#f2a900',
    brand_primary: '#1f4e79',
    created_at: '2026-11-14T11:00:00-03:00',
    id,
    is_platform: false,
    lead_count: 0,
    logo_text: name,
    modules: ['briefing'],
    name,
    proof: null,
    short_name: name,
    site_url: siteUrl,
    slug,
    tagline: null,
    ...extra,
  };
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** The firm's own site, as a visitor opening the console's link sees it: every call mocked here. */
async function mockIpeSite(context: BrowserContext) {
  await context.route(/\/api\/public\/firm/, (route) => route.fulfill(json({ data: IPE_PUBLIC })));
  await context.route(/\/api\/public\/sectors$/, (route) => route.fulfill(json({ data: [] })));
  await context.route(/\/api\/public\/events/, (route) =>
    route.fulfill({ status: 201, contentType: 'application/json', body: '[]' })
  );
}

async function signInAsOperator(page: Page, baseURL: string | undefined) {
  const hub = platformFirm(2, 'hub', 'Hub da Reforma', hostUrl(baseURL, 'localhost', '/'), {
    is_platform: true,
    modules: [],
  });
  const alvorada = platformFirm(3, 'alvorada', 'Alvorada Contabilidade', hostUrl(baseURL, 'alvorada.localhost', '/'), {
    lead_count: 40,
    modules: ['portfolio', 'radar', 'briefing'],
  });
  const ipe = platformFirm(9, 'ipe', 'Ipê Contábil', hostUrl(baseURL, 'ipe.localhost', '/'));

  await seedAuthSession(page);
  await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: HUB_PUBLIC })));
  await page.route('**/api/auth/me**', (route) => route.fulfill(json({ data: OPERATOR })));
  await page.route(/\/api\/platform\/firms$/, (route) =>
    route.request().method() === 'POST'
      ? route.fulfill(
          json(
            {
              data: {
                firm: ipe,
                manager: { email: null, id: 50, username: 'gestao.ipe' },
                password: 'Q7w8E9r0T1y2U3i4',
              },
            },
            201
          )
        )
      : route.fulfill(json({ data: [hub, alvorada] }))
  );
}

test.describe('the platform console (rnc-4 A30)', () => {
  test('the firm-less operator lands on the console, creates a firm and opens its subdomain', async ({
    baseURL,
    context,
    page,
  }) => {
    await signInAsOperator(page, baseURL);

    await page.goto(hostUrl(baseURL, 'localhost', '/painel'));

    await expect(page).toHaveURL(/\/platform\/firms$/);
    await expect(page.getByTestId('firm-row')).toHaveCount(2);

    await page.getByTestId('firm-new').click();
    await page.getByTestId('firm-name').fill('Ipê Contábil');
    await page.getByTestId('firm-slug').fill('ipe');
    await expect(page.getByTestId('firm-slug-preview')).toContainText('ipe.localhost');
    await page.getByTestId('firm-manager').fill('gestao.ipe');
    await page.getByTestId('firm-save').click();

    await expect(page.getByTestId('password-value')).toContainText('Q7w8E9r0T1y2U3i4');
    await page.getByTestId('firm-done').click();

    const row = page.locator('[data-testid="firm-row"][data-firm="ipe"]');

    await expect(row).toBeVisible();

    await mockIpeSite(context);

    const [site] = await Promise.all([context.waitForEvent('page'), row.getByTestId('firm-site-link').click()]);

    await site.waitForLoadState();

    expect(new URL(site.url()).hostname).toBe('ipe.localhost');

    for (const size of SIZES) {
      for (const scheme of SCHEMES) {
        await site.setViewportSize(size);
        await site.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
        await site.reload();

        await expect(site.getByRole('banner')).toContainText('Ipê');
        expect(await horizontalOverflow(site), `${size.width} ${scheme}`).toBeLessThanOrEqual(0);
      }
    }
  });

  test('the console reads at 390 and 1440, light and dark, with no horizontal scroll', async ({ baseURL, page }) => {
    await signInAsOperator(page, baseURL);

    for (const size of SIZES) {
      for (const scheme of SCHEMES) {
        await page.setViewportSize(size);
        await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
        await page.goto(hostUrl(baseURL, 'localhost', '/platform/firms'));

        await expect(page.getByTestId('firm-row')).toHaveCount(2);
        expect(await horizontalOverflow(page), `${size.width} ${scheme}`).toBeLessThanOrEqual(0);
      }
    }
  });
});
