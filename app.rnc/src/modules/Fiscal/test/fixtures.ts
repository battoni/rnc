import type {
  ClientEvent,
  NfeImport,
  ReadinessClient,
  ReadinessDetail,
  ReadinessList,
  SupplierCreditClient,
  SupplierCreditList,
} from '../interfaces';
import { CLIENT_REGIME, CLIENT_SECTOR } from '@RepricingModule';
import { CLIENT_EVENT_TYPE, NFE_IMPORT_SOURCE, NFE_REFUSAL, READINESS_ISSUE, READINESS_STATUS } from '../enums';

/* What the API answers for the fiscal screens (rnc-2 F7), as the specs need it. Money in cents. */
const METALURGICA: ReadinessClient = {
  at_risk: 3,
  cnpj: '99800001000191',
  complete: 28,
  complete_pct: 70,
  id: 7,
  invoices: 40,
  name: 'Metalúrgica Boa Vista Ltda',
  regime: CLIENT_REGIME.REAL,
  sector: CLIENT_SECTOR.INDUSTRIA,
  status: READINESS_STATUS.CRITICO,
  system: 'ERP Delta 4.2',
};

const PADARIA: ReadinessClient = {
  at_risk: 0,
  cnpj: '99800002000172',
  complete: 29,
  complete_pct: 96.7,
  id: 8,
  invoices: 30,
  name: 'Padaria Estrela Ltda',
  regime: CLIENT_REGIME.PRESUMIDO,
  sector: CLIENT_SECTOR.COMERCIO,
  status: READINESS_STATUS.OK,
  system: 'Emissor Nuvem 2.0',
};

const SEM_NOTAS: ReadinessClient = {
  at_risk: 0,
  cnpj: '99800003000153',
  complete: 0,
  complete_pct: null,
  id: 9,
  invoices: 0,
  name: 'Clínica Horizonte Ltda',
  regime: CLIENT_REGIME.PRESUMIDO,
  sector: CLIENT_SECTOR.SAUDE,
  status: READINESS_STATUS.SEM_NOTAS,
  system: null,
};

const READINESS_FIXTURE: ReadinessList = {
  clients: [METALURGICA, PADARIA, SEM_NOTAS],
  summary: {
    at_risk: 3,
    by_status: { atencao: 0, critico: 1, ok: 1, sem_notas: 1 },
    clients: 3,
    complete: 57,
    complete_pct: 81.4,
    invoices: 70,
  },
  systems: ['ERP Delta 4.2', 'Emissor Nuvem 2.0'],
};

/** The API filtered on `status=critico`: the hero stays over every client (W76). */
const READINESS_CRITICO_FIXTURE: ReadinessList = { ...READINESS_FIXTURE, clients: [METALURGICA] };

const METALURGICA_DETAIL: ReadinessDetail = {
  ...METALURGICA,
  issues: [
    {
      code: READINESS_ISSUE.MISSING_GROUP,
      invoices: 3,
      risk: true,
      samples: [
        { item: 2, number: 1042, series: 1 },
        { item: 1, number: 1038, series: 1 },
      ],
    },
    { code: READINESS_ISSUE.MISSING_CODES, invoices: 6, risk: false, samples: [{ item: 4, number: 1040, series: 1 }] },
    {
      code: READINESS_ISSUE.WITHOUT_TEST_VALUES,
      invoices: 3,
      risk: false,
      samples: [{ item: null, number: 1031, series: 2 }],
    },
  ],
  window_days: 30,
};

const SEM_NOTAS_DETAIL: ReadinessDetail = { ...SEM_NOTAS, issues: [], window_days: 30 };

const NOTICE_EVENT: ClientEvent = {
  at: '2026-11-09T16:00:00+00:00',
  client_id: 7,
  data: { at_risk: 3, complete_pct: 70, invoices: 40, status: 'critico' },
  id: 51,
  type: CLIENT_EVENT_TYPE.NFE_NOTICE,
  user: { id: 4, name: 'Patrícia Lima' },
};

const REVIEW_EVENT: ClientEvent = {
  at: '2026-11-08T13:30:00+00:00',
  client_id: 21,
  data: { lost_per_year_cents: [7_740_000, 8_460_000], simples_share: 30 },
  id: 52,
  type: CLIENT_EVENT_TYPE.SUPPLIER_REVIEW,
  user: { id: 2, name: 'Reinaldo Franco' },
};

const ATACADO: SupplierCreditClient = {
  cnpj: '99800021000110',
  documents: 36,
  id: 21,
  lost_per_year_cents: [7_740_000, 8_460_000],
  monthly_purchases_cents: 10_000_000,
  name: 'Atacado Serra Azul Ltda',
  normal_share: 70,
  proposed: false,
  regime: CLIENT_REGIME.REAL,
  sector: CLIENT_SECTOR.COMERCIO,
  simples_share: 30,
};

const LABORATORIO: SupplierCreditClient = {
  cnpj: '99800022000190',
  documents: 12,
  id: 22,
  lost_per_year_cents: [0, 0],
  monthly_purchases_cents: 4_000_000,
  name: 'Laboratório Vida Ltda',
  normal_share: 100,
  proposed: false,
  regime: CLIENT_REGIME.PRESUMIDO,
  sector: CLIENT_SECTOR.SAUDE,
  simples_share: 0,
};

const SUPPLIER_FIXTURE: SupplierCreditList = {
  clients: [ATACADO, LABORATORIO],
  summary: {
    buying_from_simples: 1,
    clients: 2,
    lost_per_year_cents: [7_740_000, 8_460_000],
    mean_simples_share: 30,
    proposed: 0,
  },
};

/** The API filtered on `sector=saude`: the KPIs follow the filter (W76). */
const SUPPLIER_SAUDE_FIXTURE: SupplierCreditList = {
  clients: [LABORATORIO],
  summary: { buying_from_simples: 0, clients: 1, lost_per_year_cents: [0, 0], mean_simples_share: 0, proposed: 0 },
};

const UPLOAD_BATCH: NfeImport = {
  at: '2026-11-09T17:05:00+00:00',
  by_name: 'Reinaldo Franco',
  files: 4,
  id: 41,
  refused: [
    { code: NFE_REFUSAL.NOT_XML, file: 'leia-me.xml' },
    { code: NFE_REFUSAL.CLIENT_UNKNOWN, file: '35261199800099000100550010000001231000001234.xml' },
  ],
  source: NFE_IMPORT_SOURCE.UPLOAD,
  stored: 2,
  user_id: 2,
};

const FOLDER_BATCH: NfeImport = {
  at: '2026-11-08T11:00:00+00:00',
  by_name: null,
  files: 62,
  id: 40,
  refused: [],
  source: NFE_IMPORT_SOURCE.FOLDER,
  stored: 62,
  user_id: null,
};

export {
  ATACADO,
  FOLDER_BATCH,
  LABORATORIO,
  METALURGICA,
  METALURGICA_DETAIL,
  NOTICE_EVENT,
  PADARIA,
  READINESS_CRITICO_FIXTURE,
  READINESS_FIXTURE,
  REVIEW_EVENT,
  SEM_NOTAS,
  SEM_NOTAS_DETAIL,
  SUPPLIER_FIXTURE,
  SUPPLIER_SAUDE_FIXTURE,
  UPLOAD_BATCH,
};
