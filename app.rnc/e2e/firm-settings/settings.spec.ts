import type { Page } from '@playwright/test';
import { expect, mockUser, paginated, seedAuthSession, test } from '../fixtures/auth';

/*
 * rnc-4 A40 (R30–R33), API mocked: the demo firm's manager, on its own host, saves a new consent
 * version (preview with the firm's name, history growing) and creates a team login whose password is
 * shown once; the four settings screens read at 390 and 1440, light and dark, with no horizontal scroll.
 */
const SIZES = [
  { height: 844, width: 390 },
  { height: 900, width: 1440 },
];

const SCHEMES = ['light', 'dark'] as const;

const ALVORADA_PUBLIC = {
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  business_hours: [{ from: '08:00', to: '18:00', weekdays: [1, 2, 3, 4, 5] }],
  credentials: null,
  is_platform: false,
  logo_text: 'Alvorada Contabilidade',
  modules: ['portfolio', 'fiscal', 'radar', 'partners', 'contracts', 'briefing'],
  name: 'Alvorada Contabilidade',
  short_name: 'Alvorada',
  slug: 'alvorada',
};

const MANAGER = {
  ...mockUser,
  firm: { modules: ALVORADA_PUBLIC.modules, name: ALVORADA_PUBLIC.name, short_name: 'Alvorada', slug: 'alvorada' },
  id: 30,
  last_name: 'Alvorada',
  name: 'Helena',
  permissions: [
    'home.read',
    'leads.read',
    'metrics.read',
    'firm.settings',
    'users.read',
    'users.create',
    'users.update',
    'users.archive',
    'users.reset_password',
  ],
  platform: { is_operator: false, name: 'Hub da Reforma', view_as: null },
  role: 'Gestão',
  role_slug: 'gestao',
  username: 'helena.alvorada',
};

const SETTINGS = {
  address: 'Rua das Acácias, 120 — Centro, Pouso Alegre/MG',
  business_hours: ALVORADA_PUBLIC.business_hours,
  credentials: null,
  mailbox: 'contato@alvorada.example',
  name: ALVORADA_PUBLIC.name,
  phone: '(35) 3421-0000',
  slug: 'alvorada',
};

interface ConsentVersionMock {
  author: { id: number; name: string } | null;
  created_at: string;
  text: string;
  version: number;
}

const VERSION_ONE: ConsentVersionMock = {
  author: null,
  created_at: '2026-11-13T09:00:00-03:00',
  text: 'Autorizo a {escritorio} a entrar em contato comigo sobre o diagnóstico.',
  version: 1,
};

const ROLES = [
  { id: 1, name: 'Operador', permissionGroups: [], slug: 'operador', users: [] },
  { id: 2, name: 'Gestão', permissionGroups: [], slug: 'gestao', users: [] },
  { id: 3, name: 'Equipe', permissionGroups: [], slug: 'equipe', users: [] },
  { id: 4, name: 'Parceiro', permissionGroups: [], slug: 'parceiro', users: [] },
];

const TEAM = [
  { ...MANAGER, is_specialist: false, status: 'active' },
  {
    email: null,
    id: 31,
    is_specialist: false,
    last_name: 'Fontana',
    name: 'Lívia',
    role: 'Equipe',
    role_slug: 'equipe',
    status: 'active',
    username: 'equipe.alvorada',
  },
];

const SPECIALISTS = [
  {
    area: 'Tributário',
    hours: [{ closes_at: '17:00', opens_at: '09:00', weekday: 1 }],
    id: 5,
    name: 'Bruno Alvorada',
    user_id: 32,
  },
];

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

function alvoradaUrl(baseURL: string | undefined, path: string) {
  const url = new URL(path, baseURL);

  url.hostname = 'alvorada.localhost';

  return url.toString();
}

/**
 * The page's horizontal scroll AND the back office's own scroller: `TheLayout` scrolls its content in
 * `.main-scroll` (`overflow-y-auto`, so x too), and a row wider than the screen scrolls there while the
 * document stays exactly as wide as the window — measuring only the document missed it.
 */
async function horizontalOverflow(page: Page) {
  return page.evaluate(() => {
    const scrollers = [document.documentElement, ...document.querySelectorAll<HTMLElement>('.main-scroll')];

    return Math.max(...scrollers.map((element) => element.scrollWidth - element.clientWidth));
  });
}

async function signInAsManager(page: Page) {
  const versions = [VERSION_ONE];

  await seedAuthSession(page);
  await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: ALVORADA_PUBLIC })));
  await page.route('**/api/auth/me**', (route) => route.fulfill(json({ data: MANAGER })));
  await page.route('**/api/notifications**', (route) => route.fulfill(json({ data: paginated([]) })));
  await page.route(/\/api\/firm\/settings$/, (route) => route.fulfill(json({ data: SETTINGS })));
  await page.route(/\/api\/specialists$/, (route) => route.fulfill(json({ data: SPECIALISTS })));
  await page.route(/\/api\/auth\/roles$/, (route) => route.fulfill(json({ data: ROLES })));
  await page.route(/\/api\/firm\/consent-versions$/, async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fulfill(json({ data: [...versions].reverse() }));
      return;
    }

    const { text } = route.request().postDataJSON() as { text: string };
    const created = {
      author: { id: MANAGER.id, name: 'Helena Alvorada' },
      created_at: '2026-11-14T12:00:00-03:00',
      text,
      version: versions.length + 1,
    };

    versions.push(created);
    await route.fulfill(json({ data: created }, 201));
  });
  await page.route(/\/api\/auth\/users(\?.*)?$/, async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fulfill(json({ data: paginated(TEAM) }));
      return;
    }

    const body = route.request().postDataJSON() as { last_name: string; name: string; username: string };

    await route.fulfill(
      json(
        {
          data: {
            ...body,
            email: null,
            id: 40,
            is_specialist: false,
            role: 'Equipe',
            role_slug: 'equipe',
            status: 'active',
          },
        },
        201
      )
    );
  });
}

test.describe('the firm settings (rnc-4 A40)', () => {
  test('the manager saves a new consent version and sees it head the history', async ({ baseURL, page }) => {
    await signInAsManager(page);

    await page.goto(alvoradaUrl(baseURL, '/management/consent'));

    await expect(page.getByTestId('consent-version-row')).toHaveCount(1);

    await page.getByTestId('consent-text').fill('Autorizo a {escritorio} a me ligar sobre a Reforma.');

    await expect(page.getByTestId('consent-preview')).toContainText(
      'Autorizo a Alvorada Contabilidade a me ligar sobre a Reforma.'
    );

    await page.getByTestId('consent-save').click();

    await expect(page.getByTestId('consent-version-row')).toHaveCount(2);
    await expect(page.getByTestId('consent-version-row').first()).toContainText('Helena Alvorada');
    await expect(page.getByTestId('consent-current-version')).toContainText('2');
  });

  test('the manager creates a team login and sees its password once', async ({ baseURL, page }) => {
    await signInAsManager(page);

    await page.goto(alvoradaUrl(baseURL, '/management/team'));

    await expect(page.getByTestId('team-row')).toHaveCount(2);

    await page.getByTestId('team-new').click();
    await page.getByTestId('member-name').fill('Otávio');
    await page.getByTestId('member-last-name').fill('Reis');
    await page.getByTestId('member-username').fill('otavio.alvorada');

    await expect(page.getByTestId('member-role-option')).toHaveCount(2);

    await page.getByLabel('Equipe').check();
    await page.getByTestId('member-save').click();

    await expect(page.getByTestId('password-value')).toHaveText(/^[A-Za-z0-9]{16}$/);

    await page.getByTestId('member-done').click();

    await expect(page.getByTestId('team-row')).toHaveCount(3);
  });

  test('the four settings screens read at 390 and 1440, light and dark, with no horizontal scroll', async ({
    baseURL,
    page,
  }) => {
    const screens = [
      { path: '/management/firm', ready: 'contact-form' },
      { path: '/management/team', ready: 'team-row' },
      { path: '/management/specialists', ready: 'specialist-row' },
      { path: '/management/consent', ready: 'consent-version-row' },
    ];

    await signInAsManager(page);

    for (const size of SIZES) {
      for (const scheme of SCHEMES) {
        await page.setViewportSize(size);
        await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });

        for (const screen of screens) {
          await page.goto(alvoradaUrl(baseURL, screen.path));

          await expect(page.getByTestId(screen.ready).first()).toBeVisible();
          expect(await horizontalOverflow(page), `${screen.path} ${size.width} ${scheme}`).toBeLessThanOrEqual(0);
        }
      }
    }
  });
});
