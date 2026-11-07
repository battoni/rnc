import type { RADAR_EVIDENCE_SOURCE, RADAR_KIND, RADAR_PERIOD } from './enums';
import type { PARTNER_SPECIALTY } from '@PartnerModule';
import type { CLIENT_REGIME, CLIENT_SECTOR } from '@RepricingModule';

/** `[low, high]` in integer cents. */
type CentsRange = [number, number];

/**
 * A partner the row may send a batch to (rnc-2 R27): active, its specialty covering the rule; the
 * next batch's size (≤ 10, consent given, not yet sent to it) and how many of the row's clients it
 * already got.
 */
interface RadarPartnerOption {
  batch: number;
  id: number;
  name: string;
  sent: number;
  specialty: PARTNER_SPECIALTY;
}

/** One rule type of the Radar (R24): its clients, its range, the consent count and the pickable partners. */
interface RadarTypeRow {
  citation: string;
  clients: number;
  kind: RADAR_KIND;
  partners: RadarPartnerOption[];
  period: RADAR_PERIOD;
  range_cents: CentsRange;
  rule: string;
  title: string;
  without_consent: number;
}

/** `GET radar` (R24): the totals over every client and one row per rule type found. */
interface RadarSummary {
  clients: number;
  risk_types: number;
  total_cents: CentsRange;
  types: RadarTypeRow[];
  with_opportunity: number;
}

/**
 * An item's proof (R25). `cadastro` alone is "do cadastro"; `notas` carries what the XMLs say —
 * the readiness counts (`erp-nfe`), the measured share (`simples-*`) or the held cash (`split-payment`).
 */
interface RadarEvidence {
  at_risk?: number;
  b2b_share?: number;
  complete?: number;
  declared_b2b_share?: number;
  documents?: number;
  held_per_month_cents?: CentsRange;
  invoices?: number;
  monthly_b2b_cents?: number;
  source: RADAR_EVIDENCE_SOURCE;
  window_days?: number;
}

/** One client of a Radar type, with its range, its proof and up to three access keys. */
interface RadarClient {
  b2b_share: number;
  band: string;
  cnpj: string;
  evidence: RadarEvidence;
  id: number;
  keys: string[];
  name: string;
  partner_consent: boolean | null;
  range_cents: CentsRange;
  regime: CLIENT_REGIME;
  sector: CLIENT_SECTOR;
}

/** `GET radar/types/{rule}`: the row with the rule's text, and its clients, biggest first. */
interface RadarTypeDetail {
  clients: RadarClient[];
  type: RadarTypeRow & { detail: string };
}

/** `POST radar/referrals`: how many went, and why the others did not. */
interface RadarBatchResult {
  sent: number;
  skipped_already: number;
  skipped_no_consent: number;
}

export type {
  CentsRange,
  RadarBatchResult,
  RadarClient,
  RadarEvidence,
  RadarPartnerOption,
  RadarSummary,
  RadarTypeDetail,
  RadarTypeRow,
};
