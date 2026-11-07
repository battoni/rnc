import type { RadarClient, RadarPartnerOption, RadarSummary, RadarTypeDetail, RadarTypeRow } from '../interfaces';
import { PARTNER_SPECIALTY } from '@PartnerModule';
import { CLIENT_REGIME, CLIENT_SECTOR } from '@RepricingModule';
import { RADAR_EVIDENCE_SOURCE, RADAR_KIND, RADAR_PERIOD } from '../enums';

/* What the API answers for the Radar (rnc-2 F9–F10), as the specs need it. Money in cents. */
const MARGEM: RadarPartnerOption = {
  batch: 10,
  id: 7,
  name: 'Margem Certa (exemplo)',
  sent: 0,
  specialty: PARTNER_SPECIALTY.PRECIFICACAO_CONTROLADORIA,
};

const MARGEM_DOIS: RadarPartnerOption = {
  batch: 4,
  id: 9,
  name: 'Margem Dois (exemplo)',
  sent: 6,
  specialty: PARTNER_SPECIALTY.PRECIFICACAO_CONTROLADORIA,
};

const CONTRATOS: RadarTypeRow = {
  citation: 'LC 214/2025, art. 378',
  clients: 14,
  kind: RADAR_KIND.OPORTUNIDADE,
  partners: [MARGEM, MARGEM_DOIS],
  period: RADAR_PERIOD.ANO,
  range_cents: [12_000_000, 36_000_000],
  rule: 'contratos',
  title: 'Revisão de contratos longos',
  without_consent: 2,
};

const UNCOVERED: RadarTypeRow = {
  ...CONTRATOS,
  clients: 3,
  partners: [],
  range_cents: [1_000_000, 2_000_000],
  rule: 'servicos-30',
  title: 'Redução de 30% para serviços',
  without_consent: 0,
};

const SPLIT: RadarTypeRow = {
  citation: 'LC 214/2025, arts. 31–35',
  clients: 2,
  kind: RADAR_KIND.RISCO,
  partners: [
    { batch: 0, id: 4, name: 'Giro Capital (exemplo)', sent: 2, specialty: PARTNER_SPECIALTY.CREDITO_CAPITAL_GIRO },
  ],
  period: RADAR_PERIOD.MES,
  range_cents: [1_060_000, 1_590_000],
  rule: 'split-payment',
  title: 'Split payment no caixa',
  without_consent: 0,
};

const RADAR_FIXTURE: RadarSummary = {
  clients: 500,
  risk_types: 1,
  total_cents: [13_000_000, 38_000_000],
  types: [CONTRATOS, UNCOVERED, SPLIT],
  with_opportunity: 480,
};

const FROM_NOTES: RadarClient = {
  b2b_share: 20,
  band: 'b2',
  cnpj: '99800001000191',
  evidence: {
    b2b_share: 80,
    declared_b2b_share: 20,
    documents: 12,
    source: RADAR_EVIDENCE_SOURCE.NOTAS,
    window_days: 90,
  },
  id: 31,
  keys: ['35261199800001000191550010000010421000010420', '35261199800001000191550010000010401000010406'],
  name: 'A Medida Ltda',
  partner_consent: true,
  range_cents: [1_800_000, 5_400_000],
  regime: CLIENT_REGIME.SIMPLES,
  sector: CLIENT_SECTOR.SERVICOS,
};

const FROM_CADASTRO: RadarClient = {
  ...FROM_NOTES,
  evidence: { source: RADAR_EVIDENCE_SOURCE.CADASTRO },
  id: 32,
  keys: [],
  name: 'B Poucas Ltda',
  partner_consent: null,
  range_cents: [900_000, 2_700_000],
};

const CONTRATOS_DETAIL: RadarTypeDetail = {
  clients: [FROM_NOTES, FROM_CADASTRO],
  type: { ...CONTRATOS, detail: 'Contratos de longo prazo assinados antes da Reforma pedem revisão de preço.' },
};

const SPLIT_DETAIL: RadarTypeDetail = {
  clients: [
    {
      ...FROM_NOTES,
      evidence: {
        held_per_month_cents: [1_060_000, 1_590_000],
        monthly_b2b_cents: 10_000_000,
        source: RADAR_EVIDENCE_SOURCE.NOTAS,
        window_days: 90,
      },
      id: 41,
      name: 'C Presumida Ltda',
      range_cents: [1_060_000, 1_590_000],
      regime: CLIENT_REGIME.PRESUMIDO,
    },
  ],
  type: { ...SPLIT, detail: 'O imposto da venda B2B fica retido na liquidação.' },
};

export { CONTRATOS, CONTRATOS_DETAIL, MARGEM, MARGEM_DOIS, RADAR_FIXTURE, SPLIT, SPLIT_DETAIL };
