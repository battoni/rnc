import type { Page } from '@playwright/test';
import { test, expect, mockUser, paginated, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * "Briefing do dia" (rnc-3 F9, R26, A23) with the API mocked and the clock fixed at 09:15 in São
 * Paulo: Reinaldo opens `/gestao` and lands on the briefing ("Bom dia, Reinaldo", the four KPIs,
 * the actions, the countdown, the three panels); every action button lands on the screen it names
 * — the trail filtered by `?parceiro=`, the agenda, the pipeline outlined at `?etapa=`, the Radar.
 * The screen fits 390 and 1440 px with no sideways scroll and no console error, and its words read
 * at 4.5:1 or more in both schemes.
 */
const REINALDO = {
  ...mockUser,
  id: 2,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'gestao',
  permissions: ['leads.read', 'agenda.read', 'metrics.read', 'referrals.read', 'radar.manage', 'briefing.read'],
};

const NOW = new Date('2026-11-09T09:15:00-03:00');

const STAGES = ['visitante', 'lead_parcial', 'lead', 'reuniao_marcada', 'proposta', 'cliente', 'perdido'];

const FIRST_ACTIONS = [
  {
    kind: 'sla_breached',
    params: { company: 'Padaria Trigo Dourado', goal_hours: 24, hours: 31.5, partner: 'Margem Certa (exemplo)' },
    to: '/gestao/trilha?parceiro=7',
  },
  { kind: 'meetings_today', params: { count: 2 }, to: '/equipe/agenda' },
  { kind: 'proposals', params: { count: 3, range_cents: [5_400_000, 9_000_000] }, to: '/equipe/pipeline?etapa=proposta' },
];

const LATER_ACTIONS = [
  { kind: 'partial_leads', params: { count: 4 }, to: '/equipe/pipeline?etapa=lead_parcial' },
  {
    kind: 'radar',
    params: { clients: 8, period: 'ano', range_cents: [4_000_000, 6_500_000], rule: 'contratos', title: 'Contratos longos sem cláusula' },
    to: '/gestao/radar',
  },
];

function referral(id: number, state: string, hours: number, company: string) {
  return { company, hours, id, lead_id: id, partner: { id: 7, name: 'Margem Certa (exemplo)' }, state };
}

function briefing(actions: unknown[]) {
  return {
    actions,
    as_of: '2026-11-09T09:15:00-03:00',
    leads: { last_24_hours: 2, last_7_days: 14 },
    meetings: {
      count: 6,
      next: [
        { company: 'Centro Médico Bandeira Ltda', lead_id: 11, specialist: 'Mariana', starts_at: '2026-11-09T10:00:00-03:00' },
        { company: 'Atacado Serra Azul Ltda', lead_id: 12, specialist: 'Carlos', starts_at: '2026-11-09T15:30:00-03:00' },
      ],
      today: 2,
      window_end: '2026-11-13',
    },
    pipeline: {
      count: 9,
      range_cents: [12_000_000, 21_600_000],
      stages: [
        { count: 4, range_cents: [3_600_000, 7_200_000], stage: 'lead' },
        { count: 2, range_cents: [3_000_000, 5_400_000], stage: 'reuniao_marcada' },
        { count: 3, range_cents: [5_400_000, 9_000_000], stage: 'proposta' },
      ],
    },
    referrals: {
      by_state: { atencao: 1, estourado: 2, ok: 4 },
      count: 7,
      goal_hours: 24,
      more: 2,
      top: [
        referral(40, 'estourado', 31.5, 'Padaria Trigo Dourado'),
        referral(41, 'estourado', 26, 'Distribuidora Horizonte Azul de Alimentos Ltda'),
        referral(42, 'atencao', 14, 'Oficina Boa Vista'),
        referral(43, 'ok', 3, 'Loja Pinheiros'),
        referral(44, 'ok', 1, 'Clínica Sol'),
      ],
    },
  };
}

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

function card(id: number, stage: string) {
  return {
    channel: 'formulario',
    company: `Empresa ${id}`,
    contact_name: 'Ana Almeida',
    created_at: '2026-11-05T09:12:00-03:00',
    has_contact: true,
    id,
    last_step: 'resultado',
    lost_reason: null,
    meeting: null,
    opportunities: 3,
    risks: 2,
    sector: 'comercio',
    source: 'organico',
    stage,
    updated_at: '2026-11-06T10:00:00-03:00',
  };
}

/** The briefing and every screen its actions open, mocked; `requested` keeps the trail's queries. */
async function mockApi(page: Page, actions: unknown[] = FIRST_ACTIONS) {
  const requested: URL[] = [];

  await page.clock.setFixedTime(NOW);
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: REINALDO })));
  await page.route('**/notifications', (route) => route.fulfill(json({ data: { items: [], unread_count: 0 } })));
  // The screen's own address ends in `/briefing` too: only the API's call is answered here.
  await page.route(/\/briefing$/, (route) =>
    route.request().isNavigationRequest() ? route.fallback() : route.fulfill(json({ data: briefing(actions) }))
  );
  await page.route(/\/referrals(\?.*)?$/, (route) => {
    requested.push(new URL(route.request().url()));

    return route.fulfill(json({ data: paginated([]) }));
  });
  await page.route(/\/partners\/options$/, (route) =>
    route.fulfill(json({ data: [{ active: true, label: 'Margem Certa (exemplo)', value: 7 }] }))
  );
  await page.route(/\/agenda(\?.*)?$/, (route) =>
    route.request().isNavigationRequest()
      ? route.fallback()
      : route.fulfill(
          json({
            data: { days: [], next_week: '2026-11-16', previous_week: '2026-11-02', week_end: '2026-11-13', week_start: '2026-11-09' },
          })
        )
  );
  await page.route(/\/leads(\?.*)?$/, (route) =>
    route.fulfill(
      json({ data: { brands: [], columns: STAGES.map((stage, index) => ({ count: 1, items: [card(index + 1, stage)], stage })) } })
    )
  );
  await page.route(/\/radar$/, (route) =>
    route.request().isNavigationRequest()
      ? route.fallback()
      : route.fulfill(json({ data: { clients: 0, risk_types: 0, total_cents: [0, 0], types: [], with_opportunity: 0 } }))
  );

  return requested;
}

test.describe('Briefing do dia', () => {
  test('/gestao lands on the briefing with the greeting, the KPIs, the actions and the countdown (A23)', async ({
    page,
  }) => {
    await mockApi(page);
    await page.goto('/gestao');

    await expect(page).toHaveURL(/\/gestao\/briefing$/);
    await expect(page.getByTestId('briefing-greeting')).toHaveText('Bom dia, Reinaldo');
    await expect(page.getByTestId('briefing-kpi')).toHaveCount(4);
    await expect(page.locator('[data-testid="briefing-kpi"][data-kpi="leads"]')).toContainText('2 nas últimas 24h');
    await expect(page.getByTestId('briefing-action')).toHaveCount(3);
    await expect(page.getByTestId('briefing-action').first()).toContainText(
      'Cobrar Margem Certa (exemplo): Padaria Trigo Dourado está há 31,5h úteis sem primeiro contato (meta 24h).'
    );
    await expect(page.locator('[data-testid="briefing-countdown"] [data-part="days"]')).toHaveText(/52/);
    await expect(page.getByTestId('briefing-referral')).toHaveCount(5);
    await expect(page.getByTestId('briefing-referrals-more')).toHaveText('e mais 2.');
  });

  test('"Ver parceiro" opens the trail filtered by the partner (W83)', async ({ page }) => {
    const requested = await mockApi(page);

    await page.goto('/gestao/briefing');
    await page.locator('[data-testid="briefing-action"][data-kind="sla_breached"]').getByTestId('briefing-action-go').click();

    await expect(page).toHaveURL(/\/gestao\/trilha\?parceiro=7$/);
    await expect.poll(() => requested.map((url) => url.searchParams.get('partner_id'))).toContain('7');
  });

  test('"Abrir agenda" opens the agenda', async ({ page }) => {
    await mockApi(page);

    await page.goto('/gestao/briefing');
    await page.locator('[data-testid="briefing-action"][data-kind="meetings_today"]').getByTestId('briefing-action-go').click();

    await expect(page).toHaveURL(/\/equipe\/agenda$/);
  });

  test('"Ver pipeline" opens the board outlined on Proposta (W83)', async ({ page }) => {
    await mockApi(page);

    await page.goto('/gestao/briefing');
    await page.locator('[data-testid="briefing-action"][data-kind="proposals"]').getByTestId('briefing-action-go').click();

    await expect(page).toHaveURL(/\/equipe\/pipeline\?etapa=proposta$/);
    await expect(page.locator('[data-testid="column"][data-stage="proposta"]')).toHaveAttribute('data-focused', 'true');
    await expect(page.locator('[data-testid="column"][data-focused]')).toHaveCount(1);
  });

  test('"Lista de ligação" opens the board on the partial leads, "Abrir Radar" the Radar', async ({ page }) => {
    await mockApi(page, LATER_ACTIONS);
    await page.setViewportSize({ width: 390, height: 800 });

    await page.goto('/gestao/briefing');
    await page.locator('[data-testid="briefing-action"][data-kind="partial_leads"]').getByTestId('briefing-action-go').click();

    await expect(page).toHaveURL(/\/equipe\/pipeline\?etapa=lead_parcial$/);

    const partial = page.locator('[data-testid="column"][data-stage="lead_parcial"]');

    await expect(partial).toHaveAttribute('data-focused', 'true');
    await expect(partial).toBeInViewport();

    await page.goBack();
    await page.locator('[data-testid="briefing-action"][data-kind="radar"]').getByTestId('briefing-action-go').click();

    await expect(page).toHaveURL(/\/gestao\/radar$/);
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
      await page.goto('/gestao/briefing');
      await expect(page.getByTestId('briefing-action').first()).toBeVisible();
      await expect(page.getByTestId('briefing-stage')).toHaveCount(3);

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
    test(`its words read at 4.5:1 or more, ${scheme}`, async ({ page }) => {
      await mockApi(page);
      await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });
      await page.goto('/gestao/briefing');
      await expect(page.getByTestId('briefing-referral').first()).toBeVisible();

      // Colours measured through a canvas: a computed `oklab(…)` read by a regex is black (F2 pitfall).
      const ratios = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true })!;

        canvas.width = 1;
        canvas.height = 1;

        const rgb = (colour: string): [number, number, number] => {
          context.clearRect(0, 0, 1, 1);
          context.fillStyle = '#fff';
          context.fillRect(0, 0, 1, 1);
          context.fillStyle = colour;
          context.fillRect(0, 0, 1, 1);
          const [r, g, b] = context.getImageData(0, 0, 1, 1).data;

          return [r!, g!, b!];
        };
        const channel = (value: number) => {
          const srgb = value / 255;

          return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
        };
        const luminance = ([r, g, b]: [number, number, number]) =>
          0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
        const groundOf = (element: Element): string => {
          for (let node: Element | null = element; node; node = node.parentElement) {
            const background = getComputedStyle(node).backgroundColor;

            if (background !== 'transparent' && !/^rgba\(.*,\s*0\)$/.test(background)) return background;
          }

          return getComputedStyle(document.body).backgroundColor;
        };
        const words = document.querySelectorAll(
          [
            '[data-testid="briefing-greeting"]',
            '[data-testid="briefing-kpi"] span',
            '[data-testid="briefing-kpi"] strong',
            '[data-testid="briefing-action-text"]',
            '[data-testid="briefing-countdown"] h2',
            '[data-testid="briefing-countdown"] strong',
            '[data-testid="briefing-countdown"] p',
            '[data-testid="briefing-meeting"] *',
            '[data-testid="briefing-referral"] *',
            '[data-testid="briefing-stage"] span',
          ].join(', ')
        );

        return Array.from(words)
          .filter((element) => element.childElementCount === 0 && element.textContent?.trim())
          .map((element) => {
            const [lighter, darker] = [luminance(rgb(getComputedStyle(element).color)), luminance(rgb(groundOf(element)))].sort(
              (a, b) => b - a
            );

            return { ratio: (lighter! + 0.05) / (darker! + 0.05), text: element.textContent!.trim().slice(0, 30) };
          });
      });

      expect(ratios.length).toBeGreaterThan(30);

      const weak = ratios.filter(({ ratio }) => ratio < 4.5).map(({ ratio, text }) => `"${text}" ${ratio.toFixed(2)}:1`);

      expect(weak).toEqual([]);
    });
  }
});
