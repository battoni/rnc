import type { ROUTING_BLOCK } from './enums';
import type { LEDGER_MODEL, ReferralSla } from '@PortalModule';

/** Where the partner has taken a referral since (rnc-2 R10): its move, its SLA, the value once closed. */
interface ReferralProgress {
  deal_value_cents: number | null;
  sla: ReferralSla;
  status: string;
  status_at: string | null;
}

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
  referral: (ReferralProgress & { at: string; id: number }) | null;
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
interface Referral extends ReferralProgress {
  at: string;
  consent: { at: string; given: boolean; text: string; version: number };
  covers: CoveredItem[];
  id: number;
  lead: { company: string | null; id: number };
  message: string;
  partner: { active: boolean; id: number; name: string; specialty: string };
  referred_by: { id: number; name: string };
  score: number;
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

/** One partner's month on the network ledger: both models' figures and what it owes under the current one. */
interface NetworkLedgerRow {
  commission_cents: number;
  deal_total_cents: number;
  deals: number;
  due_cents: number;
  partner: { active: boolean; id: number; name: string; specialty: string };
  subscribed: boolean;
  subscription_cents: number;
}

/** The network's money model as it is now (`LedgerModelResource`): the model and both rules. */
interface LedgerModelSettings {
  commission_rate_bp: number;
  model: LEDGER_MODEL;
  monthly_fee_cents: number;
}

/** Franco's "Extrato da rede" (`NetworkStatementResource`, rnc-2 R11): every partner, the total and the other model's. */
interface NetworkLedger extends LedgerModelSettings {
  deal_total_cents: number;
  deals: number;
  month: string;
  other: { model: LEDGER_MODEL; total_cents: number };
  partners: NetworkLedgerRow[];
  total_cents: number;
}

export type {
  CoveredItem,
  IssuedPartnerAccess,
  LedgerModelSettings,
  NetworkLedger,
  NetworkLedgerRow,
  Partner,
  PartnerAccess,
  PartnerAccessPayload,
  PartnerMatch,
  PartnerMatches,
  PartnerOption,
  PartnerPayload,
  ReferLeadPayload,
  Referral,
  ReferralProgress,
  ReferralTimelineEvent,
  ReferralWritten,
  UpdatePartnerPayload,
};
