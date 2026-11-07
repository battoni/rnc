# Changelog — Portfolio Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.


## 1.4.0 — 2026-11-07

### Added

- The contract alert (rnc-3 F6a, R17–R19, R22, D10, AM7, AM16, AM17; A16, A17, A20's Pest half): `client_contracts` table, `ClientContract`, `ContractKind`, `ContractExposure` (`min(end, 2033) − max(start, 2027) + 1`, floor 0), `CsvReader` (the portfolio's CSV reading, shared), `ContractCsvParser` (lines independent, line codes), `ContractImportService` (a marked clause never downgraded), `ContractService` (alert in three queries; `markClause` writes `contrato.clausula` through `recordEvent`), `ContractRepository`, `ContractController` — `GET contracts`, `POST contracts/import` (throttled 10/min), `GET contracts/template`, `POST contracts/{id}/clause` behind `contracts.manage`; `ClientEventType::ContractClause`; `ContractSeeder` (40 contracts on the invented clients, local only). Tested by `tests/Unit/Modules/Portfolio/ContractExposureTest` and `tests/Feature/Portfolio/Contract{Import,Alert,Clause}Test`.

## 1.3.0 — 2026-11-07

### Added

- The client's partner consent (rnc-2 F9, R28, A22; plan AM11): `clients.partner_consent` (nullable bool), `partner_consent_at` (UTC, `UtcDateTime`), `partner_consent_via` folded into `create_clients_table` (D15); `Client::CONSENT_VIA_CONTRACT`; `ClientCsvParser` reads the optional `autoriza_parceiros` (`sim`/`não`, any case, with or without the accent, or empty — else `consent_invalid`) and the template gains it last; `ClientImportService` stamps `via contrato` and the moment, keeping an unchanged answer's; `ClientRepository::findClientsByCnpjs` (one query for the re-import) and `findAllClients` (the Radar). Tested in `tests/Feature/Portfolio/ClientConsentImportTest` and `ClientSeederTest`.

### Changed

- `ClientImportTest`'s template line gains `;autoriza_parceiros`.

## 1.2.0 — 2026-11-07

### Added

- A client's timeline (rnc-2 F7, R20, R22, D12, A17's Pest half): `client_events` table, `ClientEvent` model, `ClientEventType` (`nfe.aviso`, `fornecedores.revisao`), `ClientEventRepository`, `ClientService::recordEvent` (the only writer — Fiscal's notice and review call it), `GET clients/{id}/events` behind `fiscal.read` (`ClientEventController`, `ClientEventResource`). `ClientRepository::findClientsByRegimes`, read by Fiscal's F7 lists. Tested in `tests/Feature/Portfolio/ClientEventsTest` and through `tests/Feature/Fiscal/ReadinessTest` / `SupplierCreditTest`.

## 1.1.0 — 2026-11-07

### Added

- `ClientRepository::findClientIdsByCnpjs` — the firm's client ids by CNPJ, read by Fiscal's NF-e matching (rnc-2 F6a, R15, D6). Tested through `tests/Feature/Fiscal/NfeImportTest`.

## 1.0.0 — 2026-11-07

### Added

- `clients` table; `GET repricing`, `GET repricing/{id}`, `GET repricing/{id}/letter`, `POST repricing/mark` behind `repricing.manage`; `POST clients/import` and `GET clients/import/template` behind `clients.import` (rnc-1 F8, R25–R26, A13, A14, AM8). `RepricingCalculator` (the demo's `repricing()` on Diagnostic's `RulesEngine`), `RepricingService`, `ClientCsvParser`, `ClientImportService`, `ClientImportException`; `ClientSeeder` (500 invented clients, local only). Tested by `tests/Feature/Portfolio` and `tests/Unit/Modules/Portfolio`.
