import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * "Canal RNC" (rnc-3 F3b, R8, R9), with the API mocked: the operator advances a firm and the hero
 * follows the board the move answers; Reinaldo reads the same channel with no button, no platform
 * price and no "pela plataforma" lede (A9, AM15). Both fit a phone and a wide screen with no
 * sideways scroll, in both schemes.
 */
const OPERATOR = {
  ...mockUser,
  permissions: [...mockUser.permissions, 'channel.read', 'channel.manage'],
};

const REINALDO = {
  ...mockUser,
  id: 2,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'ceo',
  permissions: ['metrics.read', 'channel.read'],
};

type Stage = 'ativo' | 'contato' | 'demo' | 'implantacao';

const FIRMS: [string, string, Stage, Stage | null][] = [
  ['Contábil Serra Azul (exemplo)', 'Belo Horizonte-MG', 'ativo', null],
  ['Prisma Contabilidade (exemplo)', 'Curitiba-PR', 'ativo', null],
  ['Litoral Assessoria Contábil (exemplo)', 'Santos-SP', 'implantacao', 'ativo'],
  ['Cerrado Contadores (exemplo)', 'Goiânia-GO', 'demo', 'implantacao'],
  ['Pampa Gestão Contábil (exemplo)', 'Porto Alegre-RS', 'ativo', null],
  ['Mandacaru Contabilidade (exemplo)', 'Recife-PE', 'contato', 'demo'],
];

/** `ChannelResource` as the API answers it: closed for Reinaldo, with prices for the operator (AM15). */
function board(withPrices: boolean, advancedId: number | null = null) {
  const firms = FIRMS.map(([name, city, stage, next], index) => {
    const moved = index + 1 === advancedId;
    const row = {
      city,
      id: index + 1,
      name,
      next_stage: moved ? null : next,
      share_cents: 52_500,
      stage: moved ? 'ativo' : stage,
      stage_at: '2026-10-01T15:00:00+00:00',
    };

    return withPrices ? { ...row, monthly_fee_cents: 350_000, notes: null, share_bp: 1500 } : row;
  });
  const active = firms.filter((firm) => firm.stage === 'ativo').length;
  const count = (stage: Stage) => firms.filter((firm) => firm.stage === stage).length;
  const summary = {
    counts: { ativo: active, contato: count('contato'), demo: count('demo'), implantacao: count('implantacao') },
    monthly_share_cents: active * 52_500,
    pending_monthly_share_cents: (firms.length - active) * 52_500,
    yearly_share_cents: active * 52_500 * 12,
  };

  return withPrices
    ? { defaults: { monthly_fee_cents: 350_000, share_bp: 1500 }, firms, summary }
    : { firms, summary };
}

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signedInAs(page: Page, user: Record<string, unknown>, withPrices: boolean) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: user })));
  await page.route('**/channel', (route) => route.fulfill(json({ data: board(withPrices) })));
}

async function sidewaysScroll(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** The text as read, with `Intl`'s non-breaking spaces as plain ones. */
async function plainText(page: Page, testId: string): Promise<string> {
  return ((await page.getByTestId(testId).textContent()) ?? '').replace(/\s+/g, ' ');
}

test.describe('Canal RNC — the operator (R8)', () => {
  test('advances a firm and the hero follows: R$ 1.575 → R$ 2.100 a month', async ({ page }) => {
    const sent: unknown[] = [];

    await signedInAs(page, OPERATOR, true);
    await page.route('**/channel/firms/3/advance', async (route) => {
      sent.push(route.request().postDataJSON());
      await route.fulfill(json({ data: board(true, 3) }));
    });
    await page.goto('/operador/canal-rnc');

    await expect(page.getByTestId('channel-total')).toHaveAttribute('data-value', '157500');
    expect(await plainText(page, 'channel-total')).toContain('R$ 1.575,00/mês');
    expect(await plainText(page, 'channel-lede')).toContain('R$ 3.500,00/mês pela plataforma');

    const litoral = page.locator('[data-testid="channel-firm"][data-id="3"]');

    await litoral.getByRole('button', { name: 'Avançar para Ativo' }).click();

    await expect(page.getByTestId('channel-total')).toHaveAttribute('data-value', '210000');
    await expect(litoral).toHaveAttribute('data-stage', 'ativo');
    await expect(litoral.getByTestId('channel-active')).toBeVisible();
    expect(sent).toEqual([{ to: 'ativo' }]);
  });
});

test.describe('Canal RNC — Reinaldo reads it (A9, R9, AM15)', () => {
  for (const [width, scheme] of [
    [390, 'light'],
    [1440, 'dark'],
  ] as const) {
    test(`shows his share, the stage and the year total, without a button or the platform price, at ${width} in ${scheme}`, async ({
      page,
    }) => {
      await page.setViewportSize({ height: 900, width });
      await page.emulateMedia({ colorScheme: scheme });
      await signedInAs(page, REINALDO, false);
      await page.goto('/reinaldo/canal-rnc');

      await expect(page.getByTestId('channel-firm')).toHaveCount(6);

      const main = page.getByTestId('channel-hero').locator('xpath=ancestor::main[1]');
      const screenText = ((await main.textContent()) ?? '').replace(/\s+/g, ' ');

      expect(await plainText(page, 'channel-total')).toContain('R$ 1.575,00/mês');
      expect(await plainText(page, 'channel-hero')).toContain('R$ 18.900,00 por ano');
      expect(screenText).not.toContain('R$ 3.500');
      expect(screenText).not.toContain('pela plataforma');
      await expect(main.getByRole('button')).toHaveCount(0);
      expect(await sidewaysScroll(page)).toBeLessThanOrEqual(0);
    });
  }

  test('finds the channel in his rail', async ({ page }) => {
    await signedInAs(page, REINALDO, false);
    await page.goto('/reinaldo/canal-rnc');

    await expect(page.getByRole('link', { name: 'Canal RNC' }).first()).toBeVisible();
  });
});
