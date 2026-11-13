import type { Page, Route } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * The team's board (rnc-1 F5, R18) where jsdom cannot look: the geometry. At 390 the seven columns
 * scroll sideways INSIDE the board's frame and the page itself never does; at 1440 the same. A
 * card moved by its button lands in the next column, and a drag says it was a drag.
 */
const TEAM_MEMBER = {
  ...mockUser,
  name: 'Patrícia',
  last_name: 'Gomes',
  username: 'patricia',
  role: 'Equipe',
  role_slug: 'equipe',
  permissions: ['leads.read', 'leads.update', 'agenda.read'],
};

const STAGES = ['visitante', 'lead_parcial', 'lead', 'reuniao_marcada', 'proposta', 'cliente', 'perdido'];

function card(id: number, stage: string, company: string) {
  return {
    channel: 'formulario',
    company,
    contact_name: 'Ana Almeida',
    created_at: '2026-11-10T09:12:00-03:00',
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
    updated_at: '2026-11-12T10:00:00-03:00',
  };
}

const BOARD = {
  columns: STAGES.map((stage, index) => ({
    count: 1,
    items: [card(index + 1, stage, `Empresa Exemplo de Nome Comprido ${index + 1}`)],
    stage,
  })),
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function openBoard(page: Page, moves: Record<string, unknown>[] = []) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: TEAM_MEMBER })));
  await page.route(/\/leads(\?.*)?$/, (route) => route.fulfill(json({ data: BOARD })));
  await page.route(/\/leads\/\d+\/stage$/, async (route: Route) => {
    const body = route.request().postDataJSON() as Record<string, unknown>;
    const id = Number(route.request().url().match(/leads\/(\d+)/)?.[1]);

    moves.push({ id, ...body });

    await route.fulfill(
      json({
        data: {
          event: { at: '2026-11-19T10:00:00-03:00', data: { ...body, from: 'lead' }, id: 99, type: 'etapa.alterada', user: { id: 3, name: 'Patrícia Gomes' } },
          lead: { ...card(id, String(body.stage), `Empresa Exemplo de Nome Comprido ${id}`) },
        },
      })
    );
  });
  await page.goto('/equipe/pipeline');
  await expect(page.getByTestId('lead-card').first()).toBeVisible();
}

function column(page: Page, stage: string) {
  return page.locator(`[data-testid="column"][data-stage="${stage}"]`);
}

for (const width of [390, 1440]) {
  test.describe(`pipeline — ${width}`, () => {
    test.use({ viewport: { width, height: 900 } });

    test('the board scrolls inside its frame and the page never sideways', async ({ page }) => {
      await openBoard(page);

      await expect(page.getByTestId('column')).toHaveCount(7);

      const pageOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      const board = await page.getByTestId('board').evaluate((element) => ({ client: element.clientWidth, scroll: element.scrollWidth }));

      expect(pageOverflow).toBeLessThanOrEqual(0);
      expect(board.scroll).toBeGreaterThan(board.client);
    });

    test('a card moved by its button lands in the next column', async ({ page }) => {
      const moves: Record<string, unknown>[] = [];

      await openBoard(page, moves);
      await column(page, 'lead').getByTestId('move-next').click();

      await expect(column(page, 'reuniao_marcada').getByTestId('lead-card')).toHaveCount(2);
      await expect(column(page, 'lead').getByTestId('lead-card')).toHaveCount(0);
      expect(moves).toEqual([{ id: 3, stage: 'reuniao_marcada', via: 'botao' }]);
    });
  });
}

test.describe('pipeline — drag', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('a card dragged onto another column moves there', async ({ page }) => {
    const moves: Record<string, unknown>[] = [];

    await openBoard(page, moves);
    await column(page, 'lead').getByTestId('lead-card').dragTo(column(page, 'lead_parcial'));

    await expect(column(page, 'lead_parcial').getByTestId('lead-card')).toHaveCount(2);
    expect(moves).toEqual([{ id: 3, stage: 'lead_parcial', via: 'arrastar' }]);
  });
});
