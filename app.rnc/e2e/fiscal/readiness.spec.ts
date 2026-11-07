import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * The fiscal screens (rnc-2 F8, R15–R22), with the API mocked: Reinaldo opens "Prontidão da NF-e",
 * filters the críticos, opens a client and registers the notice — the line lands on its timeline;
 * proposes a supplier review — the row reads "Revisão proposta"; uploads a ZIP and reads its batch
 * with the refusals by file. Each screen fits 390 and 1440 px with no sideways scroll and no console
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
  permissions: ['leads.read', 'metrics.read', 'fiscal.read', 'fiscal.import'],
};

const CRITICO = {
  id: 7,
  name: 'Metalúrgica Boa Vista Ltda (exemplo)',
  cnpj: '99800001000191',
  sector: 'industria',
  regime: 'real',
  system: 'ERP Delta 4.2',
  invoices: 40,
  complete: 28,
  complete_pct: 70,
  at_risk: 3,
  status: 'critico',
};

const ATENCAO = {
  ...CRITICO,
  id: 8,
  name: 'Distribuidora Rio Claro Ltda (exemplo)',
  complete: 36,
  complete_pct: 90,
  at_risk: 0,
  status: 'atencao',
  system: 'Emissor Nuvem 2.0',
};
const PRONTO = {
  ...CRITICO,
  id: 9,
  name: 'Padaria Estrela Ltda (exemplo)',
  complete: 39,
  complete_pct: 97.5,
  at_risk: 0,
  status: 'ok',
};
const SEM_NOTAS = {
  ...CRITICO,
  id: 10,
  name: 'Clínica Horizonte Ltda (exemplo)',
  invoices: 0,
  complete: 0,
  complete_pct: null,
  at_risk: 0,
  status: 'sem_notas',
  system: null,
};

const SUMMARY = {
  clients: 4,
  invoices: 120,
  complete: 103,
  complete_pct: 85.8,
  at_risk: 3,
  by_status: { critico: 1, atencao: 1, ok: 1, sem_notas: 1 },
};

const DETAIL = {
  ...CRITICO,
  window_days: 30,
  issues: [
    { code: 'missing_group', invoices: 3, risk: true, samples: [{ number: 1042, series: 1, item: 2 }] },
    { code: 'missing_codes', invoices: 6, risk: false, samples: [{ number: 1040, series: 1, item: 4 }] },
    { code: 'without_test_values', invoices: 3, risk: false, samples: [{ number: 1031, series: 2, item: null }] },
  ],
};

const NOTICE = {
  id: 51,
  client_id: 7,
  type: 'nfe.aviso',
  data: { invoices: 40, complete_pct: 70, at_risk: 3, status: 'critico' },
  at: '2026-11-09T16:00:00+00:00',
  user: { id: 2, name: 'Reinaldo Franco' },
};

const ATACADO = {
  id: 21,
  name: 'Atacado Serra Azul Ltda (exemplo)',
  cnpj: '99800021000110',
  sector: 'comercio',
  regime: 'real',
  documents: 36,
  monthly_purchases_cents: 10_000_000,
  simples_share: 30,
  normal_share: 70,
  lost_per_year_cents: [7_740_000, 8_460_000],
  proposed: false,
};

const LABORATORIO = {
  ...ATACADO,
  id: 22,
  name: 'Laboratório Vida Ltda (exemplo)',
  sector: 'saude',
  simples_share: 0,
  normal_share: 100,
  lost_per_year_cents: [0, 0],
};

const REVIEW = {
  id: 52,
  client_id: 21,
  type: 'fornecedores.revisao',
  data: { simples_share: 30, lost_per_year_cents: [7_740_000, 8_460_000] },
  at: '2026-11-09T16:05:00+00:00',
  user: { id: 2, name: 'Reinaldo Franco' },
};

const FOLDER_BATCH = {
  id: 40,
  source: 'folder',
  user_id: null,
  by_name: null,
  files: 62,
  stored: 62,
  refused: [],
  at: '2026-11-08T11:00:00+00:00',
};

const UPLOAD_BATCH = {
  id: 41,
  source: 'upload',
  user_id: 2,
  by_name: 'Reinaldo Franco',
  files: 3,
  stored: 1,
  refused: [
    { file: 'leia-me.xml', code: 'not_xml' },
    { file: '35261199800099000100550010000001231000001234.xml', code: 'client_unknown' },
  ],
  at: '2026-11-09T17:05:00+00:00',
};

const SCREENS = ['/equipe/nfe', '/equipe/fornecedores', '/reinaldo/notas'] as const;

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function mockApi(page: Page) {
  const proposed = { value: false };
  const events: unknown[] = [];

  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: REINALDO })));
  await page.route('**/notifications', (route) => route.fulfill(json({ data: { items: [], unread_count: 0 } })));
  await page.route(/\/fiscal\/readiness(\?.*)?$/, (route) => {
    const status = new URL(route.request().url()).searchParams.get('status');
    const clients = [CRITICO, ATENCAO, PRONTO, SEM_NOTAS].filter((client) => !status || client.status === status);

    return route.fulfill(
      json({ data: { summary: SUMMARY, systems: ['ERP Delta 4.2', 'Emissor Nuvem 2.0'], clients } })
    );
  });
  await page.route(/\/fiscal\/readiness\/7$/, (route) => route.fulfill(json({ data: DETAIL })));
  await page.route(/\/fiscal\/readiness\/7\/notice$/, (route) => {
    events.unshift(NOTICE);

    return route.fulfill(json({ data: NOTICE }, 201));
  });
  await page.route(/\/clients\/7\/events$/, (route) => route.fulfill(json({ data: events })));
  await page.route(/\/fiscal\/supplier-credit(\?.*)?$/, (route) =>
    route.fulfill(
      json({
        data: {
          summary: {
            clients: 2,
            buying_from_simples: 1,
            mean_simples_share: 30,
            lost_per_year_cents: [7_740_000, 8_460_000],
            proposed: proposed.value ? 1 : 0,
          },
          clients: [{ ...ATACADO, proposed: proposed.value }, LABORATORIO],
        },
      })
    )
  );
  await page.route(/\/fiscal\/supplier-credit\/21\/review$/, (route) => {
    proposed.value = true;

    return route.fulfill(json({ data: REVIEW }, 201));
  });
  await page.route(/\/fiscal\/imports(\?.*)?$/, (route) => {
    if (route.request().method() === 'POST') return route.fulfill(json({ data: UPLOAD_BATCH }, 201));

    return route.fulfill(
      json({ data: { current_page: 1, data: [FOLDER_BATCH], last_page: 1, per_page: 10, total: 1 } })
    );
  });
}

test.describe('Fiscal screens', () => {
  test('registers a notice from a critical client, and the line lands on its timeline', async ({ page }) => {
    await mockApi(page);
    await page.goto('/equipe/nfe');

    await expect(page.getByTestId('readiness-risk-total')).toHaveAttribute('data-value', '3');
    await page.getByTestId('readiness-filter-critico').click();
    await expect(page.getByTestId('readiness-row')).toHaveCount(1);

    await page.getByTestId('readiness-open').first().click();

    const detail = page.getByTestId('readiness-detail');

    await expect(detail.getByTestId('readiness-issue')).toHaveCount(3);
    await expect(detail.getByTestId('timeline-empty')).toBeVisible();

    await detail.getByTestId('readiness-notify').click();
    await expect(page.getByTestId('notice-text')).toContainText('ERP Delta 4.2');
    await page.getByTestId('notice-register').click();
    await expect(page.getByTestId('notice-registered')).toContainText('Reinaldo Franco');

    await expect(detail.getByTestId('timeline-line')).toHaveCount(1);
    await expect(detail.getByTestId('timeline-line').first()).toHaveAttribute('data-type', 'nfe.aviso');
  });

  test('proposes a supplier review; the 0 % client cannot', async ({ page }) => {
    await mockApi(page);
    await page.goto('/equipe/fornecedores');

    const rows = page.getByTestId('supplier-row');

    await expect(rows).toHaveCount(2);
    await expect(rows.nth(1).getByTestId('supplier-propose')).toBeDisabled();

    await rows.nth(0).getByTestId('supplier-propose').click();
    await expect(rows.nth(0).getByTestId('supplier-proposed')).toBeVisible();
    await expect(page.getByTestId('supplier-proposed-count')).toHaveText('1');
  });

  test('uploads a ZIP and reads its batch, every refusal by file', async ({ page }) => {
    await mockApi(page);
    await page.goto('/reinaldo/notas');

    await expect(page.getByTestId('nfe-batch')).toHaveCount(1);
    await page
      .getByTestId('nfe-file')
      .setInputFiles({ name: 'notas.zip', mimeType: 'application/zip', buffer: Buffer.from('PK') });
    await page.getByTestId('nfe-import-submit').click();

    const result = page.getByTestId('nfe-result');

    await expect(result.getByTestId('nfe-refusal')).toHaveCount(2);
    await expect(result.getByTestId('nfe-refusal').first()).toContainText('leia-me.xml');
    await expect(page.getByTestId('nfe-batches').getByTestId('nfe-batch')).toHaveCount(2);
  });

  for (const width of [390, 1440]) {
    test(`the three screens fit ${width} px with no sideways scroll and no console error`, async ({ page }) => {
      const errors: string[] = [];

      page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
      });
      page.on('pageerror', (error) => errors.push(error.message));

      await mockApi(page);
      await page.setViewportSize({ width, height: 900 });

      for (const path of SCREENS) {
        await page.goto(path);
        await expect(
          page.locator('[data-testid="readiness-row"], [data-testid="supplier-row"], [data-testid="nfe-batch"]').first()
        ).toBeVisible();

        const overflow = await page.evaluate(() => {
          const scroller = document.querySelector('.main-scroll');
          const page = document.documentElement.scrollWidth - document.documentElement.clientWidth;

          return Math.max(page, scroller ? scroller.scrollWidth - scroller.clientWidth : 0);
        });

        expect(overflow, path).toBeLessThanOrEqual(0);
      }

      expect(errors).toEqual([]);
    });
  }

  for (const scheme of ['light', 'dark'] as const) {
    test(`the state words read at 4.5:1 or more, ${scheme}`, async ({ page }) => {
      await mockApi(page);
      await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });

      const ratios: { ratio: number; text: string }[] = [];

      for (const path of SCREENS) {
        await page.goto(path);
        await expect(
          page.locator('[data-testid="readiness-row"], [data-testid="supplier-row"], [data-testid="nfe-batch"]').first()
        ).toBeVisible();

        if (path === '/reinaldo/notas') {
          await page
            .getByTestId('nfe-file')
            .setInputFiles({ name: 'notas.zip', mimeType: 'application/zip', buffer: Buffer.from('PK') });
          await page.getByTestId('nfe-import-submit').click();
          await expect(page.getByTestId('nfe-result').getByTestId('nfe-refusal')).toHaveCount(2);
        }

        ratios.push(
          ...(await page.evaluate((schemeName) => {
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
                '[data-testid="readiness-status"]',
                '[data-testid="readiness-risk-total"]',
                '[data-testid="readiness-pct"] span',
                '[data-testid="supplier-range"]',
                '[data-testid="supplier-estimate"]',
                '[data-testid="supplier-part"]',
                '[data-testid="supplier-proposed"]',
                '[data-testid="nfe-refusal"] span',
                '[data-testid="nfe-batch-stored"]',
                '[data-testid="nfe-batch-refused"]',
              ].join(', ')
            );

            return Array.from(texts)
              .filter((element) => element.textContent?.trim())
              .map((element) => {
                const [lighter, darker] = [
                  luminance(getComputedStyle(element).color),
                  luminance(groundOf(element)),
                ].sort((a, b) => b - a);

                return { ratio: (lighter! + 0.05) / (darker! + 0.05), text: element.textContent!.trim().slice(0, 30) };
              });
          }, scheme))
        );
      }

      expect(ratios.length).toBeGreaterThan(10);

      const weak = ratios
        .filter(({ ratio }) => ratio < 4.5)
        .map(({ ratio, text }) => `"${text}" ${ratio.toFixed(2)}:1`);

      expect(weak).toEqual([]);
    });
  }
});
