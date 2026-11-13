import type { LEDGER_MODEL, REFERRAL_STATUS, SLA_STATE, STATEMENT_LINE_KIND } from './enums';

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
/**
 * A legal referral's contract as its partner sees it (rnc-3 R21, AM20, `PortalReferralResource`):
 * the kind (api `ContractKind` slug), the end year, whether it still lacks the Reforma clause and
 * the annual value as a fixed band — `max_cents` null on the top band. Never the exact value.
 */
interface PortalContractSummary {
  end_year: number;
  kind: string;
  sem_clausula: boolean;
  value_range: { label: string; max_cents: number | null; min_cents: number };
}

interface PortalReferral {
  closed_at: string | null;
  company: string | null;
  contact: PortalReferralContact | null;
  contacted_at: string | null;
  /** Only a legal referral of a client contract carries it (rnc-3 F6b); every other is `null`. */
  contract: PortalContractSummary | null;
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

/** One line of the partner's month: the fee (no referral) or a closing, with what it adds — `0` under assinatura. */
interface StatementLine {
  amount_cents: number;
  closed_at: string | null;
  company: string | null;
  deal_value_cents: number | null;
  kind: STATEMENT_LINE_KIND;
  referral_id: number | null;
}

/**
 * The partner's "Extrato" (`PartnerStatementResource`, rnc-2 R11): its month under the firm's model
 * only, with that model's rule — the rate under comissão, the fee under assinatura (D10).
 */
interface PortalStatement {
  commission_rate_bp?: number;
  deal_total_cents: number;
  deals: number;
  firm: { name: string };
  lines: StatementLine[];
  model: LEDGER_MODEL;
  month: string;
  monthly_fee_cents?: number;
  total_cents: number;
}

export type {
  PortalContractSummary,
  PortalMe,
  PortalReferral,
  PortalReferralContact,
  PortalStatement,
  ReferralSla,
  StatementLine,
  TransitionReferralPayload,
};
