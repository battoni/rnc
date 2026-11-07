# Changelog — Radar Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- "Radar da carteira" API (rnc-2 F9, R24–R26, A19's Radar Σ, A20; D9; plan AM6): `GET radar` and `GET radar/types/{rule}` behind `radar.manage` (`RadarController`, `RadarClientResource`); `RadarService` (the rules engine over every client, opportunities-only total, rows opportunities first, split-payment row = Σ of R23's held range per month); `EvidenceBuilder` (measured B2B share by value from ≥ 10 issued invoices in 90 days, per-rule proof, up to three access keys per client, one aggregate per call). Tested in `tests/Feature/Radar/RadarSummaryTest` and `tests/Unit/Modules/Radar/EvidenceBuilderTest`.
