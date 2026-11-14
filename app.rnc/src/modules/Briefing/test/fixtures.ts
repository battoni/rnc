import type { Briefing } from '../interfaces';
import { SLA_STATE } from '@PortalModule';
import { BRIEFING_ACTION_KIND, BRIEFING_RADAR_PERIOD } from '../enums';

/* What `GET briefing` answers (rnc-3 F8, api Briefing RULES → Endpoints), as the specs need it. Money in cents. */
const BRIEFING_FIXTURE: Briefing = {
  actions: [
    {
      kind: BRIEFING_ACTION_KIND.SLA_BREACHED,
      params: { company: 'Padaria Trigo Dourado', goal_hours: 24, hours: 31.5, partner: 'Margem Certa (exemplo)' },
      to: '/gestao/trilha?parceiro=7',
    },
    { kind: BRIEFING_ACTION_KIND.MEETINGS_TODAY, params: { count: 2 }, to: '/equipe/agenda' },
    {
      kind: BRIEFING_ACTION_KIND.PROPOSALS,
      params: { count: 3, range_cents: [5_400_000, 9_000_000] },
      to: '/equipe/pipeline?etapa=proposta',
    },
  ],
  as_of: '2026-11-09T09:15:00-03:00',
  leads: { last_24_hours: 2, last_7_days: 14 },
  meetings: {
    count: 6,
    next: [
      {
        company: 'Centro Médico Bandeira Ltda',
        lead_id: 11,
        specialist: 'Mariana',
        starts_at: '2026-11-09T10:00:00-03:00',
      },
      { company: null, lead_id: 12, specialist: 'Carlos', starts_at: '2026-11-09T15:30:00-03:00' },
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
      {
        company: 'Padaria Trigo Dourado',
        hours: 31.5,
        id: 40,
        lead_id: 2,
        partner: { id: 7, name: 'Margem Certa (exemplo)' },
        state: SLA_STATE.ESTOURADO,
      },
      {
        company: 'Atacado Serra Azul Ltda',
        hours: 26,
        id: 41,
        lead_id: 3,
        partner: { id: 1, name: 'Andrade Tributário (exemplo)' },
        state: SLA_STATE.ESTOURADO,
      },
      {
        company: 'Oficina Boa Vista',
        hours: 14,
        id: 42,
        lead_id: 4,
        partner: { id: 7, name: 'Margem Certa (exemplo)' },
        state: SLA_STATE.ATENCAO,
      },
      {
        company: 'Loja Pinheiros',
        hours: 3,
        id: 43,
        lead_id: 5,
        partner: { id: 1, name: 'Andrade Tributário (exemplo)' },
        state: SLA_STATE.OK,
      },
      {
        company: 'Clínica Sol',
        hours: 1,
        id: 44,
        lead_id: 6,
        partner: { id: 1, name: 'Andrade Tributário (exemplo)' },
        state: SLA_STATE.OK,
      },
    ],
  },
};

/** The two kinds the fixture above leaves out, for the sentences' spec. */
const PARTIAL_LEADS_ACTION: Briefing['actions'][number] = {
  kind: BRIEFING_ACTION_KIND.PARTIAL_LEADS,
  params: { count: 1 },
  to: '/equipe/pipeline?etapa=lead_parcial',
};

const RADAR_ACTION: Briefing['actions'][number] = {
  kind: BRIEFING_ACTION_KIND.RADAR,
  params: {
    clients: 8,
    period: BRIEFING_RADAR_PERIOD.MONTH,
    range_cents: [4_000_000, 6_500_000],
    rule: 'split-payment',
    title: 'Split payment no caixa',
  },
  to: '/gestao/radar',
};

/** A quiet day on an empty portfolio: nothing applies, no range anywhere. */
const QUIET_BRIEFING: Briefing = {
  ...BRIEFING_FIXTURE,
  actions: [],
  meetings: { count: 0, next: [], today: 0, window_end: '2026-11-13' },
  pipeline: {
    count: 0,
    range_cents: null,
    stages: [
      { count: 0, range_cents: null, stage: 'lead' },
      { count: 0, range_cents: null, stage: 'reuniao_marcada' },
      { count: 0, range_cents: null, stage: 'proposta' },
    ],
  },
  referrals: { by_state: { atencao: 0, estourado: 0, ok: 0 }, count: 0, goal_hours: 24, more: 0, top: [] },
};

export { BRIEFING_FIXTURE, PARTIAL_LEADS_ACTION, QUIET_BRIEFING, RADAR_ACTION };
