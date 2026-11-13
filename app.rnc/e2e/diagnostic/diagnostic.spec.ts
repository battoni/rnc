import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';
import { emulateDarkPrint, readPrintedSheet } from '../fixtures/print';

/*
 * The diagnostic (rnc-1 F3–F4, R9–R17) with the API mocked: the quiz from step 1 to the locked
 * result, the slot picker (asking the e-mail of a phone-only lead, AM5), the confirmation and the
 * "Relatório de Impacto"; the CNPJ path's refusal of a wrong check digit; and A15's width check on
 * the result, the picker and the report at 390 px and 1440 px. Measured in Portuguese.
 */
const TOKEN = 'k'.repeat(48);
const CONSENT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';
const PROFILE = { b2b: null, band: null, exporta: false, incentivos: false, regime: null, sector: null };
const LEAD = { cnpj: null, company: null, consent: { text: CONSENT, version: 1 }, has_contact: false, has_email: false, last_step: 'empresa', path: 'quiz', profile: PROFILE, stage: 'visitante' };

const RESULT = {
  company: 'Clínica Horizonte',
  opportunities: 3,
  risks: 2,
  items: [
    { kind: 'oportunidade', title: 'Opção pelo regime regular do IBS/CBS', range_min: 18000, range_max: 54000, range_label: 'R$ 18–54 mil/ano' },
    { kind: 'risco', title: 'Clientes PJ podem preferir fornecedores que geram crédito', range_min: 36000, range_max: 90000, range_label: 'R$ 36–90 mil/ano' },
    { kind: 'oportunidade', title: 'Redução de 30% para profissão regulamentada', range_min: 18000, range_max: 45000, range_label: 'R$ 18–45 mil/ano' },
    { kind: 'oportunidade', title: 'Revisar contratos e preços antes de 2027', range_min: 9000, range_max: 27000, range_label: 'R$ 9–27 mil/ano' },
    { kind: 'risco', title: 'Fluxo de caixa exposto ao split payment', range_min: 14400, range_max: 36000, range_label: 'R$ 14–36 mil/ano' },
  ],
};

const YEARS = [2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033];
const LEGACY = [1, 1, 1, 0.9, 0.8, 0.7, 0.6, 0];

const SIMULATION = {
  estimate: true,
  split_payment: { label: 'R$ 16–28 mil/mês', max: 27825, min: 15900 },
  taxes: [
    { label: 'PIS/Cofins', leaving: true, tax: 'pis-cofins' },
    { label: 'ICMS', leaving: true, tax: 'icms' },
    { label: 'ISS', leaving: true, tax: 'iss' },
    { label: 'CBS', leaving: false, tax: 'cbs' },
    { label: 'IBS', leaving: false, tax: 'ibs' },
  ],
  years: YEARS.map((year, index) => {
    const parts = [
      ...(year === 2026 ? [{ label: 'PIS/Cofins', rate: 3.7, tax: 'pis-cofins' }] : []),
      ...(LEGACY[index]! > 0 ? [{ label: 'ISS', rate: 5 * LEGACY[index]!, tax: 'iss' }] : []),
      { label: 'CBS', rate: year === 2026 ? 0.9 : 11.3, tax: 'cbs' },
      { label: 'IBS', rate: year <= 2028 ? 0.1 : 15.2 * (1 - LEGACY[index]!), tax: 'ibs' },
    ];

    return { parts, total: parts.reduce((sum, part) => sum + part.rate, 0), year };
  }),
};

const REPORT_TOKEN = 'r'.repeat(48);
const MEETING = { area: 'Contábil · Simples Nacional', ends_at: '2026-11-24T15:00:00-03:00', specialist: 'Juliana Reis', starts_at: '2026-11-24T14:30:00-03:00' };

const DAYS = ['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04'];
const HALF_HOURS = Array.from({ length: 20 }, (_, index) => `${String(8 + Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '30' : '00'}`);

function agendaOf(id: number, name: string, area: string, suggested: boolean) {
  return { area, days: DAYS.map((date, index) => ({ date, slots: HALF_HOURS.map((time) => `${date}T${time}:00-03:00`), weekday: (index % 5) + 1 })), id, name, suggested };
}

const AVAILABILITY = {
  slot_minutes: 30,
  specialists: [
    agendaOf(1, 'Mariana Toledo', 'Tributário · Reforma', false),
    agendaOf(2, 'Carlos Prado', 'Tributário · Indústria', false),
    agendaOf(3, 'Juliana Reis', 'Contábil · Simples Nacional', true),
    agendaOf(4, 'Rafael Antunes', 'Planejamento tributário', false),
  ],
  suggested_specialist_id: 3,
  timezone: 'America/Sao_Paulo',
};

const REPORT = { company: 'Clínica Horizonte', meeting: MEETING, profile: { b2b: 80, band: 'b2', regime: 'simples', sector: 'servicos' } };

async function mockDiagnosticApi(page: Page) {
  const json = (data: unknown, status = 200) => ({ status, contentType: 'application/json', body: JSON.stringify(data) });

  await page.route('**/public/firm', (route) =>
    route.fulfill(json({ data: { name: 'Franco Contabilidade', phone: '11 3732-5500', phone_href: 'tel:+551137325500', mailbox: 'saf@franco-rnc.com.br', business_hours: [] } }))
  );
  await page.route('**/public/events', (route) => route.fulfill({ status: 201, contentType: 'application/json', body: '[]' }));
  await page.route('**/public/diagnostics', (route) => route.fulfill(json({ data: { ...LEAD, token: TOKEN } }, 201)));
  await page.route(`**/public/diagnostics/${TOKEN}`, (route) => route.fulfill(json({ data: LEAD })));
  await page.route(`**/public/diagnostics/${TOKEN}/result`, (route) => route.fulfill(json({ data: RESULT })));
  await page.route(`**/public/diagnostics/${TOKEN}/simulation`, (route) => route.fulfill(json({ data: SIMULATION })));
  await page.route(`**/public/diagnostics/${TOKEN}/availability`, (route) => route.fulfill(json({ data: AVAILABILITY })));
  await page.route(`**/public/diagnostics/${TOKEN}/booking`, (route) =>
    route.fulfill(json({ data: { meeting: MEETING, report_token: REPORT_TOKEN } }, 201))
  );
  await page.route(`**/public/reports/${REPORT_TOKEN}`, (route) =>
    route.fulfill(json({ data: { ...REPORT, result: RESULT, simulation: SIMULATION } }))
  );
  await page.route(`**/public/diagnostics/${TOKEN}/cnpj`, (route) =>
    route.fulfill(json({ errors: { cnpj: ['diagnostic.cnpj.invalid'] }, message: 'invalid' }, 422))
  );
}

async function walkQuizToResult(page: Page) {
  await page.goto('/diagnostico');
  await page.getByTestId('path-quiz').click();
  await page.getByTestId('sector-servicos').click();
  await page.getByTestId('regime-simples').click();
  await page.getByTestId('step-next').click();

  await expect(page.getByTestId('contact-form')).toContainText(CONSENT);
  await page.getByTestId('contact-company').fill('Clínica Horizonte');
  await page.getByTestId('contact-phone').fill('(11) 98888-7777');
  await page.getByTestId('contact-save').click();

  await page.getByTestId('b2b-80').click();
  await page.getByTestId('step-next').click();
  await page.getByTestId('band-b2').click();
  await page.getByTestId('step-next').click();

  await expect(page.getByTestId('result')).toBeVisible();
}

async function bookTheSuggestedSlot(page: Page) {
  await page.getByTestId('schedule-cta').click();
  await expect(page.getByTestId('specialist')).toHaveCount(4);
  await page.locator('[data-testid="slot-day"][data-day="2026-11-24"]').click();
  await page.locator('[data-testid="slot"][data-slot="2026-11-24T14:30:00-03:00"]').click();
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
  await mockDiagnosticApi(page);
});

test('walks the quiz from step 1 to the locked result and the specialist CTA', async ({ page }) => {
  await walkQuizToResult(page);

  await expect(page.getByTestId('result-summary')).toHaveText('Encontramos 3 oportunidades e 2 riscos');
  await expect(page.getByTestId('result-item')).toHaveCount(5);
  await expect(page.getByTestId('locked')).toHaveCount(5);
  await expect(page.getByTestId('sim-year')).toHaveCount(8);
  await expect(page.getByTestId('split-payment')).toContainText('R$ 16–28 mil/mês');
  await expect(page).toHaveTitle('Diagnóstico gratuito da Reforma Tributária · Franco Contabilidade');

  await bookTheSuggestedSlot(page);

  await expect(page.locator('[data-testid="specialist"][aria-checked="true"]')).toContainText('Indicado para o seu perfil');

  // The contact was a phone only, so the picker asks for the e-mail before confirming (AM5).
  await page.getByTestId('slot-confirm').click();
  await expect(page.getByTestId('booking-error')).toContainText('e-mail');
  await page.getByTestId('booking-email').fill('ana@horizonte.com.br');
  // A person takes longer than the ClickGuard's 350 ms to type an address; the spec waits it out.
  await expect(page.getByTestId('slot-confirm')).not.toHaveAttribute('data-click-guard', 'locked');
  await page.getByTestId('slot-confirm').click();

  await expect(page.getByTestId('booking-confirmed')).toContainText('Juliana Reis');
  await expect(page.getByTestId('booking-when')).toContainText('24/11 às 14:30');

  await page.getByTestId('open-report').click();

  await expect(page).toHaveURL(new RegExp(`/relatorio/${REPORT_TOKEN}$`));
  await expect(page.getByTestId('report-company')).toHaveText('Clínica Horizonte');
  await expect(page.getByTestId('report-meeting')).toContainText('Juliana Reis');
  await expect(page.getByTestId('sim-year')).toHaveCount(8);
});

// A visitor saves the report as PDF from whatever scheme the screen is in; paper is white (R17).
test('prints the report as a light document while the screen is dark', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto(`/relatorio/${REPORT_TOKEN}`);
  await expect(page.getByTestId('impact-report')).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

  await emulateDarkPrint(page);

  const sheet = await readPrintedSheet(page, '[data-testid="impact-report"]');

  expect(sheet.measured).toBeGreaterThan(20);
  expect(sheet.faintText).toEqual([]);
  expect(sheet.sheetLuminance === null || sheet.sheetLuminance > 0.8).toBe(true);
  expect(sheet.rootColorScheme).toBe('light');

  await page.emulateMedia({ media: 'screen' });
  await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(10, 10, 9)');
});

test('says calmly that a dead report link expired', async ({ page }) => {
  await page.route('**/public/reports/*', (route) =>
    route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ error: 'booking.reportNotFound' }) })
  );

  await page.goto(`/relatorio/${'x'.repeat(48)}`);

  await expect(page.getByTestId('report-missing')).toContainText('Este link expirou ou não existe');
});

test('says a CNPJ with a wrong check digit is wrong', async ({ page }) => {
  await page.goto('/diagnostico');
  await page.getByTestId('path-cnpj').click();
  await page.getByTestId('cnpj-input').fill('11.222.333/0001-82');
  await page.getByTestId('cnpj-lookup').click();

  await expect(page.getByTestId('cnpj-invalid')).toBeVisible();
});

for (const width of [390, 1440]) {
  test(`the result does not scroll sideways at ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });

    await walkQuizToResult(page);

    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

    const simulator = await page.getByTestId('simulator').boundingBox();

    expect(simulator!.x + simulator!.width).toBeLessThanOrEqual(width);
  });

  test(`the slot picker and the report do not scroll sideways at ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });

    await walkQuizToResult(page);
    await bookTheSuggestedSlot(page);

    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

    const confirm = await page.getByTestId('slot-confirm').boundingBox();

    expect(confirm!.x + confirm!.width).toBeLessThanOrEqual(width);

    await page.goto(`/relatorio/${REPORT_TOKEN}`);
    await expect(page.getByTestId('impact-report')).toBeVisible();

    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
  });
}
