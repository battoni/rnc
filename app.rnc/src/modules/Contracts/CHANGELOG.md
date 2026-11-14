# Changelog — Contracts Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13).

## 1.0.1 — 2026-11-07

### Fixed

- `ContractsView` imports `vCountUp` from `@Directives`: the "Encaminhados ao jurídico" figure named `v-count-up` without importing it, so Vue warned "Failed to resolve directive" and the figure never rolled (found building rnc-3 F9; directives are not global).

## 1.0.0 — 2026-11-07

### Added

- "Alerta de contratos" (rnc-3 F7, R18–R22 UI, completes A20): `ContractsView` at `/reinaldo/contratos` (`/ceo/contracts`, route `contract-alert`, `contracts.manage`) — the dated note, the four KPIs (sem cláusula N de M, annual value, value × years exposed, sent to legal), the at-risk rows; `MContractRow` (client, "<tipo> · desde · <valor>/ano · até", the '27–'33 chips lit on the exposed years, the exposure bar ≥ 2 %, the legal picker, "Encaminhar ao jurídico" disabled with the consent reason, "Com <parceiro>", "Sem parceiro jurídico na rede.", refusals by `code`, "Cláusula incluída" asked on the row); `MImportContractsForm` (columns, template, partial import with the lines left out, whole-file refusals); six services; the rail child under Reinaldo. Tested by `ContractsView.view.integration.spec.ts`, `MContractRow.spec.ts`, `MImportContractsForm.spec.ts`, `routes.spec.ts`, `navItems.local.spec.ts` and `e2e/contracts/contracts.spec.ts`.
