# Changelog — Lead Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- `leads`, `lead_consents`, `lead_events` tables, `Lead`/`LeadConsent`/`LeadEvent` models, `LeadStage`, `LeadStep`, `LeadEventType` enums, `ConsentTerms`, `LeadService` (start, CNPJ, contact + consent, progress, result seen), `PublicLeadResource`, `LeadException` (409 `lead_locked`), `LeadFactory` (rnc-1 F3, R11, AM3, AM12, AM13). Tested through `PublicDiagnosticsTest`.
