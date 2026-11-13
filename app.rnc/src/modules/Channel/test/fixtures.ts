import type { ChannelBoard, ChannelFirm } from '../interfaces';

/*
 * The API's answers as the Channel specs need them — `ChannelResource` on the seeded six firms
 * (`ChannelSeeder`): three ativo at R$ 3.500 and 15 %, R$ 525 each. The operator's board carries the
 * fee, the rate, the notes and the defaults; Reinaldo's carries none of them (AM15).
 */
const SHARE_CENTS = 52_500;

const CEO_FIRMS: ChannelFirm[] = [
  ['Contábil Serra Azul (exemplo)', 'Belo Horizonte-MG', 'ativo', null],
  ['Prisma Contabilidade (exemplo)', 'Curitiba-PR', 'ativo', null],
  ['Litoral Assessoria Contábil (exemplo)', 'Santos-SP', 'implantacao', 'ativo'],
  ['Cerrado Contadores (exemplo)', 'Goiânia-GO', 'demo', 'implantacao'],
  ['Pampa Gestão Contábil (exemplo)', 'Porto Alegre-RS', 'ativo', null],
  ['Mandacaru Contabilidade (exemplo)', 'Recife-PE', 'contato', 'demo'],
].map(([name, city, stage, next], index) => ({
  city: city as string,
  id: index + 1,
  name: name as string,
  next_stage: next as ChannelFirm['next_stage'],
  share_cents: SHARE_CENTS,
  stage: stage as ChannelFirm['stage'],
  stage_at: '2026-10-01T15:00:00+00:00',
}));

const SUMMARY: ChannelBoard['summary'] = {
  counts: { ativo: 3, contato: 1, demo: 1, implantacao: 1 },
  monthly_share_cents: 157_500,
  pending_monthly_share_cents: 157_500,
  yearly_share_cents: 1_890_000,
};

const CEO_BOARD_FIXTURE: ChannelBoard = { firms: CEO_FIRMS, summary: SUMMARY };

const OPERATOR_BOARD_FIXTURE: ChannelBoard = {
  defaults: { monthly_fee_cents: 350_000, share_bp: 1500 },
  firms: CEO_FIRMS.map((firm) => ({ ...firm, monthly_fee_cents: 350_000, notes: null, share_bp: 1500 })),
  summary: SUMMARY,
};

/** The operator's board after Litoral (id 3) moved to ativo: R$ 2.100/mês, four ativo. */
const ADVANCED_BOARD_FIXTURE: ChannelBoard = {
  ...OPERATOR_BOARD_FIXTURE,
  firms: OPERATOR_BOARD_FIXTURE.firms.map((firm) =>
    firm.id === 3 ? { ...firm, next_stage: null, stage: 'ativo' } : firm
  ),
  summary: {
    counts: { ativo: 4, contato: 1, demo: 1, implantacao: 0 },
    monthly_share_cents: 210_000,
    pending_monthly_share_cents: 105_000,
    yearly_share_cents: 2_520_000,
  },
};

export { ADVANCED_BOARD_FIXTURE, CEO_BOARD_FIXTURE, OPERATOR_BOARD_FIXTURE };
