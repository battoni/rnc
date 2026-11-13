import type { NOTIFICATION_KIND } from './enums';

/**
 * `NotificationResource`: one bell entry — its fields depend on the `kind`. A lead's carry `company`
 * and `lead_id`; the assistant's (rnc-3 F5) carry the `question` and who asked, who answered or the
 * last day a review is served (`expires_on`, São Paulo's `YYYY-MM-DD`).
 */
interface BellNotification {
  answered_by?: string;
  asked_by?: string;
  company?: string;
  created_at: string;
  /** A partner's closing (`referral_status` at `fechado`): the first-year contract value. */
  deal_value_cents?: number;
  expires_on?: string;
  /** A UUID: Laravel's notifications key. */
  id: string;
  kind: NOTIFICATION_KIND;
  lead_id?: number;
  note_id?: number;
  /** `referral_status`: the partner that moved the referral. */
  partner?: string;
  question?: string;
  question_id?: number;
  read_at: string | null;
  referral_id?: number;
  sector?: string | null;
  specialist?: string;
  starts_at?: string;
  /** `referral_status`: where the partner took it — aceito, recusado or fechado. */
  status?: string;
}

/** `GET /api/notifications`. */
interface NotificationInbox {
  items: BellNotification[];
  unread_count: number;
}

export type { BellNotification, NotificationInbox };
