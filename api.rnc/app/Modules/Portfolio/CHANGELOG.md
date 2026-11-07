# Changelog — Portfolio Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- `ClientRepository::findClientIdsByCnpjs` — the firm's client ids by CNPJ, read by Fiscal's NF-e matching (rnc-2 F6a, R15, D6). Tested through `tests/Feature/Fiscal/NfeImportTest`.

## 1.0.0 — 2026-11-07

### Added

- `clients` table; `GET repricing`, `GET repricing/{id}`, `GET repricing/{id}/letter`, `POST repricing/mark` behind `repricing.manage`; `POST clients/import` and `GET clients/import/template` behind `clients.import` (rnc-1 F8, R25–R26, A13, A14, AM8). `RepricingCalculator` (the demo's `repricing()` on Diagnostic's `RulesEngine`), `RepricingService`, `ClientCsvParser`, `ClientImportService`, `ClientImportException`; `ClientSeeder` (500 invented clients, local only). Tested by `tests/Feature/Portfolio` and `tests/Unit/Modules/Portfolio`.
