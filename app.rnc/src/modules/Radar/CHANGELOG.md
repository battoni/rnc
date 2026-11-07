# Changelog — Radar Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- "Radar da carteira" (rnc-2 F10, R24–R27, A21's Vitest half): `RadarView` at `/reinaldo/radar` (`/ceo/radar`, route `portfolio-radar`, `radar.manage`) — the four KPIs (clients analysed, with an opportunity, the opportunities' total as an estimate, the risk types), the opportunity rows and the risk rows apart; `MRadarTypeRow` (kind, title, legal text, the split payment's "retidos na liquidação" line, count, range with its period, the covering-partner picker, "Encaminhar os N maiores" / "Todos já encaminhados", "N encaminhados", "N sem autorização ficam fora do lote", refusals by `code` on the row, "Ver clientes"); `MRadarTypeDetail` (clients with consent, range, the proof — "do cadastro" or the XMLs' figures — and up to three access keys); three services; the rail child under Reinaldo. Tested by `RadarView.view.integration.spec.ts`, `MRadarTypeDetail.spec.ts`, `routes.spec.ts`, `navItems.local.spec.ts` and `e2e/radar/radar.spec.ts`.
