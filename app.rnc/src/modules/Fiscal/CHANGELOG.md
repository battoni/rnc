# Changelog — Fiscal Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13).

## 1.0.1 — 2026-11-07

### Changed

- `RULES.md` states the formatting rule (rnc-2 review S4): counts and percentages in the UI language, as the rest of the app; money (BRL) and moments (São Paulo) always pt-BR. No code change.

## 1.0.0 — 2026-11-07

### Added

- The fiscal screens (rnc-2 F8, R15–R22, A17's Vitest half): `ReadinessView` at `/equipe/nfe` (`/team/invoices`, route `nfe-readiness`) — the hero (invoices at risk of 1115, % complete, críticos, prontos, the bar by status), the status and system filters, `MReadinessTable`, `MReadinessDetail` (issue lines with sample invoices, the timeline, "Avisar cliente"), `MClientNoticeDialog` (the e-mail text, "Copiar texto", "Registrar aviso"), `MReadinessStatusChip`; `SupplierCreditView` at `/equipe/fornecedores` (`/team/suppliers`, `supplier-credit`) — the KPIs, the sector filter, `MSupplierCreditRow` (the two-part bar, the range as an estimate, "Propor revisão" disabled at 0 %, "Revisão proposta", the timeline); `NfeImportView` at `/reinaldo/notas` (`/ceo/invoices`, `nfe-imports`) — `MNfeImportForm` (early refusals, 422/429 by status), `MNfeBatch` (refusals by file), the paginated batches; the shared `MClientTimeline`, `useClientEvents`, `useFiscalFormat`; eight services; rail children under Equipe (`fiscal.read`) and Reinaldo (`fiscal.import`). Tested by `ReadinessView.view.integration.spec.ts`, `SupplierCreditView.view.integration.spec.ts`, `NfeImportView.view.integration.spec.ts`, `MClientNoticeDialog.spec.ts`, `MClientTimeline.spec.ts`, `MNfeImportForm.spec.ts`, `routes.spec.ts`, `navItems.local.spec.ts` and `e2e/fiscal/readiness.spec.ts`.
