import type {
  CHAT_SPEAKER,
  CHAT_TURN,
  DIAGNOSTIC_CHANNEL,
  DIAGNOSTIC_PATH,
  DIAGNOSTIC_STEP,
  LOOKUP_STATUS,
  RESULT_KIND,
} from './enums';
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
  /** How the lead was started (rnc-2 R14); it never changes after. */
  channel: DIAGNOSTIC_CHANNEL;
  cnpj: string | null;
  company: string | null;
  consent: { text: string; version: number };
  has_contact: boolean;
  /** Whether the booking has to ask for an e-mail (AM5) — never the address itself. */
  has_email: boolean;
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
  channel: DIAGNOSTIC_CHANNEL;
  medium: string | null;
  path: DIAGNOSTIC_PATH;
  sector: string | null;
  source: TRAFFIC_SOURCE;
  visitor: string;
}

/** The contact as it is being typed (the form's fields, the chat's turns): every field a string. */
interface DiagnosticContactDraft {
  company: string;
  consent: boolean;
  email: string;
  name: string;
  phone: string;
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
  step?: Exclude<DIAGNOSTIC_STEP, DIAGNOSTIC_STEP.BOOKED | DIAGNOSTIC_STEP.BOOKING>;
}

/** One working day of a specialist's agenda: its free starts, ISO-8601 with the São Paulo offset. */
interface AvailabilityDay {
  date: string;
  slots: string[];
  weekday: number;
}

interface AvailabilitySpecialist {
  area: string | null;
  days: AvailabilityDay[];
  id: number;
  name: string;
  suggested: boolean;
}

/** `PublicAvailabilityResource` (R15): the next 10 working days of every specialist. */
interface Availability {
  slot_minutes: number;
  specialists: AvailabilitySpecialist[];
  suggested_specialist_id: number | null;
  timezone: string;
}

/** What `BookMeetingRequest` validates, key for key. */
interface BookMeetingPayload {
  email?: string;
  specialist_id: number;
  starts_at: string;
}

/** `PublicMeetingResource`. */
interface PublicMeeting {
  area: string | null;
  ends_at: string;
  specialist: string;
  starts_at: string;
}

/** `PublicBookingResource`: the meeting and the report's own token (AM4). */
interface BookingConfirmation {
  meeting: PublicMeeting;
  report_token: string;
}

/** `PublicReportResource` — the "Relatório de Impacto", never a contact (AM4). */
interface PublicReport {
  company: string | null;
  meeting: PublicMeeting | null;
  profile: Pick<DiagnosticProfile, 'b2b' | 'band' | 'regime' | 'sector'>;
  result: DiagnosticResult;
  simulation: DiagnosticSimulation;
}

/** A line the chat shows: an i18n key for the reception's, the visitor's own words for theirs. */
interface ChatMessage {
  id: number;
  key?: string;
  params?: Record<string, unknown>;
  speaker: CHAT_SPEAKER;
  text?: string;
}

/** The free-text box of a turn that asks for one (CNPJ, company, name, contact). */
interface ChatInput {
  autocomplete: string;
  inputmode: 'email' | 'numeric' | 'text';
  label: string;
  placeholder: string;
}

/** A quick reply: the value the turn takes, the i18n key (or a literal `text`) on the button. */
interface ChatReply {
  label?: string;
  params?: Record<string, unknown>;
  text?: string;
  value: string;
}

/** What the reception asks at a turn, the quick replies it offers and its box. */
interface ChatPrompt {
  ask: string;
  askParams: Record<string, unknown>;
  busy: boolean;
  /** The box under the replies — every turn has one but a running lookup (R13: typed text everywhere). */
  input: ChatInput | null;
  /** Whether the box takes the visitor's own words (CNPJ, company, name, contact) or reads an option. */
  isFreeText: boolean;
  replies: ChatReply[];
  turn: CHAT_TURN;
}

/**
 * What the receptionist script reads besides the turn: the diagnostic's state, the words shown
 * (`translate`) and a label in every language the site speaks (`labels`) — a visitor reading the
 * English site may still type "saúde".
 */
interface ChatContext {
  company: CnpjCompany | null;
  consentText: string;
  labels: (key: string, params?: Record<string, unknown>) => string[];
  lookupStatus: LOOKUP_STATUS;
  translate: (key: string, params?: Record<string, unknown>) => string;
}

/** A typed line read against a turn: the value it chose, or (value null) why it was not taken. */
interface ChatAnswer {
  errorKey: string | null;
  value: string | null;
}

/** The one way to reach the visitor the chat's contact turn read: an e-mail or a phone. */
interface ChatContact {
  email: string | null;
  phone: string | null;
}

/** One answer of a choice group: the value sent, the i18n key shown. */
interface ChoiceOption {
  label: string;
  value: number | string;
}

export type {
  Availability,
  AvailabilityDay,
  AvailabilitySpecialist,
  BookingConfirmation,
  BookMeetingPayload,
  ChatAnswer,
  ChatContact,
  ChatContext,
  ChatInput,
  ChatMessage,
  ChatPrompt,
  ChatReply,
  ChoiceOption,
  CnpjCompany,
  CnpjLookupResult,
  DiagnosticContactDraft,
  DiagnosticContactPayload,
  DiagnosticProfile,
  DiagnosticResult,
  DiagnosticResultItem,
  DiagnosticSimulation,
  PublicLead,
  PublicMeeting,
  PublicReport,
  SimulationPart,
  SimulationTax,
  SimulationYear,
  StartDiagnosticPayload,
  UpdateDiagnosticPayload,
};
