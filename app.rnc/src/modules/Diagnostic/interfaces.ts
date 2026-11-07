import type { DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS, RESULT_KIND } from './enums';
import type { TRAFFIC_SOURCE } from '@SiteModule';

/** The quiz's answers, as `PublicLeadResource` sends them and the steps fill them (R10). */
interface DiagnosticProfile {
  b2b: number | null;
  band: string | null;
  exporta: boolean;
  incentivos: boolean;
  regime: string | null;
  sector: string | null;
}

/** `PublicLeadResource` — the visitor's own lead, never a phone, an e-mail or an id (AM3). */
interface PublicLead {
  cnpj: string | null;
  company: string | null;
  consent: { text: string; version: number };
  has_contact: boolean;
  last_step: DIAGNOSTIC_STEP | null;
  path: DIAGNOSTIC_PATH;
  profile: DiagnosticProfile;
  stage: string;
  /** Only in the answer that creates the lead. */
  token?: string;
}

/** What BrasilAPI said about a CNPJ (`PublicCnpjLookupResource.company`). */
interface CnpjCompany {
  band: string | null;
  cnae: string;
  cnae_description: string | null;
  name: string;
  porte: string | null;
  regime: string | null;
  sector: string;
}

/** `PublicCnpjLookupResource`. */
interface CnpjLookupResult {
  company: CnpjCompany | null;
  lead: PublicLead;
  lookup: Extract<LOOKUP_STATUS, LOOKUP_STATUS.FOUND | LOOKUP_STATUS.UNAVAILABLE>;
}

/** One item of the public result: never its detail nor its cited rule (R13). */
interface DiagnosticResultItem {
  kind: RESULT_KIND;
  range_label: string;
  range_max: number;
  range_min: number;
  title: string;
}

/** `PublicDiagnosticResultResource`. */
interface DiagnosticResult {
  company: string | null;
  items: DiagnosticResultItem[];
  opportunities: number;
  risks: number;
}

interface SimulationPart {
  label: string;
  rate: number;
  tax: string;
}

interface SimulationYear {
  parts: SimulationPart[];
  total: number;
  year: number;
}

interface SimulationTax {
  label: string;
  leaving: boolean;
  tax: string;
}

/** `PublicSimulationResource` (R14). */
interface DiagnosticSimulation {
  estimate: boolean;
  split_payment: { label: string; max: number; min: number };
  taxes: SimulationTax[];
  years: SimulationYear[];
}

/** What `StartDiagnosticRequest` validates, key for key. */
interface StartDiagnosticPayload {
  medium: string | null;
  path: DIAGNOSTIC_PATH;
  sector: string | null;
  source: TRAFFIC_SOURCE;
  visitor: string;
}

/** What `UpdateDiagnosticRequest` validates under `contact`. */
interface DiagnosticContactPayload {
  company: string;
  consent: boolean;
  email: string | null;
  name: string | null;
  phone: string | null;
}

/** What `UpdateDiagnosticRequest` validates. */
interface UpdateDiagnosticPayload {
  contact?: DiagnosticContactPayload;
  profile?: Partial<DiagnosticProfile>;
  step?: Exclude<DIAGNOSTIC_STEP, DIAGNOSTIC_STEP.BOOKING>;
}

/** One answer of a choice group: the value sent, the i18n key shown. */
interface ChoiceOption {
  label: string;
  value: number | string;
}

export type {
  ChoiceOption,
  CnpjCompany,
  CnpjLookupResult,
  DiagnosticContactPayload,
  DiagnosticProfile,
  DiagnosticResult,
  DiagnosticResultItem,
  DiagnosticSimulation,
  PublicLead,
  SimulationPart,
  SimulationTax,
  SimulationYear,
  StartDiagnosticPayload,
  UpdateDiagnosticPayload,
};
