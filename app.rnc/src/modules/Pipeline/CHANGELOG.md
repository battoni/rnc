# Changelog — Pipeline Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-07

### Added

- The lead's channel (rnc-2 F5, R14, A12): `LEAD_CHANNEL`, `LEAD_CHANNEL_ICONS` and `MLeadChannelChip`; `MLeadCard` shows it for a chat or carteira lead, `LeadView` for every lead. Tests: `MLeadCard.spec`, `LeadView.view.integration.spec`.

## 1.1.1 — 2026-11-07

### Added

- Tests only: `AgendaView` — "Esta semana" asks the API for no week, and on a São Paulo weekend (Sunday 23:00 BRT included, already Monday in UTC) the screen still asks for none, so the weekend rule stays the API's (rnc-1 review).

## 1.1.0 — 2026-11-07

### Added

- The lead detail mounts the Partner module's routing panel under the diagnostic; the timeline reads `encaminhamento.enviado` ("Encaminhado a … (92%)") as a team line (rnc-1 F6, R22).

## 1.0.0 — 2026-11-07

### Added

- The team's board (`PipelineView`: a column per stage, `MLeadCard` with ◀ ▶ ✕ and native drag, search, Perdido through `MLostReasonForm`), the lead detail (`LeadView`: profile, `MLeadDiagnosis` with details, consent, contact links, meeting, `MLeadNoteForm`, `MLeadTimeline`, stage select) and the agenda week (`AgendaView`, ◀ ▶); services, `useLeadBoard`, `useLeadFormat`, `stageFlow`, `contactLinks`; rail children Pipeline and Agenda under Equipe (rnc-1 F5, R18–R20, A11).
