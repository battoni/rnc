# Changelog — Diagnostic Module (app)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- The booking and the report (rnc-1 F4, R15–R17): `MSlotPicker`, `useBooking`, `useMeetingTime`, `useProfileLine`, `GetAvailabilityService`, `BookMeetingService`, `GetReportService`, the confirmation step (`DIAGNOSTIC_STEP.BOOKED`) and `ReportView` at `/relatorio/:token`. The F3 holding step is gone. Tests: `MSlotPicker.spec`, `ReportView.view.integration.spec`, the booking walk in `DiagnosticView.view.integration.spec` and `e2e/diagnostic`.

## 1.0.0 — 2026-11-07

### Added

- The public diagnostic at `/diagnostico` (rnc-1 F3, R9–R14): `useDiagnostic`, the five services, `MChoiceGroup`, `MCnpjLookup`, `MDiagnosticContactForm`, `MDiagnosticProgress`, `MDiagnosticResult`, `MImpactSimulator`, `DiagnosticView`. Tests: `DiagnosticView.view.integration.spec.ts`, `MImpactSimulator.spec.ts`, `routes.spec.ts`, `e2e/diagnostic/diagnostic.spec.ts`.
