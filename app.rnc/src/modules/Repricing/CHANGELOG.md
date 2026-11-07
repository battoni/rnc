# Changelog — Repricing Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Changed

- The import's column list (`IMPORT_COLUMNS`) gains the optional `autoriza_parceiros` (rnc-2 F9, R28, A22): the dialog prints it and its accepted values (`sim`, `não` or empty), and a `consent_invalid` line error is named under `repricing.import.lineCodes` with the field `Autoriza parceiros`.

## 1.0.0 — 2026-11-07

### Added

- `RepricingView` at `/reinaldo/reajuste` (`/ceo/repricing`): the yearly uplift hero with the KPIs, `MRepricingSpread`, the sector and regime filters, `MRepricingTable` (select one or all, open), `MRepricingDetail` (fees, items, the letter link, the mark), "Marcar em lote" and the "Importar CSV" dialog (`MImportClientsForm`, line errors by code, the template download); `RepricingLetterView` at `/reinaldo/reajuste/:id/carta` (printable, outside the back office's layout); six services; rail child Reajuste under Reinaldo (rnc-1 F8, R25–R26, A13, A14).
