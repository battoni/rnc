import type { NetworkLedger, Partner, PartnerAccess, PartnerMatches, Referral } from '../interfaces';
import { LEDGER_MODEL, SLA_STATE } from '@PortalModule';

/* What the API answers for the network (rnc-1 F6), as the specs need it. */
const MATCHES_FIXTURE: PartnerMatches = {
  blocked_reason: null,
  can_refer: true,
  matches: [
    {
      covers: [
        { id: 'simples-credito', title: 'Crédito do Simples' },
        { id: 'servicos-30', title: 'Regime de 30% para serviços' },
        { id: 'contratos', title: 'Contratos de longo prazo' },
      ],
      partner: {
        city: 'Santo André-SP',
        id: 7,
        name: 'Margem Certa (exemplo)',
        specialty: 'precificacao_controladoria',
      },
      referral: null,
      score: 92,
    },
    {
      covers: [],
      partner: { city: 'Barueri-SP', id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
      referral: {
        at: '2026-11-18T15:00:00-03:00',
        deal_value_cents: null,
        id: 40,
        sla: { hours: 3, state: SLA_STATE.OK },
        status: 'enviado',
        status_at: null,
      },
      score: 19,
    },
  ],
};

const REFERRAL_FIXTURE: Referral = {
  at: '2026-11-19T11:00:00-03:00',
  consent: {
    at: '2026-11-18T09:30:00-03:00',
    given: true,
    text: 'Autorizo a Franco a me apresentar a parceiros.',
    version: 1,
  },
  covers: [{ id: 'contratos', title: 'Contratos de longo prazo' }],
  deal_value_cents: null,
  id: 51,
  lead: { company: 'Consultoria Prisma', id: 7 },
  message: 'A Franco indica a Consultoria Prisma.',
  partner: { active: true, id: 7, name: 'Margem Certa (exemplo)', specialty: 'precificacao_controladoria' },
  referred_by: { id: 3, name: 'Patrícia Gomes' },
  score: 92,
  sla: { hours: 0, state: SLA_STATE.OK },
  status: 'enviado',
  status_at: null,
};

const PARTNER_FIXTURE: Partner = {
  access: null,
  active: true,
  city: 'Barueri-SP',
  email: 'contato3@parceiro.exemplo',
  id: 3,
  name: 'NexoERP (exemplo)',
  phone: '551190009003',
  specialty: 'erp_nfe',
};

/** An active portal login (rnc-2 R2), as `PartnerAccessResource` answers it — never with the password. */
const PARTNER_ACCESS_FIXTURE: PartnerAccess = {
  active: true,
  email: 'portal3@parceiro.exemplo',
  granted_at: '2026-11-09T13:00:00+00:00',
  partner_id: 3,
  revoked_at: null,
  user_id: 21,
  username: 'parceiro.3',
};

/**
 * The network's month under comissão (rnc-2 A9, `NetworkStatementResource`): NexoERP closed R$ 12.000
 * and R$ 24.000 (R$ 3.600 owed), Giro Capital nothing; both had access, so assinatura would be 2 × R$ 490.
 */
const NETWORK_LEDGER_FIXTURE: NetworkLedger = {
  commission_rate_bp: 1000,
  deal_total_cents: 3_600_000,
  deals: 2,
  model: LEDGER_MODEL.COMISSAO,
  month: '2026-11',
  monthly_fee_cents: 49_000,
  other: { model: LEDGER_MODEL.ASSINATURA, total_cents: 98_000 },
  partners: [
    {
      commission_cents: 0,
      deal_total_cents: 0,
      deals: 0,
      due_cents: 0,
      partner: { active: true, id: 4, name: 'Giro Capital (exemplo)', specialty: 'credito_capital_giro' },
      subscribed: true,
      subscription_cents: 49_000,
    },
    {
      commission_cents: 360_000,
      deal_total_cents: 3_600_000,
      deals: 2,
      due_cents: 360_000,
      partner: { active: true, id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
      subscribed: true,
      subscription_cents: 49_000,
    },
  ],
  total_cents: 360_000,
};

/** The same month once the network moved to assinatura: every partner with access owes the fee. */
const NETWORK_LEDGER_SUBSCRIPTION_FIXTURE: NetworkLedger = {
  ...NETWORK_LEDGER_FIXTURE,
  model: LEDGER_MODEL.ASSINATURA,
  other: { model: LEDGER_MODEL.COMISSAO, total_cents: 360_000 },
  partners: NETWORK_LEDGER_FIXTURE.partners.map((row) => ({ ...row, due_cents: row.subscription_cents })),
  total_cents: 98_000,
};

export {
  MATCHES_FIXTURE,
  NETWORK_LEDGER_FIXTURE,
  NETWORK_LEDGER_SUBSCRIPTION_FIXTURE,
  PARTNER_ACCESS_FIXTURE,
  PARTNER_FIXTURE,
  REFERRAL_FIXTURE,
};
