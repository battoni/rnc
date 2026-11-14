# Changelog — Radar Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-14

- Radar without `partners` (rnc-4 F3c, R19): `MRadarTypeRow` takes `canRefer` (the view's `hasModule('partners')`) and draws no batch column — no picker, no "no partner", no consent line. The XML evidence without `fiscal` is the API's (the proof reads "cadastro").

## 1.1.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13).

## 1.0.1 — 2026-11-07

### Fixed

- `RadarView` imports `vCountUp` from `@Directives`; without it the count-up never ran (rnc-2).

## 1.0.0 — 2026-11-07

### Added

- "Radar da carteira" (rnc-2 F10, R24–R27, A21's Vitest half): `RadarView` at `/reinaldo/radar` (`/ceo/radar`, route `portfolio-radar`, `radar.manage`) — the four KPIs (clients analysed, with an opportunity, the opportunities' total as an estimate, the risk types), the opportunity rows and the risk rows apart; `MRadarTypeRow` (kind, title, legal text, the split payment's "retidos na liquidação" line, count, range with its period, the covering-partner picker, "Encaminhar os N maiores" / "Todos já encaminhados", "N encaminhados", "N sem autorização ficam fora do lote", refusals by `code` on the row, "Ver clientes"); `MRadarTypeDetail` (clients with consent, range, the proof — "do cadastro" or the XMLs' figures — and up to three access keys); three services; the rail child under Reinaldo. Tested by `RadarView.view.integration.spec.ts`, `MRadarTypeDetail.spec.ts`, `routes.spec.ts`, `navItems.local.spec.ts` and `e2e/radar/radar.spec.ts`.
