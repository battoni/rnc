# Changelog — Radar Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.3.0 — 2026-11-14
- Degrades with the firm's modules (rnc-4 F3a, R19, D16): no XML evidence nor key without `fiscal`, no partner picker without `partners`; routes `module:radar`, the batch referral `module:radar,partners`. Tested by `tests/Feature/Radar/RadarDegradeTest`.

## 1.2.0 — 2026-11-07

### Added

- `RadarService::firstOpportunity()` (rnc-3 F7, W100): the Radar's first opportunity row without the clients' ranking and the partner pickers, for Reinaldo's briefing. Tested by `tests/Feature/Briefing/BriefingTest.php`.

## 1.1.0 — 2026-11-07

### Added

- The batch referral (rnc-2 F10, R27, A21): `POST radar/referrals` (`radar.manage`; `ReferRadarBatchRequest`, `ReferRadarBatchDTO`, `RadarController::refer`, `RadarService::referBatch` handing Partner's `referBatch` the row's clients in the Radar's order with the profile read); every row of `GET radar` and `GET radar/types/{rule}` gains `partners` (`{id, name, specialty, batch, sent}`, Partner's `portfolioBatchOptions`). Tested by `tests/Feature/Radar/RadarBatchReferralTest`; `RadarSummaryTest`'s query ceilings rise by the pickers' three (8 and 9).

## 1.0.0 — 2026-11-07

### Added

- "Radar da carteira" API (rnc-2 F9, R24–R26, A19's Radar Σ, A20; D9; plan AM6): `GET radar` and `GET radar/types/{rule}` behind `radar.manage` (`RadarController`, `RadarClientResource`); `RadarService` (the rules engine over every client, opportunities-only total, rows opportunities first, split-payment row = Σ of R23's held range per month); `EvidenceBuilder` (measured B2B share by value from ≥ 10 issued invoices in 90 days, per-rule proof, up to three access keys per client, one aggregate per call). Tested in `tests/Feature/Radar/RadarSummaryTest` and `tests/Unit/Modules/Radar/EvidenceBuilderTest`.
