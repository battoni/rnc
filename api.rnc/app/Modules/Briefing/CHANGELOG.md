# Changelog — Briefing Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- R25's fifth action (rnc-3 F6b, W81): `ActionPicker::RADAR` — the Radar's first opportunity type (`{rule, title, clients, range_cents, period}` → `/reinaldo/radar`), from `RadarService::summary` in `BriefingService`; its e-mail sentence `mail.briefing.actions.radar` / `radar_monthly` (pt-BR and en) and button `go.radar`. Tested by `ActionPickerTest`, `BriefingTest` and `tests/Feature/Briefing/BriefingMailActionTest`.

## 1.0.0 — 2026-11-07

### Added

- Reinaldo's briefing (rnc-3 F8, R23–R25, R27, D12, D13, AM6, AM7; A21, A22, A24): `Helpers/FeeQuartiles` (nearest-rank p25–p75 by band, fallback under 5 clients, decimal → cents as text), `Helpers/ActionPicker` (R25's order, at most three, `{kind, params, to}`), `Repositories/BriefingRepository`, `Services/BriefingService` (one payload in a fixed number of queries), `Resources/BriefingResource`, `Controllers/BriefingController` — `GET briefing` behind `briefing.read`; `Services/DailyBriefingSender`, `Mail/DailyBriefingMail` (queued, pt-BR), `DTOs/BriefingMailDTO`, `briefing:send` scheduled at 07:00 São Paulo on business days. Tested by `tests/Unit/Modules/Briefing/{FeeQuartiles,ActionPicker}Test` and `tests/Feature/Briefing/{Briefing,DailyBriefingCommand}Test`.
