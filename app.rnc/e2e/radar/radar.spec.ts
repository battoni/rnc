import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * "Radar da carteira" (rnc-2 F10, R24–R27, A21), with the API mocked: Reinaldo opens the Radar,
 * reads the KPIs and the rows, picks a covering partner and sends "Encaminhar os 10 maiores" — the
 * Radar is read again and the button says "Todos já encaminhados"; a row opens its clients with the
 * proof and the access keys. The screen fits 390 and 1440 px with no sideways scroll and no console
 * error, and its state words read at 4.5:1 or more in both schemes.
 */
const REINALDO = {
  ...mockUser,
  id: 2,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'ceo',
  permissions: ['leads.read', 'metrics.read', 'radar.manage'],
};

const MARGEM = { id: 7, name: 'Margem Certa (exemplo)', specialty: 'precificacao_controladoria', batch: 10, sent: 0 };

const CONTRATOS = {
  rule: 'contratos',
  kind: 'oportunidade',
  title: 'Revisão de contratos longos',
  citation: 'LC 214/2025, art. 378',
  clients: 432,
  without_consent: 66,
  range_cents: [1_296_000_000, 3_888_000_000],
  period: 'ano',
  partners: [MARGEM],
};

const SERVICOS = {
  ...CONTRATOS,
  rule: 'servicos-30',
  title: 'Redução de 30% para serviços profissionais',
  clients: 40,
  without_consent: 5,
  range_cents: [40_000_000, 120_000_000],
  partners: [],
};

const SPLIT = {
  rule: 'split-payment',
  kind: 'risco',
  title: 'Split payment no caixa',
  citation: 'LC 214/2025, arts. 31–35',
  clients: 500,
  without_consent: 66,
  range_cents: [7_813_206_100, 11_719_809_200],
  period: 'mes',
  partners: [{ id: 4, name: 'Giro Capital (exemplo)', specialty: 'credito_capital_giro', batch: 10, sent: 0 }],
};

const DETAIL = {
  type: { ...CONTRATOS, detail: 'Contratos de longo prazo assinados antes da Reforma pedem revisão de preço.' },
  clients: [
    {
      id: 71,
      name: 'Atacado Serra Azul Ltda (exemplo)',
      cnpj: '99800021000110',
      sector: 'comercio',
      regime: 'real',
      band: 'b4',
      b2b_share: 70,
      partner_consent: true,
      range_cents: [15_000_000, 45_000_000],
      evidence: { source: 'cadastro' },
      keys: [],
    },
    {
      id: 72,
      name: 'Clínica Horizonte Ltda (exemplo)',
      cnpj: '99800022000101',
      sector: 'saude',
      regime: 'presumido',
      band: 'b3',
      b2b_share: 20,
      partner_consent: null,
      range_cents: [2_400_000, 7_200_000],
      evidence: { source: 'notas', window_days: 90, documents: 14, b2b_share: 80, declared_b2b_share: 20 },
      keys: ['35261199800022000101550010000010421000010420'],
    },
  ],
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function mockApi(page: Page) {
  const sent = { value: false };

  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: REINALDO })));
  await page.route('**/notifications', (route) => route.fulfill(json({ data: { items: [], unread_count: 0 } })));
  // The screen's own address ends in `/radar` too: only the API's call is answered here.
  await page.route(/\/radar$/, (route) =>
    route.request().isNavigationRequest()
      ? route.fallback()
      : route.fulfill(
          json({
            data: {
              clients: 500,
              with_opportunity: 500,
              total_cents: [1_336_000_000, 4_008_000_000],
              risk_types: 1,
              types: [
                { ...CONTRATOS, partners: [{ ...MARGEM, ...(sent.value ? { batch: 0, sent: 10 } : {}) }] },
                SERVICOS,
                SPLIT,
              ],
            },
          })
        )
  );
  await page.route(/\/radar\/types\/contratos$/, (route) => route.fulfill(json({ data: DETAIL })));
  await page.route(/\/radar\/referrals$/, (route) => {
    sent.value = true;

    return route.fulfill(json({ data: { sent: 10, skipped_no_consent: 66, skipped_already: 0 } }));
  });
}

function rowOf(page: Page, rule: string) {
  return page.locator(`[data-testid="radar-type"][data-rule="${rule}"]`);
}

test.describe('Radar da carteira', () => {
  test('sends a batch to a covering partner; the row then says every client was sent (A21)', async ({ page }) => {
    await mockApi(page);
    await page.goto('/reinaldo/radar');

    await expect(page.getByTestId('radar-clients')).toHaveAttribute('data-value', '500');
    await expect(page.getByTestId('radar-opportunities').getByTestId('radar-type')).toHaveCount(2);
    await expect(page.getByTestId('radar-risks').getByTestId('radar-type')).toHaveCount(1);

    const row = rowOf(page, 'contratos');

    await expect(row.getByTestId('radar-no-consent')).toContainText('66 sem autorização ficam fora do lote');
    await expect(rowOf(page, 'servicos-30').getByTestId('radar-no-partner')).toBeVisible();
    await expect(rowOf(page, 'split-payment').getByTestId('radar-split-held')).toContainText('retidos na liquidação');

    await row.getByTestId('radar-refer').click();
    await expect(row.getByTestId('radar-refer')).toContainText('Todos já encaminhados');
    await expect(row.getByTestId('radar-refer')).toBeDisabled();
    await expect(row.getByTestId('radar-sent')).toContainText('10 encaminhados');
  });

  test("opens a row's clients with the proof and the access keys (R25)", async ({ page }) => {
    await mockApi(page);
    await page.goto('/reinaldo/radar');

    const row = rowOf(page, 'contratos');

    await row.getByTestId('radar-open').click();

    const detail = row.getByTestId('radar-detail');

    await expect(detail.getByTestId('radar-client')).toHaveCount(2);
    await expect(detail.getByTestId('radar-proof').first()).toHaveText('do cadastro');
    await expect(detail.getByTestId('radar-key')).toHaveCount(1);
    await expect(detail.getByTestId('radar-client-no-consent')).toHaveCount(1);
  });

  for (const width of [390, 1440]) {
    test(`fits ${width} px with no sideways scroll and no console error`, async ({ page }) => {
      const errors: string[] = [];

      page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
      });
      page.on('pageerror', (error) => errors.push(error.message));

      await mockApi(page);
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/reinaldo/radar');
      await rowOf(page, 'contratos').getByTestId('radar-open').click();
      await expect(page.getByTestId('radar-client').first()).toBeVisible();

      const overflow = await page.evaluate(() => {
        const scroller = document.querySelector('.main-scroll');
        const page = document.documentElement.scrollWidth - document.documentElement.clientWidth;

        return Math.max(page, scroller ? scroller.scrollWidth - scroller.clientWidth : 0);
      });

      expect(overflow).toBeLessThanOrEqual(0);
      expect(errors).toEqual([]);
    });
  }

  for (const scheme of ['light', 'dark'] as const) {
    test(`the state words read at 4.5:1 or more, ${scheme}`, async ({ page }) => {
      await mockApi(page);
      await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
      await page.goto('/reinaldo/radar');
      await rowOf(page, 'contratos').getByTestId('radar-open').click();
      await expect(page.getByTestId('radar-client').first()).toBeVisible();

      const ratios = await page.evaluate((schemeName) => {
        document.documentElement.setAttribute('data-theme', schemeName);

        const channel = (value: number) => {
          const srgb = value / 255;

          return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
        };
        const luminance = (colour: string) => {
          const [r, g, b] = (colour.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map(Number);

          return 0.2126 * channel(r!) + 0.7152 * channel(g!) + 0.0722 * channel(b!);
        };
        const groundOf = (element: Element): string => {
          for (let node: Element | null = element; node; node = node.parentElement) {
            const background = getComputedStyle(node).backgroundColor;

            if (!/rgba\(.*,\s*0\)$/.test(background) && background !== 'transparent') return background;
          }

          return 'rgb(255, 255, 255)';
        };
        const texts = document.querySelectorAll(
          [
            '[data-testid="radar-kind"]',
            '[data-testid="radar-range"]',
            '[data-testid="radar-estimate"]',
            '[data-testid="radar-split-held"] strong',
            '[data-testid="radar-no-consent"]',
            '[data-testid="radar-proof"]',
            '[data-testid="radar-key"]',
            '[data-testid="radar-client-no-consent"]',
            '[data-testid="radar-client-range"]',
          ].join(', ')
        );

        return Array.from(texts)
          .filter((element) => element.textContent?.trim())
          .map((element) => {
            const [lighter, darker] = [luminance(getComputedStyle(element).color), luminance(groundOf(element))].sort(
              (a, b) => b - a
            );

            return { ratio: (lighter! + 0.05) / (darker! + 0.05), text: element.textContent!.trim().slice(0, 30) };
          });
      }, scheme);

      expect(ratios.length).toBeGreaterThan(8);

      const weak = ratios
        .filter(({ ratio }) => ratio < 4.5)
        .map(({ ratio, text }) => `"${text}" ${ratio.toFixed(2)}:1`);

      expect(weak).toEqual([]);
    });
  }
});
