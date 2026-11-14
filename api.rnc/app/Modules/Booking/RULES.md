---
version: 1.6.0
origin: rnc
---
# RULES — Booking Module

> For AI agents. rnc-1 F4, 07/11/2026.

## Domain

The visitor's meeting with a specialist (R15–R17): who is bookable (`specialists`, their weekly
`specialist_hours`), the meetings (`appointments`), the "Relatório de Impacto" the confirmation links,
the confirmation and reminder e-mails, and the `appointments:remind` command.

**Making someone bookable** is `SpecialistService::makeBookable($person, $firm, $area, $focus)`: the
specialist row (one per person) and, as weekly hours, the firm's business hours, rewritten on every
call. `SpecialistSeeder` and `firm:create --specialist` (rnc-4 F3b1) both come through it, run as
that firm.

## The routes (`routes/api.local.php`)

| Route | What |
| --- | --- |
| `GET public/diagnostics/{token}/availability` | every specialist's free 30-min slots, next 10 working days, and the one "Indicado para o seu perfil" |
| `POST public/diagnostics/{token}/booking` | books one (10/min) → `reuniao_marcada`, the e-mail, the bell |
| `GET public/reports/{reportToken}` | the report (AM4), its own token, 30 days |

Every refusal the picker acts on carries a `code` (`BookingException`): `slot_taken` 409 ("esse
horário acabou de ser reservado"), `slot_unavailable` 422 (not a slot the agenda offers),
`email_required` 422 (AM5), `already_booked` 409 (one meeting per lead).

## Intentional Decisions

- **Time (AM9).** Slots are computed in `America/Sao_Paulo` (`BookingCalendar::TIMEZONE`) and
  stored in UTC by the `UtcDateTime` cast — `APP_TIMEZONE` is São Paulo, and Eloquent's own
  `datetime` would write local time. The API answers ISO-8601 with the offset. Queries compare
  UTC strings with Carbon bounds, never raw SQL (AM11).
- **The calendar is pure** (`BookingCalendar`): 10 days Monday–Friday that are not in `Holidays`
  (fixed list + Carnaval, Sexta-feira Santa and Corpus Christi from Easter by Meeus), today
  counted; a slot is 30 minutes that end by closing and start at least 2 hours from now
  (`LEAD_TIME_MINUTES`), so today offers only what is still bookable (late in the day, nothing).
  A booked start is left out of the availability. (Until the review of 07/11/2026 the window
  started tomorrow: a visitor finishing the diagnostic in the morning could not book that
  afternoon — W57.)
- **The unique (specialist, starts_at) index is the lock** (R16). The service checks first for a
  calm answer, and a constraint error from a race becomes the same 409; the unique `lead_id` makes
  one meeting per lead. The catch is `UniqueConstraintViolationException`, never a SQLSTATE
  compare: MySQL and SQLite say `23000`, PostgreSQL `23505`.
- **`starts_at` arrives as the availability gave it**: ISO-8601 with an offset or `Z`
  (`BookMeetingRequest`). A time without its zone is a 422 on the field, not a `slot_unavailable`.
- **The suggested specialist** is `SpecialistFocus::forProfile()` — the demo's
  `suggestedSpecialist()`: Simples → `simples`, indústria → `industria`, Real → `real`, else `geral`;
  the first specialist with that focus, else the first one.
- **Every write to the lead goes through `LeadService::recordBooking`** (stage, e-mail, report
  token, timeline line), inside the transaction that writes the appointment.
- **The e-mails carry values, not models** (`MeetingMailDTO`, AM6): built in the request or in the
  command's firm, so a queue worker — no current firm — never runs a scoped query to render them.
  Both are queued, pt-BR whatever `APP_LOCALE` says, and have a text twin.
- **The reminder goes once**: `claimAppointmentReminder` is a conditional update on
  `reminded_at IS NULL`, so overlapping runs cannot both send. The command walks
  `CurrentFirm::forEachFirm` (AM6) and is scheduled hourly.
- **Specialists come from `SpecialistSeeder`**: every `is_specialist` user gets a row and the firm's
  business hours; the area and focus of the four invented specialists are in the seeder.

- **The team's reads (F5)**: `findAppointmentsOfLeads` (the board's booked cards) and
  `findAppointmentsBetween` (the agenda week) serve the Pipeline module, which owns no table.

- **A brand's lead hears from its brand (rnc-3 R3)**: `MeetingMailBuilder` signs the confirmation
  and the reminder with the lead's sub-brand name (the firm's when none) and its colours
  (`MailBrand`, rnc-4 R12). Both are sent from the platform's `mail.from.address` under that name,
  replying to the firm's mailbox (D11); the report link opens on the firm's own host. Address and
  phone stay the firm's.
  *Retired 14/11/2026 (rnc-4 F2a):* "sent from that name at the firm's own mailbox" — every firm's
  mailbox domain would need verifying at the mail provider.

## Tables read from other modules

`leads` (Lead), through `LeadService`; `users` (the specialist's name, through `Specialist::user`).

## The manager's hours (rnc-4 F5a, R31, R32)

- `GET specialists`, `GET specialists/{id}/hours`, `PUT specialists/{id}/hours` (`firm.settings`,
  `SpecialistHoursController`): the CURRENT firm's bookable specialists; another firm's id is 404.
  The PUT replaces the whole week (`UpdateSpecialistHoursRequest`: ISO weekday, `HH:MM`, closing after
  opening; an empty list empties it). The visitor's availability reads the new hours on its next call.
- **Bookable = a specialist row whose person is flagged `is_specialist`** (`SpecialistRepository`):
  switching the flag off takes the person out of the agenda and the hours list, and the row — its
  hours, its past appointments — stays; switching it back on keeps those hours.
- **A newly flagged person** is made bookable by Auth's `UserService` through
  `SpecialistService::startOnFirmHours` (the firm's business hours, or the row it already had).
  Auth calls this service; Booking never writes `users`.

## Known Gaps

- *Retired 14/11/2026 (rnc-4 F2a, D11):* "the sender is the firm's mailbox, on a domain nobody
  verified" — the sender is now `mail.from.address`, the one domain `MailSenderTest` checks, and the
  firm's mailbox is the reply-to.

- *Retired 14/11/2026 (rnc-4 F5a, R31, R32):* "no screen edits a specialist's hours or flags a new
  specialist" — the manager edits hours through `PUT specialists/{id}/hours`, and flagging a person
  (`auth/users`) starts it on the firm's hours (`SpecialistService::startOnFirmHours`).
- **No rescheduling or cancelling by the visitor**: the e-mail says to reply or call; the team's
  pipeline (F5) moves the lead, but the appointment itself has no edit yet.
- **State and city holidays are not in the list** (São Paulo's 25/01, 09/07): a closed national list
  by AM9.
- **The worker is not run locally** (`QUEUE_CONNECTION=sync`): a real queue needs a worker in deploy.
