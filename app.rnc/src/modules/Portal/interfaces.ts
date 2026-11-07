import type { REFERRAL_STATUS, SLA_STATE } from './enums';

/** Who is signed in to the portal (`PortalMeResource`). */
interface PortalMe {
  firm: { name: string; slug: string };
  partner: { id: number; name: string; specialty: string };
}

/** A referral's first-contact clock (`ReferralSla::of`): business hours, to one decimal. */
interface ReferralSla {
  hours: number;
  state: SLA_STATE;
}

/** The way in once the partner accepts: the lead's own, or Franco's for a portfolio client (R27). */
interface PortalReferralContact {
  email: string | null;
  name: string | null;
  phone: string | null;
  via: 'firm' | 'lead';
}

/** A referral as its partner sees it (`PortalReferralResource`, R6) — never a range, a note or the timeline. */
interface PortalReferral {
  closed_at: string | null;
  company: string | null;
  contact: PortalReferralContact | null;
  contacted_at: string | null;
  covers: string[];
  /** Present only once `fechado` (W24). */
  deal_value_cents?: number;
  id: number;
  meeting_at: string | null;
  message: string;
  profile: { band: string | null; regime: string | null; sector: string | null };
  received_at: string;
  score: number;
  sla: ReferralSla;
  status: REFERRAL_STATUS;
  status_at: string | null;
}

/** What `TransitionReferralRequest` validates: one step of the graph, with what that step needs. */
interface TransitionReferralPayload {
  deal_value_cents?: number;
  meeting_at?: string;
  to: REFERRAL_STATUS;
}

export type { PortalMe, PortalReferral, PortalReferralContact, ReferralSla, TransitionReferralPayload };
