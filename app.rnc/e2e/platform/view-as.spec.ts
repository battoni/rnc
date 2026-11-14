import type { Page } from '@playwright/test';
import { expect, mockUser, paginated, seedAuthSession, test } from '../fixtures/auth';

/*
 * rnc-4 A43 (R26, R27), API mocked: the platform operator uploads a firm's logo from the console (an
 * SVG refused before anything is sent, a PNG previewed and sent), the logo shows on the firm's own site
 * and in the back office, and "Acessar como suporte" enters the firm — a banner naming it on every
 * screen while it lasts, the firm's brand painted — until "Sair" ends it and returns to the console.
 */
const SIZES = [
  { height: 844, width: 390 },
  { height: 900, width: 1440 },
];

// A real 1×1 PNG, so the preview and the shown logo decode.
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64'
);

const LOGO_URL = 'http://localhost:8000/storage/firms/3/logo-abc123.png';
const LOGO_PATH = '/storage/firms/3/logo-abc123.png';

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
  logo_url: null,
  modules: [],
  name: 'Hub da Reforma',
  platform: { name: 'Hub da Reforma' },
  slug: 'hub',
};

const ALVORADA_PUBLIC = {
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  is_platform: false,
  logo_text: 'Alvorada Contabilidade',
  logo_url: LOGO_URL,
  modules: ['briefing'],
  name: 'Alvorada Contabilidade',
  platform: { name: 'Hub da Reforma' },
  proof: null,
  short_name: 'Alvorada',
  slug: 'alvorada',
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

function platformFirm(id: number, slug: string, name: string, extra: object = {}) {
  return {
    brand_accent: '#f2a900',
    brand_primary: '#1f4e79',
    created_at: '2026-11-14T11:00:00-03:00',
    id,
    is_platform: false,
    lead_count: 0,
    logo_text: name,
    logo_url: null,
    modules: ['briefing'],
    name,
    proof: null,
    short_name: name,
    site_url: `http://${slug}.localhost:5173`,
    slug,
    tagline: null,
    ...extra,
  };
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => {
    const scroller = document.querySelector('.main-scroll');
    const document_ = document.documentElement.scrollWidth - document.documentElement.clientWidth;

    return Math.max(document_, scroller ? scroller.scrollWidth - scroller.clientWidth : 0);
  });
}

/** The operator at the root, every call mocked; `viewing` is the server session's view-as. */
async function signInAsOperator(page: Page) {
  const state = { logoPosts: 0, viewing: false };
  const hub = platformFirm(2, 'hub', 'Hub da Reforma', { is_platform: true, modules: [] });
  const alvorada = platformFirm(3, 'alvorada', 'Alvorada Contabilidade');

  await seedAuthSession(page);
  await page.route('**/storage/**', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: PNG }));
  await page.route('**/api/public/firm**', (route) =>
    route.fulfill(json({ data: state.viewing ? ALVORADA_PUBLIC : HUB_PUBLIC }))
  );
  await page.route('**/api/auth/me**', (route) =>
    route.fulfill(
      json({
        data: {
          ...OPERATOR,
          platform: {
            ...OPERATOR.platform,
            view_as: state.viewing ? { name: 'Alvorada Contabilidade', slug: 'alvorada' } : null,
          },
        },
      })
    )
  );
  await page.route(/\/api\/platform\/firms$/, (route) => route.fulfill(json({ data: [hub, alvorada] })));
  await page.route(/\/api\/platform\/firms\/3\/logo$/, (route) => {
    state.logoPosts += 1;

    return route.fulfill(json({ data: { ...alvorada, logo_url: LOGO_URL } }));
  });
  await page.route(/\/api\/platform\/firms\/3\/view-as$/, (route) => {
    state.viewing = true;

    return route.fulfill(
      json(
        {
          data: {
            ended_at: null,
            end_reason: null,
            firm: { id: 3, name: 'Alvorada Contabilidade', slug: 'alvorada' },
            id: 7,
            ip_address: '127.0.0.1',
            operator: { id: 1, name: 'Guilherme' },
            started_at: '2026-11-14T13:00:00-03:00',
          },
        },
        201
      )
    );
  });
  await page.route(/\/api\/platform\/view-as$/, (route) => {
    state.viewing = false;

    return route.fulfill(json({ data: null }));
  });
  await page.route(/\/api\/platform\/view-as-log/, (route) =>
    route.fulfill(
      json({
        data: paginated([
          {
            ended_at: '2026-11-14T13:10:00-03:00',
            end_reason: 'sair',
            firm: { id: 3, name: 'Alvorada Contabilidade', slug: 'alvorada' },
            id: 7,
            ip_address: '127.0.0.1',
            operator: { id: 1, name: 'Guilherme' },
            started_at: '2026-11-14T13:00:00-03:00',
          },
        ]),
      })
    )
  );

  return state;
}

test.describe('logo upload and view-as (rnc-4 A43)', () => {
  test('the console refuses an SVG before sending, previews a PNG and sends it', async ({ baseURL, page }) => {
    const state = await signInAsOperator(page);

    await page.goto(hostUrl(baseURL, 'localhost', '/platform/firms'));
    await page.locator('[data-testid="firm-row"][data-firm="alvorada"]').getByTestId('firm-edit').click();

    const input = page.getByTestId('firm-logo-input');

    await input.setInputFiles({ buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>'), mimeType: 'image/svg+xml', name: 'logo.svg' });
    await expect(page.getByTestId('firm-logo-error')).toBeVisible();
    await expect(page.getByTestId('firm-logo-upload')).toHaveCount(0);

    await input.setInputFiles({ buffer: PNG, mimeType: 'image/png', name: 'logo.png' });
    await expect(page.getByTestId('firm-logo-error')).toHaveCount(0);
    await expect(page.getByTestId('firm-logo-preview')).toBeVisible();

    await page.getByTestId('firm-logo-upload').click();

    await expect(page.getByTestId('firm-logo-current')).toHaveAttribute('src', LOGO_PATH);
    expect(state.logoPosts).toBe(1);
  });

  test("the firm's logo shows on its own site, through the same origin", async ({ baseURL, page }) => {
    await page.route('**/storage/**', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: PNG }));
    await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: ALVORADA_PUBLIC })));
    await page.route(/\/api\/public\/sectors$/, (route) => route.fulfill(json({ data: [] })));
    await page.route(/\/api\/public\/events/, (route) =>
      route.fulfill({ status: 201, contentType: 'application/json', body: '[]' })
    );

    await page.goto(hostUrl(baseURL, 'alvorada.localhost', '/'));

    // The header holds the min mark (phone) and the full one (wider): the one on screen.
    const logo = page.locator('header [data-testid="logo-image"]:visible').first();

    await expect(logo).toBeVisible();
    await expect(logo).toHaveAttribute('src', LOGO_PATH);
  });

  test('"Acessar como suporte" enters the firm with a banner on every screen until "Sair"', async ({
    baseURL,
    page,
  }) => {
    const state = await signInAsOperator(page);

    await page.goto(hostUrl(baseURL, 'localhost', '/platform/firms'));

    // The platform firm is never entered: no action on its row.
    await expect(page.locator('[data-testid="firm-row"][data-firm="hub"]').getByTestId('firm-view-as')).toHaveCount(0);
    await expect(page.getByTestId('view-as-banner')).toHaveCount(0);

    await page.locator('[data-testid="firm-row"][data-firm="alvorada"]').getByTestId('firm-view-as').click();

    const banner = page.getByTestId('view-as-banner');

    await expect(banner).toContainText('Alvorada Contabilidade');
    await expect(page).not.toHaveURL(/\/platform\/firms$/);
    expect(state.viewing).toBe(true);

    // The back office paints the viewed firm, logo included.
    await expect(page.getByTestId('navbar-firm-name').first()).toContainText('Alvorada Contabilidade');

    // Another screen, and a reload: the banner stays.
    await page.goto(hostUrl(baseURL, 'localhost', '/platform/view-as-log'));
    await expect(page.getByTestId('view-as-banner')).toContainText('Alvorada Contabilidade');
    await expect(page.getByTestId('view-as-log-row')).toHaveCount(1);

    for (const size of SIZES) {
      await page.setViewportSize(size);
      await expect(page.getByTestId('view-as-banner')).toBeInViewport();
      await expect(page.getByTestId('view-as-leave')).toBeInViewport();
      expect(await horizontalOverflow(page), `${size.width}`).toBeLessThanOrEqual(0);
    }

    await page.getByTestId('view-as-leave').click();

    await expect(page).toHaveURL(/\/platform\/firms$/);
    await expect(page.getByTestId('view-as-banner')).toHaveCount(0);
    await expect(page.getByTestId('navbar-firm-name').first()).toContainText('Hub da Reforma');
    expect(state.viewing).toBe(false);
  });
});
