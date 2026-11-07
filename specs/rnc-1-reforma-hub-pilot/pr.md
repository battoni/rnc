# rnc-1: Build the Franco Reforma Hub pilot (Phase 1)

**Problem.** Franco Contabilidade has no diagnostic, booking, lead pipeline or metrics, and the Reforma Hub existed only as a clickable demo with simulated data. **Outcome.** The real Phase 1 product in `api.rnc` (Laravel) + `app.rnc` (Vue): public site → diagnostic with real CNPJ lookup → lead captured before the result → booking with e-mail and report → kanban → consent-gated referrals and trail → funnel metrics → fee repricing with CSV import and letters.

## R# → tests

| R# | Tests |
| --- | --- |
| R1 firms / isolation | `api.rnc/tests/Feature/Firm/FirmScopeTest.php`, `FirmSeederTest.php`, `PublicFirmTest.php` |
| R2–R3 roles, rail | `tests/Feature/Auth/RoleLoginTest.php`, `RolePermissionMatrixTest.php`, `SeededRolesTest.php`; `app.rnc/src/components/unique/TheNavbar/navItems.local.spec.ts` |
| R4 sample modules removed | `make pre-commit` (the whole suite without them) |
| R5–R6, R8 site and sector pages | `tests/Feature/Content/PublicSectorPagesTest.php`; `app.rnc/e2e/site/site.spec.ts` |
| R7 tracking | `tests/Feature/Tracking/PublicEventsTest.php` |
| R9 CNPJ | `tests/Feature/Diagnostic/BrasilApiCnpjLookupTest.php` |
| R10–R14 diagnostic, consent, engine, locked result, simulator | `tests/Feature/Diagnostic/PublicDiagnosticsTest.php`, `tests/Unit/Modules/Diagnostic/RulesEngineTest.php`; `app.rnc/e2e/diagnostic/diagnostic.spec.ts` |
| R15–R16 availability, booking | `tests/Feature/Booking/PublicBookingTest.php`, `tests/Unit/Modules/Booking/HolidaysTest.php` |
| R17 e-mails, report, reminder, bell | `tests/Feature/Booking/PublicReportTest.php`, `RemindAppointmentsTest.php`, `tests/Feature/Notification/NotificationsTest.php`; `app.rnc/e2e/diagnostic/diagnostic.spec.ts` (report print) |
| R18–R20 pipeline, lead, agenda | `tests/Feature/Pipeline/PipelineLeadsTest.php`, `AgendaTest.php`; `AgendaView.view.integration.spec.ts` |
| R21–R23 partners, routing, trail | `tests/Feature/Partner/PartnersTest.php`, `ReferralsTest.php` |
| R24 funnel | `tests/Feature/Metrics/FunnelTest.php` |
| R25–R26 portfolio, repricing | `tests/Feature/Portfolio/ClientImportTest.php`, `RepricingTest.php`, `tests/Unit/Modules/Portfolio/RepricingCalculatorTest.php`; `app.rnc/e2e/repricing/letter-print.spec.ts` |

## Review

Plan review (fable-reviewer, before F1): 15 findings, all applied as amendments AM1–AM15 in `plan.md`.
Branch review (fable-reviewer, API and SPA): API 1–7 applied, plus two checks (Easter offsets, mass assignment); SPA 2–4 applied. **Not applied:** SPA 1 ("`/equipe` and `/reinaldo` lack `meta.permission`"): they are redirects to permission-guarded routes.

## QA

- Layer 1, tests: 643 Pest · 870 Vitest · 113 Playwright, all green (`make pre-commit`).
- Layer 2, Chrome (07/11, real API + seeded SQLite): R1 visitor 25/25 · R2 team 18/18 · R3 governance 12/12 · R4 CEO 14/14 · R5 access + A15 sweep 9/9. One bug found and fixed: the letter and the report printed light-on-white in the dark scheme (eb9b5ab). One script fix: the QA login link needs the SPA's session hint (tooling, not product).
- Layer 3, a person: owed.

## Decided while you were away

W1–W60 in `specs/rnc-1-reforma-hub-pilot/state.md` → "Decided while you were away"; spec changes C1–C11 in `spec.md` → "Changes while building".

🤖 Generated with [Claude Code](https://claude.com/claude-code)
