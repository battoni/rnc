# Changelog — Booking Module

## 1.4.0 — 2026-11-14

### Changed

- The meeting e-mails wear the lead's firm (rnc-4 F2a, R12, D11): `MeetingMailDTO` carries a `MailBrand` (replaces `firmMailbox` and `sender()`); from the platform's address under the brand's name, replies to the firm's mailbox; the report link on the firm's own host. Tested by `tests/Feature/Mail/FirmMailBrandTest`.

## 1.3.0 — 2026-11-07

### Changed

- The confirmation and the reminder are signed by the lead's sub-brand (rnc-3 F1, R3) and sent from that name at the firm's own mailbox (`MeetingMailDTO::$firmMailbox`, `sender()`). Test: `BrandLeadTest` (A3).


## 1.2.0 — 2026-11-07

### Changed

- `BookingCalendar` counts today among the 10 working days and offers only slots at least 2 hours away (`LEAD_TIME_MINUTES`); `slotsOf` takes the clock (rnc-1 review, W57).
- `BookMeetingRequest` wants `starts_at` as ISO-8601 with an offset or `Z`; a time without its zone is a 422 on the field.

### Fixed

- `BookingService::reserve` catches `UniqueConstraintViolationException` instead of comparing the SQLSTATE with `23000` — PostgreSQL's `23505` answered 500 instead of 409 `slot_taken`. Tests: the insert-meets-the-index case (real index and a pgsql double), the offset formats, today's lead time, Easter 2027 in `HolidaysTest`.

## 1.1.0 — 2026-11-07

### Added

- `AppointmentRepository::findAppointmentsOfLeads` and `findAppointmentsBetween` for the team's board and agenda (rnc-1 F5).

## 1.0.0 — 07/11/2026 (rnc-1 F4)

- `specialists`, `specialist_hours`, `appointments`; `SpecialistSeeder` from the firm's hours.
- Availability and booking on the lead token; the report on its own token (AM4).
- `BookingConfirmed` and `MeetingReminder` Mailables; `appointments:remind`, hourly.
