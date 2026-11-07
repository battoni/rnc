import type { PortalMe, PortalReferral, PortalStatement } from '../interfaces';
import { LEDGER_MODEL, REFERRAL_STATUS, SLA_STATE, STATEMENT_LINE_KIND } from '../enums';

/* What the portal API answers (rnc-2 F2's `PortalMeResource` and `PortalReferralResource`), as the specs need it. */
const PORTAL_ME_FIXTURE: PortalMe = {
  firm: { name: 'Franco Contabilidade', slug: 'franco' },
  partner: { id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
};

/** A new referral: no contact, no value — what a partner sees before it accepts (A5). */
const NEW_REFERRAL_FIXTURE: PortalReferral = {
  closed_at: null,
  company: 'Consultoria Prisma Ltda',
  contact: null,
  contacted_at: null,
  covers: ['Novo layout da NF-e', 'ERP pronto para 2027'],
  id: 31,
  meeting_at: null,
  message: 'A Franco indica a Consultoria Prisma Ltda. Podem fazer o primeiro contato em até 24 horas úteis?',
  profile: { band: 'b2', regime: 'simples', sector: 'servicos' },
  received_at: '2026-11-13T19:00:00+00:00',
  score: 81,
  sla: { hours: 3, state: SLA_STATE.OK },
  status: REFERRAL_STATUS.ENVIADO,
  status_at: null,
};

const LEAD_CONTACT = {
  email: 'ana.prisma@exemplo.com.br',
  name: 'Ana Exemplo',
  phone: '5511900000077',
  via: 'lead',
} as const;

/** The same referral, a step on: the API answers it after `aceito`, now with the lead's contact. */
const ACCEPTED_REFERRAL_FIXTURE: PortalReferral = {
  ...NEW_REFERRAL_FIXTURE,
  contact: { ...LEAD_CONTACT },
  status: REFERRAL_STATUS.ACEITO,
  status_at: '2026-11-16T13:00:00+00:00',
};

/** Builds a referral at `status` — the card's actions depend on nothing else. */
function referralAt(status: REFERRAL_STATUS, overrides: Partial<PortalReferral> = {}): PortalReferral {
  const accepted = status !== REFERRAL_STATUS.ENVIADO && status !== REFERRAL_STATUS.RECUSADO;

  return {
    ...NEW_REFERRAL_FIXTURE,
    contact: accepted ? { ...LEAD_CONTACT } : null,
    status,
    status_at: status === REFERRAL_STATUS.ENVIADO ? null : '2026-11-16T13:00:00+00:00',
    ...(status === REFERRAL_STATUS.FECHADO ? { deal_value_cents: 1_200_000 } : {}),
    ...overrides,
  };
}

/** A month under comissão (rnc-2 A9): R$ 12.000 and R$ 24.000 closed, R$ 1.200 + R$ 2.400 owed (`PartnerStatementResource`). */
const COMMISSION_STATEMENT_FIXTURE: PortalStatement = {
  commission_rate_bp: 1000,
  deal_total_cents: 3_600_000,
  deals: 2,
  firm: { name: 'Franco Contabilidade' },
  lines: [
    {
      amount_cents: 240_000,
      closed_at: '2026-11-12T14:00:00+00:00',
      company: 'Consultoria Prisma Ltda',
      deal_value_cents: 2_400_000,
      kind: STATEMENT_LINE_KIND.CLOSING,
      referral_id: 32,
    },
    {
      amount_cents: 120_000,
      closed_at: '2026-11-03T18:00:00+00:00',
      company: 'Padaria Trigo Dourado',
      deal_value_cents: 1_200_000,
      kind: STATEMENT_LINE_KIND.CLOSING,
      referral_id: 31,
    },
  ],
  model: LEDGER_MODEL.COMISSAO,
  month: '2026-11',
  total_cents: 360_000,
};

/** The same month under assinatura: the R$ 490 fee, and the closings add nothing. */
const SUBSCRIPTION_STATEMENT_FIXTURE: PortalStatement = {
  deal_total_cents: 1_200_000,
  deals: 1,
  firm: { name: 'Franco Contabilidade' },
  lines: [
    {
      amount_cents: 49_000,
      closed_at: null,
      company: null,
      deal_value_cents: null,
      kind: STATEMENT_LINE_KIND.SUBSCRIPTION,
      referral_id: null,
    },
    {
      amount_cents: 0,
      closed_at: '2026-11-03T18:00:00+00:00',
      company: 'Padaria Trigo Dourado',
      deal_value_cents: 1_200_000,
      kind: STATEMENT_LINE_KIND.CLOSING,
      referral_id: 31,
    },
  ],
  model: LEDGER_MODEL.ASSINATURA,
  month: '2026-11',
  monthly_fee_cents: 49_000,
  total_cents: 49_000,
};

export {
  ACCEPTED_REFERRAL_FIXTURE,
  COMMISSION_STATEMENT_FIXTURE,
  NEW_REFERRAL_FIXTURE,
  PORTAL_ME_FIXTURE,
  referralAt,
  SUBSCRIPTION_STATEMENT_FIXTURE,
};
