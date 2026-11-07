import type { RepricingClient, RepricingClientDetail, RepricingLetter, RepricingList } from '../interfaces';
import { CLIENT_REGIME, CLIENT_SECTOR, REPRICING_ITEM_KIND } from '../enums';

/* What the API answers for the repricing (rnc-1 F8), as the specs need it. Money in cents. */
const ATLAS: RepricingClient = {
  b2b_share: 80,
  band: 'b2',
  cnpj: '99900001999901',
  current_cents: 200_000,
  id: 1,
  name: 'Consultoria Aurora Ltda',
  opportunities: 3,
  pct: 10,
  regime: CLIENT_REGIME.SIMPLES,
  repricing: null,
  risks: 1,
  sector: CLIENT_SECTOR.SERVICOS,
  suggested_cents: 220_000,
  uplift_cents: 240_000,
};

const EMPORIO: RepricingClient = {
  ...ATLAS,
  b2b_share: 10,
  cnpj: '99900002999902',
  current_cents: 90_000,
  id: 2,
  name: 'Empório Cedral Ltda',
  pct: 6,
  repricing: { at: '2026-11-18T13:00:00+00:00', by: 2, by_name: 'Reinaldo Franco', pct: 6, suggested_cents: 95_000 },
  sector: CLIENT_SECTOR.COMERCIO,
  suggested_cents: 95_000,
  uplift_cents: 60_000,
};

const LIST_FIXTURE: RepricingList = {
  clients: [ATLAS, EMPORIO],
  spread: [
    { count: 1, from: 4, to: 8 },
    { count: 1, from: 9, to: 13 },
    { count: 0, from: 14, to: 19 },
    { count: 0, from: 20, to: 25 },
  ],
  summary: {
    average_pct: 8,
    clients: 2,
    current_yearly_cents: 3_480_000,
    marked: 1,
    uplift_share: 8.6,
    uplift_total_cents: 300_000,
  },
};

const COMERCIO_FIXTURE: RepricingList = {
  clients: [EMPORIO],
  spread: [
    { count: 1, from: 4, to: 8 },
    { count: 0, from: 9, to: 13 },
    { count: 0, from: 14, to: 19 },
    { count: 0, from: 20, to: 25 },
  ],
  summary: {
    ...LIST_FIXTURE.summary,
    average_pct: 6,
    clients: 1,
    current_yearly_cents: 1_080_000,
    uplift_total_cents: 60_000,
  },
};

const DETAIL_FIXTURE: RepricingClientDetail = {
  ...ATLAS,
  items: [
    {
      detail: 'O cliente B2B passa a olhar o crédito.',
      id: 'simples-credito',
      kind: REPRICING_ITEM_KIND.OPORTUNIDADE,
      rule: 'LC 214/2025',
      title: 'Crédito do Simples',
    },
    {
      detail: 'O imposto é retido na liquidação.',
      id: 'split-payment',
      kind: REPRICING_ITEM_KIND.RISCO,
      rule: 'LC 214/2025, art. 31',
      title: 'Split payment no caixa',
    },
  ],
};

const LETTER_FIXTURE: RepricingLetter = {
  client: { cnpj: '99900001999901', id: 1, name: 'Consultoria Aurora Ltda' },
  current_cents: 200_000,
  effective_from: '2027-01-01',
  firm: {
    address: 'Rua do Exemplo, 100 · São Paulo-SP',
    mailbox: 'contato@franco.exemplo',
    name: 'Franco Contabilidade',
    phone: '(11) 3000-0000',
  },
  issued_on: '2026-11-19',
  items: [
    { kind: REPRICING_ITEM_KIND.OPORTUNIDADE, title: 'Crédito do Simples' },
    { kind: REPRICING_ITEM_KIND.RISCO, title: 'Split payment no caixa' },
  ],
  pct: 10,
  suggested_cents: 220_000,
};

export { ATLAS, COMERCIO_FIXTURE, DETAIL_FIXTURE, EMPORIO, LETTER_FIXTURE, LIST_FIXTURE };
