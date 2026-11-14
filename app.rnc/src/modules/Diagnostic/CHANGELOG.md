# Changelog — Diagnostic Module (app)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.5.0 — 2026-11-14

- Never at the root (rnc-4 F6b, R28): `/diagnostico` takes the Site module's `firmSiteOnly` as `beforeEnter` — on the platform firm it lands on the product's landing. Tested by `productSite.spec` and `e2e/product/landing.spec`.

## 1.4.0 — 2026-11-14

- The chat per firm (rnc-4 F3c, R17): the "Formulário · Conversar no chat" toggle shows only where `public/firm` lists `chat` — not on the platform firm's root site; the form is always there. Tested by `DiagnosticView.chatOff.spec.ts`.

## 1.3.2 — 2026-11-07

### Changed

- Every public service goes through the Site module's `publicApi` (rnc-3 F2, R2, C1): the diagnostic, its CNPJ lookup, result, simulation, availability, booking and the report carry the tab's `X-Brand`, so a lead started under a sub-brand is stored under it and the consent the visitor ticks names that brand (AM3). Tested by `DiagnosticView.view.integration.spec.ts` ("under a sub-brand").

## 1.3.1 — 2026-11-07

### Fixed

- The chat said "CNPJ inválido" in a loop when the diagnostic's start had failed: a lookup with no lead is now `diagnostic.errors.unavailable` (form and chat) and starts the lead again (rnc-2 review S1). Tested by `useChatReceptionist.spec.ts` and `DiagnosticView.view.integration.spec.ts`.
- A contact the API refused was still said after the visitor answered again: answering clears the diagnostic's error too (`useDiagnostic.clearError`) (S2).
- A company found that is not theirs offers "Tentar outro CNPJ" and "Usar o questionário" instead of dropping to the quiz, and forgets what the lookup filled (S3); `receptionistScript.spec.ts`.
- Quick replies are logged as their key, so the transcript re-translates on a language switch (S6); the cursor returns to the box only at a free-text turn (S5); the chat's error line drops `role="alert"` inside the `role="log"` (S7). Tested by `MChatReceptionist.spec.ts` and `e2e/diagnostic/chat.spec.ts`.

### Removed

- The `diagnostic.chat.replies.notThisCompany` string (en, pt-BR), no longer offered.

## 1.3.0 — 2026-11-07

### Added

- The chat receptionist (rnc-2 F5, R12–R14, D11): the toggle "Formulário · Conversar no chat" on `DiagnosticView`; `useChatReceptionist` over the view's own `useDiagnostic` (no second copy of the answers); the pure `ReceptionistScript` (prompts, quick replies, the typed-text matcher in every UI language, the contact reader); the turn table `CHAT_TURNS`; `MChatReceptionist` (transcript, quick replies, the box, the "Não entendi…" alert, the reception typing), `MChatResult` (the locked items, the same `MSlotPicker`) and `MBookingConfirmation` (the confirmation, now shared by the form and the chat). `useDiagnostic` gains `channel`/`selectChannel` (sent with the start) and the shared `contact` draft (`MDiagnosticContactForm` binds it with `v-model:draft`). Motion: `motion-bubble` and `motion-typing` in `styles/components/motion.css`. Tests: `receptionistScript.spec`, `useChatReceptionist.spec` (A10/AM8: the PATCH sequence deep-equals the form's), `MChatReceptionist.spec`, the switch in `DiagnosticView.view.integration.spec`, `e2e/diagnostic/chat.spec` (walk, "Não entendi…", the switch, contrast in both schemes, 390 px).

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
