import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * A16 (rnc-4 R15): on another firm's host the product says that firm's name and never Franco's or its
 * manager's — the landing, the diagnostic result, the impact report and the back-office home, with
 * Alvorada's `public/firm` mocked. The words that need the firm come from `{firmShort}` / `{firmName}` in
 * the locale files, filled at runtime from that answer; nothing the build carries names a tenant.
 */
const BANNED = /Franco|Reinaldo/;

const ALVORADA = {
  address: null,
  brand: null,
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  business_hours: [],
  is_platform: false,
  logo_text: 'Alvorada Contabilidade',
  logo_url: null,
  mailbox: 'contato@alvorada.example',
  name: 'Alvorada Contabilidade',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  proof: null,
  short_name: 'Alvorada',
  slug: 'alvorada',
  tagline: null,
};

const TOKEN = 'k'.repeat(48);
const REPORT_TOKEN = 'r'.repeat(48);
const CONSENT =
  'Autorizo a Alvorada Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar.';
const PROFILE = { b2b: null, band: null, exporta: false, incentivos: false, regime: null, sector: null };
const LEAD = {
  cnpj: null,
  company: null,
  consent: { text: CONSENT, version: 1 },
  has_contact: false,
  has_email: false,
  last_step: 'empresa',
  path: 'quiz',
  profile: PROFILE,
  stage: 'visitante',
};

const RESULT = {
  company: 'Clínica Horizonte',
  items: [
    {
      kind: 'oportunidade',
      range_label: 'R$ 18–54 mil/ano',
      range_max: 54000,
      range_min: 18000,
      title: 'Opção pelo regime regular do IBS/CBS',
    },
    {
      kind: 'risco',
      range_label: 'R$ 14–36 mil/ano',
      range_max: 36000,
      range_min: 14400,
      title: 'Fluxo de caixa exposto ao split payment',
    },
  ],
  opportunities: 1,
  risks: 1,
};

const SIMULATION = {
  estimate: true,
  split_payment: { label: 'R$ 16–28 mil/mês', max: 27825, min: 15900 },
  taxes: [{ label: 'CBS', leaving: false, tax: 'cbs' }],
  years: [2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033].map((year) => ({
    parts: [{ label: 'CBS', rate: 1, tax: 'cbs' }],
    total: 1,
    year,
  })),
};

const MEETING = {
  area: 'Contábil · Simples Nacional',
  ends_at: '2026-11-24T15:00:00-03:00',
  specialist: 'Camila Duarte',
  starts_at: '2026-11-24T14:30:00-03:00',
};

const REPORT = {
  company: 'Clínica Horizonte',
  meeting: MEETING,
  profile: { b2b: 80, band: 'b2', regime: 'simples', sector: 'servicos' },
  result: RESULT,
  simulation: SIMULATION,
};

const MANAGER = {
  ...mockUser,
  name: 'Helena',
  permissions: ['leads.read', 'metrics.read'],
  role: 'Gestão',
  role_slug: 'gestao',
  username: 'helena.alvorada',
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function mockAlvorada(page: Page) {
  await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: ALVORADA })));
  await page.route('**/public/sectors', (route) => route.fulfill(json({ data: [] })));
  await page.route('**/public/events', (route) => route.fulfill(json([], 201)));
  await page.route('**/public/diagnostics', (route) => route.fulfill(json({ data: { ...LEAD, token: TOKEN } }, 201)));
  await page.route(`**/public/diagnostics/${TOKEN}`, (route) => route.fulfill(json({ data: LEAD })));
  await page.route(`**/public/diagnostics/${TOKEN}/result`, (route) => route.fulfill(json({ data: RESULT })));
  await page.route(`**/public/diagnostics/${TOKEN}/simulation`, (route) => route.fulfill(json({ data: SIMULATION })));
  await page.route(`**/public/reports/${REPORT_TOKEN}`, (route) => route.fulfill(json({ data: REPORT })));
}

async function expectNoTenantName(page: Page) {
  await expect(page.locator('body')).not.toContainText(BANNED);
  await expect(page).not.toHaveTitle(BANNED);
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
  await mockAlvorada(page);
});

test("the landing names Alvorada and never Franco's", async ({ page }) => {
  await page.goto('/');
  await expect(page.getByTestId('header-cta')).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-brand', 'alvorada');

  await expect(page.locator('body')).toContainText('Alvorada');
  await expectNoTenantName(page);
});

test("the diagnostic's result names no other firm", async ({ page }) => {
  await page.goto('/diagnostico');
  await page.getByTestId('path-quiz').click();
  await page.getByTestId('sector-servicos').click();
  await page.getByTestId('regime-simples').click();
  await page.getByTestId('step-next').click();

  await expect(page.getByTestId('contact-form')).toContainText('ficam com a Alvorada');
  await expectNoTenantName(page);

  await page.getByTestId('contact-company').fill('Clínica Horizonte');
  await page.getByTestId('contact-phone').fill('(11) 98888-7777');
  await page.getByTestId('contact-save').click();
  await page.getByTestId('b2b-80').click();
  await page.getByTestId('step-next').click();
  await page.getByTestId('band-b2').click();
  await page.getByTestId('step-next').click();

  await expect(page.getByTestId('result')).toBeVisible();
  await expectNoTenantName(page);
});

test('the impact report names no other firm', async ({ page }) => {
  await page.goto(`/relatorio/${REPORT_TOKEN}`);
  await expect(page.getByTestId('impact-report')).toBeVisible();
  await expect(page.getByTestId('report-meeting')).toContainText('Camila Duarte');

  await expectNoTenantName(page);
});

test('the back-office home reads "Gestão" and names no person or other firm', async ({ page }) => {
  await seedAuthSession(page);
  await page.route('**/api/public/firm**', (route) => route.fulfill(json({ data: ALVORADA })));
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: MANAGER })));

  await page.goto('/painel');
  await expect(page.getByTestId('navbar-firm-name').first()).toBeVisible();
  await expect(page.locator('.the-sidenav')).toContainText('Gestão');

  await expectNoTenantName(page);
});
