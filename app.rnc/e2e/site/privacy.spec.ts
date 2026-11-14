import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * rnc-4 A47 (R38, F9b): the firm's privacy notice at `/privacidade`, built from `GET public/privacy`'s
 * stable keys with the words in the SPA — the firm as controller, the platform as operator, what is
 * kept, for how long, and where a data request goes. Reached from the public footer. Measured in
 * Portuguese, at 390 and 1440 for sideways scroll.
 */
const FIRM = {
  name: 'Alvorada Contábil',
  short_name: 'Alvorada',
  slug: 'alvorada',
  logo_text: 'Alvorada Contábil',
  brand_primary: '#7a2e0e',
  brand_accent: '#f2a900',
  address: 'Rua das Palmeiras, 120, Centro, Campinas-SP',
  phone: '19 3200-1100',
  phone_href: 'tel:+551932001100',
  mailbox: 'contato@alvorada.exemplo.com.br',
  business_hours: [{ weekdays: [1, 2, 3, 4, 5], from: '08:00', to: '18:00' }],
};

const PRIVACY = {
  controller: { name: 'Alvorada Contábil', address: 'Rua das Palmeiras, 120, Centro, Campinas-SP', is_platform: false },
  operator: { name: 'Hub da Reforma' },
  collected: [
    'company',
    'cnpj',
    'contact_name',
    'email',
    'phone',
    'diagnostic_answers',
    'meeting',
    'site_visits',
    'consent_records',
  ],
  retention: [
    { data: 'lead_data', rule: 'until_request_or_firm_exit' },
    { data: 'consent_records', rule: 'kept_as_proof' },
    { data: 'site_visits', rule: 'pseudonymous' },
  ],
  sharing: ['partner_network'],
  requests: { email: 'contato@alvorada.exemplo.com.br', phone: '19 3200-1100' },
};

function json(data: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(data) };
}

async function mockPublicApi(page: Page) {
  await page.route('**/api/**', (route) => route.abort('connectionrefused'));
  await page.route('**/public/firm**', (route) => route.fulfill(json({ data: FIRM })));
  await page.route('**/public/privacy**', (route) => route.fulfill(json({ data: PRIVACY })));
  await page.route('**/public/sectors**', (route) => route.fulfill(json({ data: [] })));
  await page.route('**/public/events', (route) => route.fulfill(json([], 201)));
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
  await mockPublicApi(page);
});

test('the notice names the firm as controller, the platform as operator and where a request goes', async ({ page }) => {
  await page.goto('/privacidade');

  await expect(page.getByRole('heading', { level: 1 })).toContainText('Privacidade');

  const notice = page.getByTestId('privacy-notice');

  await expect(notice.getByTestId('privacy-controller')).toContainText('Alvorada Contábil');
  await expect(notice.getByTestId('privacy-operator')).toContainText('Hub da Reforma');
  await expect(notice.getByTestId('privacy-collected').locator('li')).toHaveCount(9);
  await expect(notice.getByTestId('privacy-retention').locator('li')).toHaveCount(3);
  await expect(notice.getByTestId('privacy-sharing')).toBeVisible();
  await expect(
    notice.getByTestId('privacy-requests').locator('a[href="mailto:contato@alvorada.exemplo.com.br"]')
  ).toBeVisible();
  await expect(page).toHaveTitle(/Privacidade/);
});

test('the public footer links to the notice', async ({ page }) => {
  await page.goto('/');

  await page.getByTestId('site-footer').getByTestId('footer-privacy').click();

  await expect(page).toHaveURL('/privacidade');
  await expect(page.getByTestId('privacy-notice')).toBeVisible();
});

for (const width of [390, 1440]) {
  test(`nothing scrolls sideways at ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/privacidade');

    await expect(page.getByTestId('privacy-controller')).toBeVisible();

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(overflow).toBeLessThanOrEqual(0);
  });
}
