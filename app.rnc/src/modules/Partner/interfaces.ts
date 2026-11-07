import type { ROUTING_BLOCK } from './enums';

/** A partner's portal login (`PartnerAccessResource`, rnc-2 R2) — never with its password. */
interface PartnerAccess {
  active: boolean;
  email: string | null;
  granted_at: string | null;
  partner_id: number;
  revoked_at: string | null;
  user_id: number | null;
  username: string | null;
}

/** A login just created or a password just regenerated: the only answer that carries it. */
interface IssuedPartnerAccess extends PartnerAccess {
  password: string;
}

/** What `CreatePartnerAccessRequest` validates. */
interface PartnerAccessPayload {
  email: string;
  username: string;
}

/** A partner of the registry (`PartnerResource`). */
interface Partner {
  access: PartnerAccess | null;
  active: boolean;
  city: string | null;
  email: string;
  id: number;
  name: string;
  phone: string | null;
  specialty: string;
}

/** What `StorePartnerRequest` validates. */
interface PartnerPayload {
  city: string | null;
  email: string;
  name: string;
  phone: string | null;
  specialty: string;
}

/** What `UpdatePartnerRequest` validates: any of the fields, or the switch. */
interface UpdatePartnerPayload extends Partial<PartnerPayload> {
  active?: boolean;
}

/** One entry of `GET partners/options`. */
interface PartnerOption {
  active: boolean;
  label: string;
  value: number;
}

/** A diagnostic item a partner's specialty solves. */
interface CoveredItem {
  id: string;
  title: string;
}

/** A partner scored against one lead (`PartnerMatchesResource`). */
interface PartnerMatch {
  covers: CoveredItem[];
  partner: { city: string | null; id: number; name: string; specialty: string };
  referral: { at: string; id: number; status: string } | null;
  score: number;
}

/** The routing panel's answer. */
interface PartnerMatches {
  blocked_reason: ROUTING_BLOCK | null;
  can_refer: boolean;
  matches: PartnerMatch[];
}

/** What `ReferLeadRequest` validates. */
interface ReferLeadPayload {
  message: string;
  partner_id: number;
}

/** One line of the trail (`ReferralResource`). */
interface Referral {
  at: string;
  consent: { at: string; given: boolean; text: string; version: number };
  covers: CoveredItem[];
  id: number;
  lead: { company: string | null; id: number };
  message: string;
  partner: { active: boolean; id: number; name: string; specialty: string };
  referred_by: { id: number; name: string };
  score: number;
  status: string;
}

/** The lead's timeline line a referral wrote (the Pipeline's `LeadEventResource`). */
interface ReferralTimelineEvent {
  at: string;
  data: Record<string, unknown>;
  id: number;
  type: string;
  user: { id: number; name: string } | null;
}

/** `POST leads/{id}/referrals` answers the trail line and the timeline line. */
interface ReferralWritten {
  event: ReferralTimelineEvent;
  referral: Referral;
}

export type {
  CoveredItem,
  IssuedPartnerAccess,
  Partner,
  PartnerAccess,
  PartnerAccessPayload,
  PartnerMatch,
  PartnerMatches,
  PartnerOption,
  PartnerPayload,
  ReferLeadPayload,
  Referral,
  ReferralTimelineEvent,
  ReferralWritten,
  UpdatePartnerPayload,
};
