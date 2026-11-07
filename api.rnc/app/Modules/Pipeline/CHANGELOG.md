# Changelog — Pipeline Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.1 — 2026-11-07

### Added

- Tests only: the agenda's opening week on the São Paulo clock (Friday 22:00 and Sunday 23:00 BRT across the UTC day), and the search's literal `%`/`_` (rnc-1 review).

## 1.0.0 — 2026-11-07

### Added

- `GET leads` (board by stage, search), `GET leads/{id}` (detail with the full diagnostic, consent, meeting, timeline, notes), `PATCH leads/{id}/stage` (any other stage; perdido needs a reason; an `etapa.alterada` line with the user), `POST leads/{id}/notes`, `GET agenda?week=` (rnc-1 F5, R18–R20, A11, AM8, AM13). `PipelineService`, `AgendaService`, `MoveVia`, `LeadCardResource`, `LeadDetailResource`, `LeadEventResource`, `LeadMeetingResource`, `AgendaWeekResource`; `PipelineSeeder` (40 invented leads, local only). Tested by `tests/Feature/Pipeline`.
