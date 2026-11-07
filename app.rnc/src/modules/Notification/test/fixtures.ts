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

/** A partner's move the bell names (rnc-2 R9, `ReferralStatusNotification`): aceito, recusado, fechado. */
function referralStatusNotification(status: string, overrides: Partial<BellNotification> = {}): BellNotification {
  return {
    company: 'Consultoria Prisma Ltda',
    created_at: '2026-11-19T11:00:00-03:00',
    id: `9a0c1d2e-0000-4000-8000-00000000000${status.length}`,
    kind: NOTIFICATION_KIND.REFERRAL_STATUS,
    lead_id: 7,
    partner: 'NexoERP (exemplo)',
    read_at: null,
    referral_id: 31,
    status,
    ...(status === 'fechado' ? { deal_value_cents: 1_200_000 } : {}),
    ...overrides,
  };
}

const INBOX_FIXTURE: NotificationInbox = {
  items: [MEETING_NOTIFICATION_FIXTURE, LEAD_NOTIFICATION_FIXTURE],
  unread_count: 2,
};

/** The Reforma assistant's bells (rnc-3 F5): `AssistantQuestionNotification`, `…AnswerNotification`, `…NoteExpiringNotification`. */
const ASSISTANT_INBOX_FIXTURE: NotificationInbox = {
  items: [
    {
      asked_by: 'Patrícia Gomes',
      created_at: '2026-11-19T10:20:00-03:00',
      id: '7a1e2b3c-0000-4000-8000-000000000001',
      kind: NOTIFICATION_KIND.ASSISTANT_QUESTION,
      question: 'Qual o melhor ERP?',
      question_id: 11,
      read_at: null,
    },
    {
      answered_by: 'Mariana Toledo',
      created_at: '2026-11-19T10:15:00-03:00',
      id: '7a1e2b3c-0000-4000-8000-000000000002',
      kind: NOTIFICATION_KIND.ASSISTANT_ANSWER,
      question: 'Qual o melhor ERP?',
      question_id: 11,
      read_at: null,
    },
    {
      created_at: '2026-11-19T07:00:00-03:00',
      expires_on: '2026-11-26',
      id: '7a1e2b3c-0000-4000-8000-000000000003',
      kind: NOTIFICATION_KIND.ASSISTANT_NOTE_EXPIRING,
      note_id: 1,
      question: 'Quando começa a CBS?',
      read_at: null,
    },
  ],
  unread_count: 3,
};

export {
  ASSISTANT_INBOX_FIXTURE,
  INBOX_FIXTURE,
  LEAD_NOTIFICATION_FIXTURE,
  MEETING_NOTIFICATION_FIXTURE,
  referralStatusNotification,
};
