import type { AgendaWeek, LeadBoard, LeadCard, LeadDetail, LeadMove } from '../interfaces';
import { LEAD_STAGE } from '../enums';

/** A lead that has seen its result — the `lead` column's only card. */
const LEAD_CARD_FIXTURE: LeadCard = {
  brand: null,
  channel: 'formulario',
  company: 'Padaria Trigo Dourado',
  contact_name: 'Ana Almeida',
  created_at: '2026-11-10T09:12:00-03:00',
  has_contact: true,
  id: 7,
  last_step: 'resultado',
  lost_reason: null,
  meeting: null,
  opportunities: 3,
  risks: 2,
  sector: 'comercio',
  source: 'organico',
  stage: LEAD_STAGE.LEAD,
  updated_at: '2026-11-12T10:00:00-03:00',
};

const PARTIAL_CARD_FIXTURE: LeadCard = {
  ...LEAD_CARD_FIXTURE,
  company: 'Clínica Vida Plena',
  id: 8,
  last_step: 'perfil',
  opportunities: 0,
  risks: 0,
  sector: 'saude',
  stage: LEAD_STAGE.LEAD_PARCIAL,
};

const BOARD_FIXTURE: LeadBoard = {
  columns: [
    { count: 0, items: [], stage: LEAD_STAGE.VISITANTE },
    { count: 1, items: [PARTIAL_CARD_FIXTURE], stage: LEAD_STAGE.LEAD_PARCIAL },
    { count: 1, items: [LEAD_CARD_FIXTURE], stage: LEAD_STAGE.LEAD },
    { count: 0, items: [], stage: LEAD_STAGE.REUNIAO_MARCADA },
    { count: 0, items: [], stage: LEAD_STAGE.PROPOSTA },
    { count: 0, items: [], stage: LEAD_STAGE.CLIENTE },
    { count: 0, items: [], stage: LEAD_STAGE.PERDIDO },
  ],
  brands: [
    { active: true, short_name: 'Franco Express', slug: 'express' },
    { active: false, short_name: 'Franco Prime', slug: 'prime' },
  ],
};

/** What the API answers to a move of `LEAD_CARD_FIXTURE` to `stage`. */
function moveFixture(stage: LEAD_STAGE, via: string, reason?: string): LeadMove {
  return {
    event: {
      at: '2026-11-19T10:00:00-03:00',
      data: { from: LEAD_STAGE.LEAD, to: stage, via, ...(reason ? { reason } : {}) },
      id: 99,
      type: 'etapa.alterada',
      user: { id: 3, name: 'Patrícia Gomes' },
    },
    lead: { ...LEAD_CARD_FIXTURE, lost_reason: reason ?? null, stage },
  };
}

const LEAD_DETAIL_FIXTURE: LeadDetail = {
  brand: null,
  channel: 'formulario',
  cnpj: '90001371000159',
  company: 'Padaria Trigo Dourado',
  consent: {
    given: true,
    given_at: '2026-11-10T09:14:00-03:00',
    text: 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.',
    version: 1,
    via: 'formulario',
  },
  contact_name: 'Ana Almeida',
  created_at: '2026-11-10T09:12:00-03:00',
  diagnosis: {
    items: [
      {
        detail: 'No Simples, a venda para empresa gera pouco crédito ao cliente.',
        id: 'simples-regular',
        kind: 'oportunidade',
        range_label: 'R$ 24 mil a R$ 72 mil',
        range_max: 72000,
        range_min: 24000,
        rule: 'LC 214/2025, opção pelo regime regular',
        title: 'Avaliar o IBS/CBS por fora do Simples',
      },
    ],
    opportunities: 1,
    risks: 0,
  },
  email: 'ana@padaria-trigo-dourado.exemplo.com.br',
  id: 7,
  last_step: 'resultado',
  lost_reason: null,
  medium: null,
  meeting: {
    area: 'Contábil · Simples Nacional',
    ends_at: '2026-11-24T15:00:00-03:00',
    id: 4,
    specialist: 'Juliana Reis',
    starts_at: '2026-11-24T14:30:00-03:00',
  },
  notes: [],
  path: 'cnpj',
  phone: '5511900000001',
  profile: { b2b: 80, band: 'b2', exporta: false, incentivos: false, regime: 'simples', sector: 'comercio' },
  source: 'organico',
  stage: LEAD_STAGE.LEAD,
  timeline: [
    {
      at: '2026-11-12T10:00:00-03:00',
      data: { from: 'lead_parcial', to: 'lead', via: 'arrastar' },
      id: 3,
      type: 'etapa.alterada',
      user: { id: 3, name: 'Patrícia Gomes' },
    },
    { at: '2026-11-10T09:12:00-03:00', data: { path: 'cnpj' }, id: 1, type: 'diagnostico.inicio', user: null },
  ],
  updated_at: '2026-11-12T10:00:00-03:00',
};

const AGENDA_FIXTURE: AgendaWeek = {
  days: [
    {
      date: '2026-11-16',
      meetings: [
        {
          area: 'Tributário · Reforma',
          company: 'Oficina Motor Certo',
          ends_at: '2026-11-16T09:30:00-03:00',
          id: 1,
          lead_id: 11,
          specialist: 'Mariana Toledo',
          stage: LEAD_STAGE.REUNIAO_MARCADA,
          starts_at: '2026-11-16T09:00:00-03:00',
        },
      ],
    },
    { date: '2026-11-17', meetings: [] },
    { date: '2026-11-18', meetings: [] },
    { date: '2026-11-19', meetings: [] },
    { date: '2026-11-20', meetings: [] },
  ],
  next_week: '2026-11-23',
  previous_week: '2026-11-09',
  week_end: '2026-11-20',
  week_start: '2026-11-16',
};

export { AGENDA_FIXTURE, BOARD_FIXTURE, LEAD_CARD_FIXTURE, LEAD_DETAIL_FIXTURE, moveFixture, PARTIAL_CARD_FIXTURE };
