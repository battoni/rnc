# Changelog — Metrics Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13).

## 1.1.0 — 2026-11-07

### Added

- "Por canal" (rnc-2 F5, R14, A12): a third `MFunnelTable` with formulário and chat from "início" on, and the share início → agendou; `FUNNEL_CHANNEL`, `FunnelChannelRow`. Tests: `MFunnelTable.spec`, `MetricsView.view.integration.spec`.

## 1.0.0 — 2026-11-07

### Added

- `MetricsView` at `/reinaldo/metricas` (`/ceo/metrics`): the funnel's seven steps as bars with the drop-off (`MFunnelChart`), four headline numbers, the tables by source and by sector page (`MFunnelTable`) and the 7/30-day toggle; `GetFunnelService`; rail child Métricas under Reinaldo (rnc-1 F7, R24, A4).
