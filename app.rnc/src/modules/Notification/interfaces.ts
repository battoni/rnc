import type { NOTIFICATION_KIND } from './enums';

/** `NotificationResource`: one bell entry — its fields depend on the `kind`. */
interface BellNotification {
  company: string;
  created_at: string;
  /** A UUID: Laravel's notifications key. */
  id: string;
  kind: NOTIFICATION_KIND;
  lead_id: number;
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
