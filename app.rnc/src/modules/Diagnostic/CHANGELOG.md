# Changelog — Diagnostic Module (app)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.1 — 2026-11-07

### Changed

- Every public service goes through the Site module's `publicApi` (rnc-3 F2, R2, C1): the diagnostic, its CNPJ lookup, result, simulation, availability, booking and the report carry the tab's `X-Brand`, so a lead started under a sub-brand is stored under it and the consent the visitor ticks names that brand (AM3). Tested by `DiagnosticView.view.integration.spec.ts` ("under a sub-brand").

## 1.2.0 — 2026-11-07

### Changed

- A step change crosses over (`<Transition name="step-fade" mode="out-in">`, keyed on step and path):
  the old step fades on `quick`, the new one rises on `calm`. The result's items land one after the
  other under the summary, the simulator's columns wipe in sideways (never up: the height is the
  rate), the progress bar's fill moves on `calm` (rnc-1, the site's motion).

## 1.1.0 — 2026-11-07

### Added

- The booking and the report (rnc-1 F4, R15–R17): `MSlotPicker`, `useBooking`, `useMeetingTime`, `useProfileLine`, `GetAvailabilityService`, `BookMeetingService`, `GetReportService`, the confirmation step (`DIAGNOSTIC_STEP.BOOKED`) and `ReportView` at `/relatorio/:token`. The F3 holding step is gone. Tests: `MSlotPicker.spec`, `ReportView.view.integration.spec`, the booking walk in `DiagnosticView.view.integration.spec` and `e2e/diagnostic`.

## 1.0.0 — 2026-11-07

### Added

- The public diagnostic at `/diagnostico` (rnc-1 F3, R9–R14): `useDiagnostic`, the five services, `MChoiceGroup`, `MCnpjLookup`, `MDiagnosticContactForm`, `MDiagnosticProgress`, `MDiagnosticResult`, `MImpactSimulator`, `DiagnosticView`. Tests: `DiagnosticView.view.integration.spec.ts`, `MImpactSimulator.spec.ts`, `routes.spec.ts`, `e2e/diagnostic/diagnostic.spec.ts`.
