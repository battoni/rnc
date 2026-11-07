import type { Page, Route } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession, seedLanguage } from '../fixtures/auth';

/*
 * The partner's inbox (rnc-2 F3, R5–R8), with the API mocked: a partner walks one referral along
 * the whole graph — accept, first contact, the meeting (its form, the date-time field), the
 * proposal and the closing (its form, the contract value) — and each move lands on the card in
 * place: the lead's contact appears after the accept, the meeting joins "Próximas reuniões", and
 * the closed card reads its first-year value. The mock answers each move as the API would.
 */
const PARTNER_LOGIN = {
  ...mockUser,
  id: 21,
  name: 'NexoERP (exemplo)',
  last_name: '',
  username: 'parceiro.3',
  role: 'Parceiro',
  role_slug: 'parceiro',
  partner_id: 3,
  permissions: ['portal.access'],
};

const PORTAL_ME = {
  firm: { name: 'Franco Contabilidade', slug: 'franco' },
  partner: { id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
};

const LEAD_CONTACT = { via: 'lead', name: 'Ana Exemplo', phone: '5511900000077', email: 'ana.prisma@exemplo.com.br' };

function newReferral() {
  return {
    id: 31,
    status: 'enviado',
    received_at: '2026-11-13T19:00:00+00:00',
    status_at: null as string | null,
    company: 'Consultoria Prisma Ltda',
    profile: { sector: 'servicos', regime: 'simples', band: 'b2' },
    covers: ['Novo layout da NF-e', 'ERP pronto para 2027'],
    score: 81,
    message: 'A Franco indica a Consultoria Prisma Ltda. Podem fazer o primeiro contato em até 24 horas úteis?',
    sla: { state: 'ok', hours: 3 },
    contacted_at: null as string | null,
    meeting_at: null as string | null,
    closed_at: null as string | null,
    contact: null as typeof LEAD_CONTACT | null,
    deal_value_cents: undefined as number | undefined,
  };
}

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

/** The API's graph, as F2 built it: one step at a time, the contact from the accept on. */
async function answerMove(route: Route, referral: ReturnType<typeof newReferral>) {
  const body = route.request().postDataJSON() as { to: string; meeting_at?: string; deal_value_cents?: number };
  const now = new Date().toISOString();

  referral.status = body.to;
  referral.status_at = now;
  referral.contact = { ...LEAD_CONTACT };

  if (body.to === 'contato') {
    referral.contacted_at = now;
    referral.sla = { state: 'cumprido', hours: 3 };
  }

  if (body.to === 'reuniao') referral.meeting_at = body.meeting_at ?? null;

  if (body.to === 'fechado') {
    referral.closed_at = now;
    referral.deal_value_cents = body.deal_value_cents;
  }

  await route.fulfill(json({ data: referral }));
}

async function signedInWithInbox(page: Page) {
  const referral = newReferral();
  const moves: Record<string, unknown>[] = [];

  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: PARTNER_LOGIN })));
  await page.route('**/portal/me**', (route) => route.fulfill(json({ data: PORTAL_ME })));
  await page.route('**/portal/referrals', (route) => route.fulfill(json({ data: [referral] })));
  await page.route('**/portal/referrals/31/transition', (route) => {
    moves.push(route.request().postDataJSON() as Record<string, unknown>);

    return answerMove(route, referral);
  });

  return { moves };
}

test.describe('Partner portal — the inbox', () => {
  test('walks a referral from accept to the closed contract', async ({ page }) => {
    const { moves } = await signedInWithInbox(page);
    const card = page.getByTestId('portal-referral');

    await page.goto('/parceiro');
    await expect(card).toHaveAttribute('data-status', 'enviado');
    await expect(page.getByTestId('kpi-novos')).toContainText('1');
    await expect(card.getByTestId('referral-contact')).toHaveCount(0);

    await card.getByRole('button', { name: 'Aceitar' }).click();
    await expect(card).toHaveAttribute('data-status', 'aceito');
    await expect(card.getByTestId('referral-contact')).toContainText('Ana Exemplo');
    await expect(page.getByTestId('kpi-andamento')).toContainText('1');

    await card.getByRole('button', { name: 'Registrar primeiro contato' }).click();
    await expect(card).toHaveAttribute('data-status', 'contato');
    await expect(card.getByTestId('sla-chip')).toHaveAttribute('data-sla', 'cumprido');

    await card.getByRole('button', { name: 'Agendar reunião' }).click();
    await page.getByTestId('meeting-at').fill('2027-03-10T14:00');
    await page.getByTestId('meeting-confirm').click();
    await expect(card).toHaveAttribute('data-status', 'reuniao');
    await expect(page.getByTestId('portal-meetings')).toContainText('10/03');
    await expect(page.getByTestId('portal-meetings')).toContainText('Consultoria Prisma Ltda');

    await card.getByRole('button', { name: 'Enviar proposta' }).click();
    await expect(card).toHaveAttribute('data-status', 'proposta');

    await card.getByRole('button', { name: 'Fechar contrato' }).click();
    await page.locator('#deal-value').pressSequentially('12000');
    await page.getByTestId('close-confirm').click();
    await expect(card).toHaveAttribute('data-status', 'fechado');
    await expect(card.getByTestId('referral-standing')).toContainText('12.000,00');
    await expect(page.getByTestId('kpi-fechados')).toContainText('1');

    expect(moves.map((move) => move.to)).toEqual(['aceito', 'contato', 'reuniao', 'proposta', 'fechado']);
    expect(moves[2]).toMatchObject({ meeting_at: expect.stringMatching(/^2027-03-10T/) });
    expect(moves[4]).toMatchObject({ deal_value_cents: 1_200_000 });
  });
});
