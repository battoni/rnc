# Changelog — Metrics Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- `GET metrics/funnel?days=7|30` behind `metrics.read` (rnc-1 F7, R24, A4, AM8, AM11): the seven-step cohort funnel with drop-off, by source and by sector page. `FunnelStep`, `FunnelRepository`, `FunnelService`, `FunnelResource`; `MetricsSeeder` (30 days of invented traffic and the seeded leads' own walks, local only). Tested by `tests/Feature/Metrics`.
