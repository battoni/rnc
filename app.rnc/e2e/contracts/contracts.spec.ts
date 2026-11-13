import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * "Alerta de contratos" (rnc-3 F7, R18–R22, completes A20) with the API mocked: Reinaldo reads the
 * KPIs and the at-risk rows, a row without consent keeps "Encaminhar ao jurídico" disabled with its
 * reason, he sends the most exposed contract to Andrade and the row says "Com Andrade"; "Cláusula
 * incluída", once confirmed, takes a row off the list; the import names the lines it left out.
 * The partner's portal shows a contract referral's summary with the value band (R21). The screen
 * fits 390 and 1440 px with no sideways scroll and no console error, and its state words read at
 * 4.5:1 or more in both schemes.
 */
const REINALDO = {
  ...mockUser,
  id: 2,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'ceo',
  permissions: ['leads.read', 'metrics.read', 'contracts.manage'],
};

const PARTNER_LOGIN = {
  ...mockUser,
  id: 21,
  name: 'Andrade Tributário (exemplo)',
  last_name: '',
  username: 'parceiro.1',
  role: 'Parceiro',
  role_slug: 'parceiro',
  partner_id: 1,
  permissions: ['portal.access'],
};

const ANDRADE = { id: 1, name: 'Andrade Tributário (exemplo)', specialty: 'advocacia_tributaria' };
const MARGEM = { id: 7, name: 'Margem Certa (exemplo)', specialty: 'precificacao_controladoria' };

function contract(id: number, overrides: Record<string, unknown> = {}) {
  return {
    id,
    client: { id: id + 100, name: `Cliente ${id} Ltda`, partner_consent: true, partner_consent_via: 'contrato' },
    kind: 'fornecimento',
    annual_value_cents: 40_000_000,
    start_year: 2025,
    end_year: 2030,
    years_exposed: 4,
    exposure_cents: 160_000_000,
    referred_to: [] as { id: number; name: string }[],
    ...overrides,
  };
}

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function mockApi(page: Page) {
  const rows = [
    contract(9, {
      client: { id: 31, name: 'Centro Médico Bandeira Ltda', partner_consent: true, partner_consent_via: 'contrato' },
      kind: 'distribuicao',
      annual_value_cents: 109_000_000,
      start_year: 2024,
      end_year: 2033,
      years_exposed: 7,
      exposure_cents: 763_000_000,
    }),
    contract(12, {
      client: { id: 32, name: 'Atacado Serra Azul Ltda', partner_consent: false, partner_consent_via: 'contrato' },
    }),
    contract(15, { kind: 'locacao', annual_value_cents: 4_000_000, end_year: 2027, start_year: 2022, years_exposed: 1, exposure_cents: 4_000_000 }),
  ];
  const state = { rows, referred: 0 };

  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: REINALDO })));
  await page.route('**/notifications', (route) => route.fulfill(json({ data: { items: [], unread_count: 0 } })));
  await page.route(/\/contracts$/, (route) =>
    route.request().isNavigationRequest()
      ? route.fallback()
      : route.fulfill(
          json({
            data: {
              summary: {
                at_risk: state.rows.length,
                total: 40,
                annual_value_cents: state.rows.reduce((sum, row) => sum + row.annual_value_cents, 0),
                exposure_cents: state.rows.reduce((sum, row) => sum + row.exposure_cents, 0),
                referred: state.referred,
              },
              contracts: state.rows,
            },
          })
        )
  );
  await page.route(/\/contracts\/legal-partners$/, (route) => route.fulfill(json({ data: [ANDRADE, MARGEM] })));
  await page.route(/\/contracts\/\d+\/referrals$/, (route) => {
    const id = Number(/contracts\/(\d+)\/referrals/.exec(route.request().url())![1]);
    const body = route.request().postDataJSON() as { partner_id: number };
    const partner = [ANDRADE, MARGEM].find((candidate) => candidate.id === body.partner_id)!;

    state.rows = state.rows.map((row) =>
      row.id === id ? { ...row, referred_to: [{ id: partner.id, name: partner.name }] } : row
    );
    state.referred++;

    return route.fulfill(json({ data: { id: 501 } }, 201));
  });
  await page.route(/\/contracts\/\d+\/clause$/, (route) => {
    const id = Number(/contracts\/(\d+)\/clause/.exec(route.request().url())![1]);

    state.rows = state.rows.filter((row) => row.id !== id);

    return route.fulfill(json({ data: { id, has_reforma_clause: true } }));
  });
  await page.route(/\/contracts\/import$/, (route) =>
    route.fulfill(
      json({
        data: {
          created: 2,
          updated: 1,
          total: 5,
          errors: [
            { line: 3, field: 'cnpj_cliente', code: 'client_unknown' },
            { line: 5, field: 'fim', code: 'end_before_start' },
          ],
          total_errors: 2,
        },
      })
    )
  );
}

function rowOf(page: Page, id: number) {
  return page.locator(`[data-testid="contract"][data-id="${id}"]`);
}

test.describe('Alerta de contratos', () => {
  test('sends a contract to legal and the row says "Com <parceiro>"; no consent keeps it disabled (A20)', async ({
    page,
  }) => {
    await mockApi(page);
    await page.goto('/reinaldo/contratos');

    await expect(page.getByTestId('contracts-at-risk')).toContainText('3 de 40');
    await expect(page.getByTestId('contract')).toHaveCount(3);
    await expect(page.getByTestId('contracts-note')).toContainText('lida em 05/11/2026');

    const blocked = rowOf(page, 12);

    await expect(blocked.getByTestId('contract-refer')).toBeDisabled();
    await expect(blocked.getByTestId('contract-consent-blocked')).toContainText('sem autorização no contrato de serviços');

    const top = rowOf(page, 9);

    await expect(top.getByTestId('contract-line')).toContainText('Distribuição · desde 2024');
    await expect(top.locator('[data-testid="contract-year"][data-lit="true"]')).toHaveCount(7);
    await top.getByTestId('contract-refer').click();
    await expect(top.getByTestId('contract-sent')).toContainText('Com Andrade Tributário (exemplo)');
    await expect(page.getByTestId('contracts-referred')).toContainText('1');
  });

  test('"Cláusula incluída", once confirmed, takes the row off the list (A20)', async ({ page }) => {
    await mockApi(page);
    await page.setViewportSize({ width: 390, height: 700 });
    await page.goto('/reinaldo/contratos');

    // The last row sits below the fold at 390: the question is the row's own, so a scroll never loses it.
    await rowOf(page, 15).getByTestId('contract-clause').click();
    await rowOf(page, 15).getByTestId('contract-clause-yes').click();

    await expect(rowOf(page, 15)).toHaveCount(0);
    await expect(page.getByTestId('contracts-at-risk')).toContainText('2 de 40');
  });

  test('the import names the lines it left out and keeps the dialog open (R18)', async ({ page }) => {
    await mockApi(page);
    await page.goto('/reinaldo/contratos');

    await page.getByTestId('contracts-import').click();
    await expect(page.getByTestId('contracts-import-template')).toBeVisible();
    await page.getByTestId('contracts-import-file').setInputFiles({
      name: 'contratos.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from('cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma\n'),
    });
    await page.getByRole('button', { name: 'Importar', exact: true }).click();

    await expect(page.getByTestId('contracts-import-partial')).toContainText('3 contratos gravados');
    await expect(page.getByTestId('contracts-import-line-error')).toHaveCount(2);
    await expect(page.getByTestId('contracts-import-line-error').first()).toContainText('Linha 3');
  });

  test("the partner's portal shows a contract referral with the value band, not the value (R21)", async ({
    page,
  }) => {
    await seedAuthSession(page);
    await seedLanguage(page, 'pt-BR');
    await page.route('**/auth/me**', (route) => route.fulfill(json({ data: PARTNER_LOGIN })));
    await page.route('**/portal/me**', (route) =>
      route.fulfill(
        json({ data: { firm: { name: 'Franco Contabilidade', slug: 'franco' }, partner: { ...ANDRADE } } })
      )
    );
    await page.route('**/portal/referrals', (route) =>
      route.fulfill(
        json({
          data: [
            {
              id: 41,
              status: 'enviado',
              received_at: '2026-11-07T13:00:00+00:00',
              status_at: null,
              company: 'Centro Médico Bandeira Ltda',
              profile: { sector: 'saude', regime: 'real', band: 'b4' },
              covers: [],
              score: 0,
              message:
                'A Franco Contabilidade indica a Centro Médico Bandeira Ltda: o contrato de distribuição de R$ 1 mi a R$ 2 mi por ano vai até 2033 sem cláusula de reequilíbrio para a Reforma.',
              sla: { state: 'ok', hours: 2 },
              contacted_at: null,
              meeting_at: null,
              closed_at: null,
              contact: null,
              contract: {
                kind: 'distribuicao',
                end_year: 2033,
                sem_clausula: true,
                value_range: { min_cents: 100_000_000, max_cents: 200_000_000, label: 'R$ 1 mi a R$ 2 mi' },
              },
            },
          ],
        })
      )
    );
    await page.goto('/parceiro');

    const summary = page.getByTestId('referral-contract');

    await expect(summary).toContainText('Contrato de distribuição');
    await expect(summary).toContainText('até 2033');
    await expect(summary).toContainText('sem cláusula da Reforma');
    await expect(page.getByTestId('referral-contract-range')).toContainText('por ano');
    await expect(summary).not.toContainText('1.090.000');
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
      await page.goto('/reinaldo/contratos');
      await expect(page.getByTestId('contract').first()).toBeVisible();

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
      await page.goto('/reinaldo/contratos');
      await rowOf(page, 9).getByTestId('contract-refer').click();
      await expect(rowOf(page, 9).getByTestId('contract-sent')).toBeVisible();

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
            '[data-testid="contract-year"]',
            '[data-testid="contract-line"]',
            '[data-testid="contract-exposure"]',
            '[data-testid="contract-sent"]',
            '[data-testid="contract-consent-blocked"]',
            '[data-testid="contracts-note"]',
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

      expect(ratios.length).toBeGreaterThan(20);

      const weak = ratios
        .filter(({ ratio }) => ratio < 4.5)
        .map(({ ratio, text }) => `"${text}" ${ratio.toFixed(2)}:1`);

      expect(weak).toEqual([]);
    });
  }
});
