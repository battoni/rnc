import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * A12 (rnc-4 R10, R11): the firm's palette is painted at runtime from its `public/firm` row on BOTH
 * layouts — the landing, the diagnostic, the back-office home and the pipeline — and what the app paints
 * in it reads at 4.5:1, in light and dark, for two firms: Franco's navy and Alvorada's green. The theme
 * file is only the neutral ground (`hub`), so a palette seen here can only have come from the row.
 */
const FIRMS = {
  franco: {
    brand_accent: '#14c3ff',
    brand_primary: '#00375a',
    logo_text: 'Franco Contabilidade',
    name: 'Franco Contabilidade',
  },
  alvorada: {
    brand_accent: '#f4b63f',
    brand_primary: '#0f5c4d',
    logo_text: 'Alvorada Contabilidade',
    name: 'Alvorada Contabilidade',
  },
} as const;

const TEAM_MEMBER = {
  ...mockUser,
  name: 'Patrícia',
  role: 'Equipe',
  role_slug: 'equipe',
  username: 'patricia',
  permissions: ['leads.read', 'leads.update', 'agenda.read'],
};

const STAGES = ['visitante', 'lead_parcial', 'lead', 'reuniao_marcada', 'proposta', 'cliente', 'perdido'];

/** Each page, how to open it signed in or not, and what on it is painted in the brand. */
const PAGES = [
  {
    name: 'landing',
    path: '/',
    ready: '[data-testid="header-cta"]',
    painted: ['[data-testid="header-cta"]', 'header .text-brand-word > span:not([role="img"])'],
  },
  { name: 'diagnostic', path: '/diagnostico', ready: '[data-testid="path-quiz"]', painted: [] },
  {
    name: 'back-office home',
    path: '/painel',
    ready: '[data-testid="navbar-firm-name"]',
    painted: ['.the-sidenav [role="img"]'],
    user: mockUser,
  },
  {
    name: 'pipeline',
    path: '/equipe/pipeline',
    ready: '[data-testid="lead-card"]',
    painted: ['.the-sidenav [role="img"]'],
    user: TEAM_MEMBER,
  },
] as const;

/** What any screen paints in the brand, probed on the page itself: the fill's label, the brand word. */
const PROBES = [
  'bg-primary-500 text-primary-contrast',
  'bg-canvas text-brand-word',
  'bg-panel text-brand-word',
  'bg-primary-950 text-primary-300',
];

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
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

function rootColour(page: Page, property: string) {
  return page.evaluate((name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim(), property);
}

/** Each element's text colour over the first painted ground at or above it, read back as `rgb()`. */
async function renderedPairs(page: Page, selector: string) {
  return page.evaluate((query) => {
    const context = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!;
    const body = window.getComputedStyle(document.body).backgroundColor;

    function toRgb(colour: string, under = body): string {
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

      return toRgb(body, '#ffffff');
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

async function addProbes(page: Page) {
  await page.evaluate((classes) => {
    const holder = document.createElement('div');

    holder.dataset.testid = 'brand-probes';
    classes.forEach((className) => {
      const probe = document.createElement('span');

      probe.className = `brand-probe ${className}`;
      probe.textContent = 'Aa';
      holder.appendChild(probe);
    });
    document.body.appendChild(holder);
  }, PROBES);
}

async function mockApp(page: Page, slug: keyof typeof FIRMS, user?: typeof mockUser) {
  if (user) {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) => route.fulfill(json({ data: user })));
  }

  await page.route(/\/leads(\?.*)?$/, (route) =>
    route.fulfill(
      json({
        data: {
          columns: STAGES.map((stage, index) => ({
            count: 1,
            items: [
              {
                channel: 'formulario',
                company: `Empresa ${index + 1}`,
                contact_name: 'Ana Almeida',
                created_at: '2026-11-10T09:12:00-03:00',
                has_contact: true,
                id: index + 1,
                last_step: 'resultado',
                lost_reason: null,
                meeting: null,
                opportunities: 3,
                risks: 2,
                sector: 'comercio',
                source: 'organico',
                stage,
                updated_at: '2026-11-12T10:00:00-03:00',
              },
            ],
            stage,
          })),
        },
      })
    )
  );
  await page.route('**/public/sectors', (route) => route.fulfill(json({ data: [] })));
  await page.route('**/public/events', (route) =>
    route.fulfill({ status: 201, contentType: 'application/json', body: '[]' })
  );
  await page.route('**/public/firm', (route) =>
    route.fulfill(
      json({
        data: {
          ...FIRMS[slug],
          address: null,
          brand: null,
          business_hours: [],
          is_platform: false,
          logo_url: null,
          mailbox: null,
          phone: null,
          phone_href: null,
          platform: { name: 'Hub da Reforma' },
          proof: null,
          short_name: null,
          slug,
          tagline: null,
        },
      })
    )
  );
}

test.use({ viewport: { width: 1440, height: 900 } });

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
});

for (const slug of Object.keys(FIRMS) as (keyof typeof FIRMS)[]) {
  for (const scheme of ['light', 'dark'] as const) {
    for (const screen of PAGES) {
      test(`${slug}, ${screen.name}: the firm's palette, painted from its row, reads at 4.5:1 (${scheme})`, async ({
        page,
      }) => {
        await mockApp(page, slug, 'user' in screen ? screen.user : undefined);
        await page.emulateMedia({ colorScheme: scheme });
        await page.goto(screen.path);
        await expect(page.locator(screen.ready).first()).toBeVisible();
        await expect(page.locator('html')).toHaveAttribute('data-brand', slug);
        await expect(page.locator('html')).toHaveAttribute('data-theme', scheme);

        expect(await rootColour(page, '--color-primary-500')).toBe(FIRMS[slug].brand_primary);

        await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; }' });
        await addProbes(page);

        const selectors = ['.brand-probe', ...screen.painted];
        const pairs = (await Promise.all(selectors.map((selector) => renderedPairs(page, selector)))).flat();

        expect(pairs.length).toBeGreaterThanOrEqual(PROBES.length + screen.painted.length);

        for (const pair of pairs) {
          const ratio = contrastRatio(parseRgb(pair.colour), parseRgb(pair.background));

          expect(
            ratio,
            `${pair.label} (${scheme}): ${ratio.toFixed(2)}:1 ${pair.colour} on ${pair.background}`
          ).toBeGreaterThanOrEqual(4.5);
        }
      });
    }
  }
}

test("the back office writes the firm's logo text and the product's name, and titles the page with it", async ({
  page,
}) => {
  await mockApp(page, 'alvorada', mockUser);
  await page.goto('/painel');

  await expect(page.getByTestId('navbar-firm-name')).toHaveText('Alvorada Contabilidade');
  await expect(page.getByTestId('navbar-platform-name')).toHaveText('Hub da Reforma');
  await expect(page).toHaveTitle('Hub da Reforma');
});
