import type { CLIENT_EVENT_TYPE, NFE_IMPORT_SOURCE, NFE_REFUSAL, READINESS_ISSUE, READINESS_STATUS } from './enums';
import type { CLIENT_REGIME, CLIENT_SECTOR } from '@RepricingModule';

/** One client of "Prontidão da NF-e" (`ReadinessClientResource`, R18). `complete_pct` is null with no invoice. */
interface ReadinessClient {
  at_risk: number;
  cnpj: string;
  complete: number;
  complete_pct: number | null;
  id: number;
  invoices: number;
  name: string;
  regime: CLIENT_REGIME;
  sector: CLIENT_SECTOR;
  status: READINESS_STATUS;
  /** The most frequent `ide/verProc` of its invoices (the demo's ERP), null when none says. */
  system: string | null;
}

/** The hero over EVERY listed client, whatever the filter (W76). */
interface ReadinessSummary {
  at_risk: number;
  by_status: Record<READINESS_STATUS, number>;
  clients: number;
  complete: number;
  complete_pct: number | null;
  invoices: number;
}

/** `GET fiscal/readiness` (R18–R19): the hero, the systems the filter offers and the listed clients. */
interface ReadinessList {
  clients: ReadinessClient[];
  summary: ReadinessSummary;
  systems: string[];
}

interface ReadinessFilters {
  status: READINESS_STATUS | null;
  system: string | null;
}

/** An invoice that shows an issue: its number, series and the first failing item. */
interface ReadinessSample {
  item: number | null;
  number: number;
  series: number;
}

/** One issue line of a client (R19): how many invoices, whether it is the 1115 risk, up to five samples. */
interface ReadinessIssueLine {
  code: READINESS_ISSUE;
  invoices: number;
  risk: boolean;
  samples: ReadinessSample[];
}

/** `GET fiscal/readiness/{id}` (`ReadinessDetailResource`). */
interface ReadinessDetail extends ReadinessClient {
  issues: ReadinessIssueLine[];
  window_days: number;
}

/** One line of a client's timeline (Portfolio `ClientEventResource`): the SPA writes the sentence. */
interface ClientEvent {
  at: string;
  client_id: number;
  data: Record<string, unknown>;
  id: number;
  type: CLIENT_EVENT_TYPE | string;
  user: { id: number; name: string } | null;
}

/** A money range in cents, `[low, high]` (P5). */
type CentsRange = [number, number];

/** One client of "Crédito de fornecedores" (`SupplierCreditClientResource`, R21). Shares in %, one decimal. */
interface SupplierCreditClient {
  cnpj: string;
  documents: number;
  id: number;
  lost_per_year_cents: CentsRange;
  monthly_purchases_cents: number;
  name: string;
  normal_share: number;
  proposed: boolean;
  regime: CLIENT_REGIME;
  sector: CLIENT_SECTOR;
  simples_share: number;
}

/** The KPIs over the listed clients — they follow the sector filter (W76). */
interface SupplierCreditSummary {
  buying_from_simples: number;
  clients: number;
  lost_per_year_cents: CentsRange;
  mean_simples_share: number;
  proposed: number;
}

/** `GET fiscal/supplier-credit` (R21–R22), sorted by the top of the range. */
interface SupplierCreditList {
  clients: SupplierCreditClient[];
  summary: SupplierCreditSummary;
}

/** A file of a batch that was not stored, and why (R17). */
interface NfeRefusalLine {
  code: NFE_REFUSAL;
  file: string;
}

/** One import batch (`NfeImportResource`, R17): who, how, when, files read, documents stored, refusals. */
interface NfeImport {
  at: string;
  by_name: string | null;
  files: number;
  id: number;
  refused: NfeRefusalLine[];
  source: NFE_IMPORT_SOURCE;
  stored: number;
  user_id: number | null;
}

export type {
  CentsRange,
  ClientEvent,
  NfeImport,
  NfeRefusalLine,
  ReadinessClient,
  ReadinessDetail,
  ReadinessFilters,
  ReadinessIssueLine,
  ReadinessList,
  ReadinessSample,
  ReadinessSummary,
  SupplierCreditClient,
  SupplierCreditList,
  SupplierCreditSummary,
};
