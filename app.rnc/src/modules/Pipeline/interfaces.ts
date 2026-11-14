import type { LEAD_CHANNEL, LEAD_EVENT_TYPE, LEAD_STAGE, MOVE_VIA } from './enums';

/** `LeadMeetingResource`: a lead's booked meeting, times in São Paulo with the offset. */
interface LeadMeeting {
  area: string | null;
  ends_at: string;
  id: number;
  specialist: string;
  starts_at: string;
}

/** The sub-brand a lead was started under (rnc-3 R4); null on the main brand. */
interface LeadBrand {
  id: number;
  short_name: string;
}

/** One option of the board's "Marca" filter (`BrandOptionResource`, rnc-3 F2). */
interface BoardBrandOption {
  active: boolean;
  short_name: string;
  slug: string;
}

/** `LeadCardResource`: one card on the board. */
interface LeadCard {
  brand: LeadBrand | null;
  channel: LEAD_CHANNEL;
  company: string | null;
  contact_name: string | null;
  created_at: string;
  has_contact: boolean;
  id: number;
  last_step: string | null;
  lost_reason: string | null;
  meeting: LeadMeeting | null;
  opportunities: number;
  risks: number;
  sector: string | null;
  source: string;
  stage: LEAD_STAGE;
  updated_at: string;
}

/** One column of `GET /api/leads`. */
interface BoardColumn {
  count: number;
  items: LeadCard[];
  stage: LEAD_STAGE;
}

/** `GET /api/leads`. */
interface LeadBoard {
  brands: BoardBrandOption[];
  columns: BoardColumn[];
}

/** `LeadEventResource`: one timeline line; `user` null is the visitor. */
interface LeadTimelineEvent {
  at: string;
  data: Record<string, unknown>;
  id: number;
  type: LEAD_EVENT_TYPE | string;
  user: { id: number; name: string } | null;
}

/** A consent answer as given (AM12). */
interface LeadConsent {
  given: boolean;
  given_at: string;
  text: string;
  version: number;
  via: string;
}

/** One diagnostic item, with the detail and the rule the public result never sends. */
interface LeadDiagnosisItem {
  detail: string;
  id: string;
  kind: string;
  range_label: string;
  range_max: number;
  range_min: number;
  rule: string;
  title: string;
}

interface LeadDiagnosis {
  items: LeadDiagnosisItem[];
  opportunities: number;
  risks: number;
}

/** The profile the diagnostic answered (R10). */
interface LeadProfile {
  b2b: number | null;
  band: string | null;
  exporta: boolean;
  incentivos: boolean;
  regime: string | null;
  sector: string | null;
}

/** `LeadDetailResource`: the lead detail (R19). */
interface LeadDetail {
  /** rnc-4 R39: when who the lead was got wiped; null while the lead is whole. */
  anonymised_at: string | null;
  brand: LeadBrand | null;
  channel: LEAD_CHANNEL;
  cnpj: string | null;
  company: string | null;
  consent: LeadConsent | null;
  contact_name: string | null;
  created_at: string;
  diagnosis: LeadDiagnosis;
  email: string | null;
  id: number;
  last_step: string | null;
  lost_reason: string | null;
  medium: string | null;
  meeting: LeadMeeting | null;
  notes: LeadTimelineEvent[];
  path: string;
  phone: string | null;
  profile: LeadProfile;
  source: string;
  stage: LEAD_STAGE;
  timeline: LeadTimelineEvent[];
  updated_at: string;
}

/** `PATCH /api/leads/{id}/stage` — what `UpdateLeadStageRequest` validates. */
interface MoveLeadPayload {
  reason?: string;
  stage: LEAD_STAGE;
  via: MOVE_VIA;
}

/** The move's answer: the card as it is now and the line it wrote. */
interface LeadMove {
  event: LeadTimelineEvent;
  lead: LeadCard;
}

/** `POST /api/leads/{id}/notes` — what `StoreLeadNoteRequest` validates. */
interface LeadNotePayload {
  text: string;
}

/** A card waiting for its perdido reason. */
interface PendingLostMove {
  company: string | null;
  id: number;
  via: MOVE_VIA;
}

/** One meeting of `GET /api/agenda`. */
interface AgendaMeeting {
  area: string | null;
  company: string | null;
  ends_at: string;
  id: number;
  lead_id: number;
  specialist: string;
  stage: LEAD_STAGE;
  starts_at: string;
}

interface AgendaDay {
  date: string;
  meetings: AgendaMeeting[];
}

/** `GET /api/agenda?week=`. */
interface AgendaWeek {
  days: AgendaDay[];
  next_week: string;
  previous_week: string;
  week_end: string;
  week_start: string;
}

export type {
  AgendaDay,
  AgendaMeeting,
  AgendaWeek,
  BoardBrandOption,
  BoardColumn,
  LeadBoard,
  LeadBrand,
  LeadCard,
  LeadConsent,
  LeadDetail,
  LeadDiagnosis,
  LeadDiagnosisItem,
  LeadMeeting,
  LeadMove,
  LeadNotePayload,
  LeadProfile,
  LeadTimelineEvent,
  MoveLeadPayload,
  PendingLostMove,
};
