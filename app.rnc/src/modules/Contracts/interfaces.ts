import type { CONTRACT_KIND } from './enums';
import type { PARTNER_SPECIALTY } from '@PartnerModule';

/** A partner a contract went to ("Com <parceiro>", rnc-3 R20), in send order. */
interface ContractReferredPartner {
  id: number;
  name: string;
}

/**
 * One row of the alert (`ContractAlertResource`, rnc-3 R19–R20): the client with its partner consent
 * (true · false · null — null was never answered and counts as no), the contract, its exposure in
 * the 2027–2033 transition and the partners it was already sent to. Money in integer cents.
 */
interface ContractAlertRow {
  annual_value_cents: number;
  client: {
    id: number;
    name: string;
    partner_consent: boolean | null;
    partner_consent_via: string | null;
  };
  end_year: number;
  exposure_cents: number;
  id: number;
  kind: CONTRACT_KIND;
  referred_to: ContractReferredPartner[];
  start_year: number;
  years_exposed: number;
}

/** `GET contracts` (R19): the four KPIs' figures and the at-risk rows, most exposed first. */
interface ContractAlert {
  contracts: ContractAlertRow[];
  summary: {
    annual_value_cents: number;
    at_risk: number;
    exposure_cents: number;
    referred: number;
    total: number;
  };
}

/** `GET contracts/legal-partners` (R20): active, tax law first, then pricing and controllership. */
interface LegalPartner {
  id: number;
  name: string;
  specialty: PARTNER_SPECIALTY;
}

/** One bad line of the contracts CSV (R18): the file's line (the header is 1), its column and why. */
interface ContractImportLineError {
  code: string;
  field: string | null;
  line: number;
}

/** `POST contracts/import` (R18): good lines stored, bad ones listed (≤ 100) and counted. */
interface ContractImportResult {
  created: number;
  errors: ContractImportLineError[];
  total: number;
  total_errors: number;
  updated: number;
}

/** A file the API refuses whole (422 `import_*`), with what the code needs to be said. */
interface ContractImportFailure {
  code?: string;
  limit?: number;
  message?: string;
  missing?: string[];
}

export type {
  ContractAlert,
  ContractAlertRow,
  ContractImportFailure,
  ContractImportLineError,
  ContractImportResult,
  ContractReferredPartner,
  LegalPartner,
};
