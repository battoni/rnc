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
  expires_on?: string;
  /** A UUID: Laravel's notifications key. */
  id: string;
  kind: NOTIFICATION_KIND;
  lead_id?: number;
  note_id?: number;
  question?: string;
  question_id?: number;
  read_at: string | null;
  sector?: string | null;
  specialist?: string;
  starts_at?: string;
}

/** `GET /api/notifications`. */
interface NotificationInbox {
  items: BellNotification[];
  unread_count: number;
}

export type { BellNotification, NotificationInbox };
