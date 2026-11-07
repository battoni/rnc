# Changelog — Booking Module

## 1.1.0 — 2026-11-07

### Added

- `AppointmentRepository::findAppointmentsOfLeads` and `findAppointmentsBetween` for the team's board and agenda (rnc-1 F5).

## 1.0.0 — 07/11/2026 (rnc-1 F4)

- `specialists`, `specialist_hours`, `appointments`; `SpecialistSeeder` from the firm's hours.
- Availability and booking on the lead token; the report on its own token (AM4).
- `BookingConfirmed` and `MeetingReminder` Mailables; `appointments:remind`, hourly.
