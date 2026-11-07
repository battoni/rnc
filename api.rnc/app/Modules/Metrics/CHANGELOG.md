# Changelog — Metrics Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.1 — 2026-11-07

### Fixed

- A diagnostic opened straight at `/diagnostico` (a link, a bookmark, a lost CTA beacon) stopped the visitor at "visitas", so its lead was in no step after it nor in "Por canal" (rnc-2 QA). A diagnostic started now counts as the CTA's click (W126).

## 1.1.0 — 2026-11-07

### Added

- "Por canal" (rnc-2 F5, R14, A12): `FunnelResource.by_channel` — formulário and chat, both always, with início · contato · resultado · agendou · cliente, counted over the same cohort as the steps; a visitor's channel is its lead's (`FunnelRepository::findLeadChannels`). Tested by `tests/Feature/Metrics/FunnelByChannelTest`.

## 1.0.0 — 2026-11-07

### Added

- `GET metrics/funnel?days=7|30` behind `metrics.read` (rnc-1 F7, R24, A4, AM8, AM11): the seven-step cohort funnel with drop-off, by source and by sector page. `FunnelStep`, `FunnelRepository`, `FunnelService`, `FunnelResource`; `MetricsSeeder` (30 days of invented traffic and the seeded leads' own walks, local only). Tested by `tests/Feature/Metrics`.
