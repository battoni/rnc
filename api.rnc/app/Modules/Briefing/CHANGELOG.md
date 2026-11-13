# Changelog — Briefing Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.1 — 2026-11-07

### Changed

- RULES: the Known Gap "the screens the actions open read no filter yet" is retired — rnc-3 F9 made the trail honour `?parceiro=` and the board `?etapa=`, and built the screen (app Briefing module). No code change here.

## 1.2.0 — 2026-11-07

### Changed

- The Radar action (rnc-3 F7, W100) reads `RadarService::firstOpportunity` instead of the whole `summary()`: the same row, without the clients' ranking and the partner pickers — 12 queries instead of 14 on `BriefingTest`'s fixture, and `GET briefing` on the seed ~100 ms → ~35 ms (live, 8013). Tested by `tests/Feature/Briefing/BriefingTest.php` ("reads the Radar's first opportunity without the batch pickers", and the existing equality with `GET radar`'s first opportunity).

## 1.1.0 — 2026-11-07

### Added

- R25's fifth action (rnc-3 F6b, W81): `ActionPicker::RADAR` — the Radar's first opportunity type (`{rule, title, clients, range_cents, period}` → `/reinaldo/radar`), from `RadarService::summary` in `BriefingService`; its e-mail sentence `mail.briefing.actions.radar` / `radar_monthly` (pt-BR and en) and button `go.radar`. Tested by `ActionPickerTest`, `BriefingTest` and `tests/Feature/Briefing/BriefingMailActionTest`.

## 1.0.0 — 2026-11-07

### Added

- Reinaldo's briefing (rnc-3 F8, R23–R25, R27, D12, D13, AM6, AM7; A21, A22, A24): `Helpers/FeeQuartiles` (nearest-rank p25–p75 by band, fallback under 5 clients, decimal → cents as text), `Helpers/ActionPicker` (R25's order, at most three, `{kind, params, to}`), `Repositories/BriefingRepository`, `Services/BriefingService` (one payload in a fixed number of queries), `Resources/BriefingResource`, `Controllers/BriefingController` — `GET briefing` behind `briefing.read`; `Services/DailyBriefingSender`, `Mail/DailyBriefingMail` (queued, pt-BR), `DTOs/BriefingMailDTO`, `briefing:send` scheduled at 07:00 São Paulo on business days. Tested by `tests/Unit/Modules/Briefing/{FeeQuartiles,ActionPicker}Test` and `tests/Feature/Briefing/{Briefing,DailyBriefingCommand}Test`.
