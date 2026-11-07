import type { CLIENT_REGIME, CLIENT_SECTOR, REPRICING_ITEM_KIND } from './enums';

/** The batch mark stored on a client (R26): when, by whom, and the quote at that moment. */
interface RepricingMark {
  at: string;
  by: number;
  by_name: string | null;
  pct: number;
  suggested_cents: number;
}

/** A row of `GET /api/repricing` (`RepricingClientResource`). Money in integer cents. */
interface RepricingClient {
  b2b_share: number;
  band: string;
  cnpj: string;
  current_cents: number;
  id: number;
  name: string;
  opportunities: number;
  pct: number;
  regime: CLIENT_REGIME;
  repricing: RepricingMark | null;
  risks: number;
  sector: CLIENT_SECTOR;
  suggested_cents: number;
  uplift_cents: number;
}

/** A diagnostic item behind a client's uplift, details unlocked (staff only). */
interface RepricingItem {
  detail: string;
  id: string;
  kind: REPRICING_ITEM_KIND;
  rule: string;
  title: string;
}

/** `GET /api/repricing/{id}` (`RepricingDetailResource`). */
interface RepricingClientDetail extends RepricingClient {
  items: RepricingItem[];
}

/** The list's totals: the uplift is Σ (suggested − current) × 12 over exactly the listed clients. */
interface RepricingSummary {
  average_pct: number;
  clients: number;
  current_yearly_cents: number;
  marked: number;
  uplift_share: number;
  uplift_total_cents: number;
}

/** How many listed clients fall in a band of percent. */
interface RepricingSpreadBand {
  count: number;
  from: number;
  to: number;
}

/** `GET /api/repricing?sector=&regime=`. */
interface RepricingList {
  clients: RepricingClient[];
  spread: RepricingSpreadBand[];
  summary: RepricingSummary;
}

/** The list's filters as the screen holds them; null is "every". */
interface RepricingFilters {
  regime: CLIENT_REGIME | null;
  sector: CLIENT_SECTOR | null;
}

/** `POST /api/repricing/mark` (`MarkRepricingRequest`). */
interface MarkRepricingPayload {
  client_ids: number[];
}

interface MarkRepricingResult {
  clients: RepricingClient[];
  marked: number;
}

/** `GET /api/repricing/{id}/letter` (`RepricingLetterResource`). */
interface RepricingLetter {
  client: { cnpj: string; id: number; name: string };
  current_cents: number;
  effective_from: string;
  firm: { address: string | null; mailbox: string | null; name: string; phone: string | null };
  issued_on: string;
  items: Pick<RepricingItem, 'kind' | 'title'>[];
  pct: number;
  suggested_cents: number;
}

/** `POST /api/clients/import` answered 200. */
interface ClientImportResult {
  created: number;
  total: number;
  updated: number;
}

/** One refused value of the CSV, by its line in the file (`ClientCsvParser`). */
interface ClientImportLineError {
  code: string;
  field: string | null;
  line: number;
}

/** The 422 body of a refused file (`ClientImportException`), by its stable `code`. */
interface ClientImportFailure {
  code?: string;
  errors?: ClientImportLineError[];
  limit?: number;
  missing?: string[];
  total_errors?: number;
}

export type {
  ClientImportFailure,
  ClientImportLineError,
  ClientImportResult,
  MarkRepricingPayload,
  MarkRepricingResult,
  RepricingClient,
  RepricingClientDetail,
  RepricingFilters,
  RepricingItem,
  RepricingLetter,
  RepricingList,
  RepricingMark,
  RepricingSpreadBand,
  RepricingSummary,
};
