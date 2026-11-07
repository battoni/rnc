import type {
  Availability,
  BookingConfirmation,
  CnpjCompany,
  DiagnosticResult,
  DiagnosticSimulation,
  PublicLead,
  PublicReport,
} from '../interfaces';
import { DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, RESULT_KIND } from '../enums';

/* The API's answers as the Diagnostic specs need them — the shapes of the `Public*` resources. */
const CONSENT_TEXT =
  'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

const TOKEN_FIXTURE = 'k'.repeat(48);

const LEAD_FIXTURE: PublicLead = {
  cnpj: null,
  company: null,
  consent: { text: CONSENT_TEXT, version: 1 },
  has_contact: false,
  has_email: false,
  last_step: DIAGNOSTIC_STEP.COMPANY,
  path: DIAGNOSTIC_PATH.QUIZ,
  profile: { b2b: null, band: null, exporta: false, incentivos: false, regime: null, sector: null },
  stage: 'visitante',
  token: TOKEN_FIXTURE,
};

const COMPANY_FIXTURE: CnpjCompany = {
  band: 'b2',
  cnae: '4711302',
  cnae_description: 'Comércio varejista de mercadorias em geral',
  name: 'MERCADO BOA VISTA LTDA',
  porte: 'EMPRESA DE PEQUENO PORTE',
  regime: 'simples',
  sector: 'comercio',
};

/* "Simples, B2B 80 %, serviços" on band b2 — the A7 profile. */
const RESULT_FIXTURE: DiagnosticResult = {
  company: 'Clínica Horizonte',
  items: [
    {
      kind: RESULT_KIND.OPPORTUNITY,
      range_label: 'R$ 18–54 mil/ano',
      range_max: 54000,
      range_min: 18000,
      title: 'Opção pelo regime regular do IBS/CBS',
    },
    {
      kind: RESULT_KIND.RISK,
      range_label: 'R$ 36–90 mil/ano',
      range_max: 90000,
      range_min: 36000,
      title: 'Clientes PJ podem preferir fornecedores que geram crédito',
    },
    {
      kind: RESULT_KIND.OPPORTUNITY,
      range_label: 'R$ 18–45 mil/ano',
      range_max: 45000,
      range_min: 18000,
      title: 'Redução de 30% para profissão regulamentada',
    },
    {
      kind: RESULT_KIND.OPPORTUNITY,
      range_label: 'R$ 9–27 mil/ano',
      range_max: 27000,
      range_min: 9000,
      title: 'Revisar contratos e preços antes de 2027',
    },
    {
      kind: RESULT_KIND.RISK,
      range_label: 'R$ 14–36 mil/ano',
      range_max: 36000,
      range_min: 14400,
      title: 'Fluxo de caixa exposto ao split payment',
    },
  ],
  opportunities: 3,
  risks: 2,
};

const YEARS = [2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033];
const LEGACY_SHARE = [1, 1, 1, 0.9, 0.8, 0.7, 0.6, 0];

const SIMULATION_FIXTURE: DiagnosticSimulation = {
  estimate: true,
  split_payment: { label: 'R$ 16–28 mil/mês', max: 27825, min: 15900 },
  taxes: [
    { label: 'PIS/Cofins', leaving: true, tax: 'pis-cofins' },
    { label: 'ICMS', leaving: true, tax: 'icms' },
    { label: 'ISS', leaving: true, tax: 'iss' },
    { label: 'CBS', leaving: false, tax: 'cbs' },
    { label: 'IBS', leaving: false, tax: 'ibs' },
  ],
  years: YEARS.map((year, index) => {
    const iss = Math.round(5 * LEGACY_SHARE[index]! * 10) / 10;
    const parts = [
      ...(year === 2026 ? [{ label: 'PIS/Cofins', rate: 3.7, tax: 'pis-cofins' }] : []),
      ...(iss > 0 ? [{ label: 'ISS', rate: iss, tax: 'iss' }] : []),
      { label: 'CBS', rate: year === 2026 ? 0.9 : 11.3, tax: 'cbs' },
      { label: 'IBS', rate: year <= 2028 ? 0.1 : Math.round(15.2 * (1 - LEGACY_SHARE[index]!) * 10) / 10, tax: 'ibs' },
    ];

    return { parts, total: Math.round(parts.reduce((sum, part) => sum + part.rate, 0) * 10) / 10, year };
  }),
};

/* Two specialists over three working days (the API sends ten); Juliana is indicated (Simples). */
const AVAILABILITY_FIXTURE: Availability = {
  slot_minutes: 30,
  specialists: [
    {
      area: 'Tributário · Reforma',
      days: [
        { date: '2026-11-23', slots: ['2026-11-23T08:00:00-03:00', '2026-11-23T08:30:00-03:00'], weekday: 1 },
        { date: '2026-11-24', slots: [], weekday: 2 },
        { date: '2026-11-25', slots: ['2026-11-25T14:00:00-03:00'], weekday: 3 },
      ],
      id: 1,
      name: 'Mariana Toledo',
      suggested: false,
    },
    {
      area: 'Contábil · Simples Nacional',
      days: [
        { date: '2026-11-23', slots: [], weekday: 1 },
        {
          date: '2026-11-24',
          slots: ['2026-11-24T09:00:00-03:00', '2026-11-24T14:30:00-03:00', '2026-11-24T15:00:00-03:00'],
          weekday: 2,
        },
        { date: '2026-11-25', slots: ['2026-11-25T10:00:00-03:00'], weekday: 3 },
      ],
      id: 3,
      name: 'Juliana Reis',
      suggested: true,
    },
  ],
  suggested_specialist_id: 3,
  timezone: 'America/Sao_Paulo',
};

const REPORT_TOKEN_FIXTURE = 'r'.repeat(48);

const MEETING_FIXTURE = {
  area: 'Contábil · Simples Nacional',
  ends_at: '2026-11-24T15:00:00-03:00',
  specialist: 'Juliana Reis',
  starts_at: '2026-11-24T14:30:00-03:00',
};

const BOOKING_FIXTURE: BookingConfirmation = { meeting: MEETING_FIXTURE, report_token: REPORT_TOKEN_FIXTURE };

const REPORT_FIXTURE: PublicReport = {
  company: 'Clínica Horizonte',
  meeting: MEETING_FIXTURE,
  profile: { b2b: 80, band: 'b2', regime: 'simples', sector: 'servicos' },
  result: RESULT_FIXTURE,
  simulation: SIMULATION_FIXTURE,
};

export {
  AVAILABILITY_FIXTURE,
  BOOKING_FIXTURE,
  COMPANY_FIXTURE,
  CONSENT_TEXT,
  LEAD_FIXTURE,
  REPORT_FIXTURE,
  REPORT_TOKEN_FIXTURE,
  RESULT_FIXTURE,
  SIMULATION_FIXTURE,
  TOKEN_FIXTURE,
};
