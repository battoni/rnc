# Changelog — Pipeline Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.8.0 — 2026-11-14

- A beta lead's detail (Chrome QA 1.10): `LeadView` shows the beta form's fields (role, city/UF, clients band,
  message) and a channel-aware consent summary; `LeadProfile` gains the optional beta fields; locale keys
  `pipeline.lead.profile.paths.beta`, `role`, `location`, `clientsBand`, `message`, `pipeline.lead.consent.beta.*`
  (pt-BR, en). Tested by `LeadView.view.integration.spec`.

## 1.7.0 — 2026-11-14

- The lead's own data (rnc-4 F9b, R39): `ExportLeadService`, `AnonymiseLeadService`, `LeadDetail.anonymised_at`,
  `PIPELINE_PERMISSIONS.firmSettings`, `LEAD_EXPORT_TYPE`, `ANONYMISED_ON_EXIT`, `LEAD_EVENT_TYPE.ANONYMISED`
  and its two timeline sentences; `LeadView`'s "Dados do lead (LGPD)" panel (export, anonymise behind a
  confirmation) and the anonymised notice. Tested by `LeadView.privacy.integration.spec` and
  `e2e/pipeline/lead-privacy.spec.ts`.

## 1.6.0 — 2026-11-14

- `LEAD_CHANNEL.BETA` (rnc-4 F6b, R29): a firm that signed up for the beta wears the "Beta" chip on the platform firm's board. Tested by `MLeadCard.spec`.

## 1.5.0 — 2026-11-14

- Lead detail without `partners` (rnc-4 F3c, W38): `LeadView` draws no `MLeadRouting` — the matches and the referral are the partner network's (404 without it).

## 1.4.0 — 2026-11-07

### Added

- The board honours `?etapa=<stage>` (rnc-3 F9, W83): the named column is outlined and scrolled into view. Tested by `PipelineView.view.integration.spec.ts` (`?etapa=` block) and `e2e/briefing/briefing.spec.ts`.

## 1.3.0 — 2026-11-07

### Added

- The brand on the board (rnc-3 F2, R4, A4): `MLeadCard` and `LeadView` wear the sub-brand's chip (`lead.brand.short_name`; none on the main brand); the board's "Marca" filter (`Todas · Marca principal · <each sub-brand>`, an inactive one says so) sends `?brand=<slug|main>` with the search, from the `brands` the board answers, and stays hidden for a firm with no sub-brand. Tests: `MLeadCard.spec.ts`, `PipelineView` and `LeadView` integration specs.

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
