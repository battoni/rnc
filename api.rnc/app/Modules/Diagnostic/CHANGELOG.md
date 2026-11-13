# Changelog — Diagnostic Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-07

### Changed

- The diagnostic's start stores the request's sub-brand on the lead (rnc-3 F1, R4), and every public diagnostic route reads `X-Brand` (`ResolveCurrentBrand`). Test: `BrandLeadTest` (A3).

## 1.1.0 — 2026-11-07

### Added

- `POST public/diagnostics` takes `channel` ∈ {`formulario`, `chat`}, `formulario` when absent (rnc-2 F5, R14; `StartDiagnosticRequest`, `StartDiagnosticDTO`); the lead's channel is Lead's rule. Tested by `tests/Feature/Diagnostic/ChatChannelTest`.

## 1.0.1 — 2026-11-07

### Added

- Tests only: `POST public/diagnostics` reuses the visitor's open `visitante` and opens a new lead otherwise (rnc-1 review; the behaviour is Lead's).

## 1.0.0 — 2026-11-07

### Added

- `CnpjLookup` contract + `BrasilApiCnpjLookup` (5 s timeout, 30-day cache, AM10 mapping), `RulesEngine` (the demo's `diagnose()`, `taxMix()`, `splitPaymentRange()`, `formatRange()`), the `DiagnosticRule`, `Sector`, `Regime`, `RevenueBand`, `Tax` enums, and the public routes `POST/PATCH public/diagnostics[/{token}]`, `POST …/{token}/cnpj`, `GET …/{token}/result`, `GET …/{token}/simulation` (rnc-1 F3, R9–R14). Tests: `RulesEngineTest`, `BrasilApiCnpjLookupTest`, `PublicDiagnosticsTest`.
