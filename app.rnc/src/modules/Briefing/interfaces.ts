import type { BRIEFING_ACTION_KIND, BRIEFING_RADAR_PERIOD } from './enums';
import type { SLA_STATE } from '@PortalModule';

/** A money range in cents, `[low, high]` (plan P5); null when the portfolio gives no base (R24). */
type BriefingRange = [number, number] | null;

interface BriefingMeeting {
  company: string | null;
  lead_id: number;
  /** São Paulo ISO 8601. */
  starts_at: string;
  specialist: string;
}

interface BriefingReferral {
  company: string | null;
  hours: number;
  id: number;
  lead_id: number;
  partner: { id: number; name: string };
  state: SLA_STATE;
}

interface BriefingStage {
  count: number;
  range_cents: BriefingRange;
  stage: string;
}

/** Each action's numbers, by kind (api `ActionPicker`): the sentence is this screen's locale (W82). */
interface BriefingActionParams {
  [BRIEFING_ACTION_KIND.SLA_BREACHED]: { company: string; goal_hours: number; hours: number; partner: string };
  [BRIEFING_ACTION_KIND.MEETINGS_TODAY]: { count: number };
  [BRIEFING_ACTION_KIND.PROPOSALS]: { count: number; range_cents: BriefingRange };
  [BRIEFING_ACTION_KIND.PARTIAL_LEADS]: { count: number };
  [BRIEFING_ACTION_KIND.RADAR]: {
    clients: number;
    period: BRIEFING_RADAR_PERIOD;
    range_cents: [number, number];
    rule: string;
    title: string;
  };
}

type BriefingAction = {
  [K in BRIEFING_ACTION_KIND]: { kind: K; params: BriefingActionParams[K]; to: string };
}[BRIEFING_ACTION_KIND];

/** `GET briefing` (rnc-3 R23–R25; api Briefing RULES → Endpoints). */
interface Briefing {
  actions: BriefingAction[];
  as_of: string;
  leads: { last_24_hours: number; last_7_days: number };
  meetings: { count: number; next: BriefingMeeting[]; today: number; window_end: string };
  pipeline: { count: number; range_cents: BriefingRange; stages: BriefingStage[] };
  referrals: {
    by_state: { atencao: number; estourado: number; ok: number };
    count: number;
    goal_hours: number;
    more: number;
    top: BriefingReferral[];
  };
}

export type { Briefing, BriefingAction, BriefingMeeting, BriefingRange, BriefingReferral, BriefingStage };
