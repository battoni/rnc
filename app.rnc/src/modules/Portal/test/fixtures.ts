import type { PortalMe, PortalReferral } from '../interfaces';
import { REFERRAL_STATUS, SLA_STATE } from '../enums';

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

export { ACCEPTED_REFERRAL_FIXTURE, NEW_REFERRAL_FIXTURE, PORTAL_ME_FIXTURE, referralAt };
