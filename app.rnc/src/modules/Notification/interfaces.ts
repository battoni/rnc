import type { NOTIFICATION_KIND } from './enums';

/** `NotificationResource`: one bell entry — its fields depend on the `kind`. */
interface BellNotification {
  company: string;
  created_at: string;
  /** A partner's closing (`referral_status` at `fechado`): the first-year contract value. */
  deal_value_cents?: number;
  /** A UUID: Laravel's notifications key. */
  id: string;
  kind: NOTIFICATION_KIND;
  lead_id: number;
  /** `referral_status`: the partner that moved the referral. */
  partner?: string;
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
