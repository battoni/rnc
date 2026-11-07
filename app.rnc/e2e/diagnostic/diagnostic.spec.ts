import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * The diagnostic (rnc-1 F3, R9–R14) with the API mocked: the quiz from step 1 to the locked result
 * and the specialist CTA, the CNPJ path's refusal of a wrong check digit, and A15's width check on
 * the result — the longest screen of the flow — at 390 px and 1440 px. Measured in Portuguese.
 */
const TOKEN = 'k'.repeat(48);
const CONSENT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';
const PROFILE = { b2b: null, band: null, exporta: false, incentivos: false, regime: null, sector: null };
const LEAD = { cnpj: null, company: null, consent: { text: CONSENT, version: 1 }, has_contact: false, last_step: 'empresa', path: 'quiz', profile: PROFILE, stage: 'visitante' };

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

  await page.getByTestId('schedule-cta').click();

  await expect(page.getByTestId('booking-soon')).toContainText('A agenda online abre em breve');
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
}
