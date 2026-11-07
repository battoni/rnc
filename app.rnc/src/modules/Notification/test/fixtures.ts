import type { BellNotification, NotificationInbox } from '../interfaces';
import { NOTIFICATION_KIND } from '../enums';

/* `GET /api/notifications` as the Notification specs need it — the shape of `NotificationResource`. */
const MEETING_NOTIFICATION_FIXTURE: BellNotification = {
  company: 'Clínica Horizonte',
  created_at: '2026-11-19T10:05:00-03:00',
  id: '0b7c3c1e-4f43-4a5e-9a51-6a0f1f9d2b11',
  kind: NOTIFICATION_KIND.MEETING_BOOKED,
  lead_id: 8,
  read_at: null,
  specialist: 'Juliana Reis',
  starts_at: '2026-11-24T14:30:00-03:00',
};

const LEAD_NOTIFICATION_FIXTURE: BellNotification = {
  company: 'Padaria Sol',
  created_at: '2026-11-19T10:00:00-03:00',
  id: '5d1f8a2c-9b0e-4c77-8f3a-2e6d4b1c0a99',
  kind: NOTIFICATION_KIND.NEW_LEAD,
  lead_id: 7,
  read_at: null,
  sector: 'comercio',
};

const INBOX_FIXTURE: NotificationInbox = {
  items: [MEETING_NOTIFICATION_FIXTURE, LEAD_NOTIFICATION_FIXTURE],
  unread_count: 2,
};

export { INBOX_FIXTURE, LEAD_NOTIFICATION_FIXTURE, MEETING_NOTIFICATION_FIXTURE };
