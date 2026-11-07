---
version: 1.3.0
origin: rnc
---

# RULES — Pipeline Module

> For AI agents. rnc-1 F5, 07/11/2026.

The team's screens (R18–R20): the board (`/equipe/pipeline`, route `pipeline`), the lead detail
(`/equipe/leads/:id`, `pipeline-lead`) and the agenda week (`/equipe/agenda`, `agenda`), each with its
`/team/…` twin. The API is `GET leads`, `GET leads/{id}`, `PATCH leads/{id}/stage`,
`POST leads/{id}/notes`, `GET agenda?week=` (api `Pipeline` module).

## Intentional Decisions

- **The routing panel is the Partner module's** (`MLeadRouting`, F6): `LeadView` mounts it under the
  diagnostic with `canRefer` = `leads.update` and prepends the `encaminhamento.enviado` line it
  emits. The stage does not change on a referral.

- **Permissions (AM8)**: `leads.read` opens the board and the detail, `leads.update` shows the
  ◀ ▶ ✕, the drag, the stage select and the note form, `agenda.read` the agenda. A missing key hides
  the control; the API refuses it too.
- **The channel chip (rnc-2 R14)**: the board card names the channel only when it is not the form
  (chat, carteira) — the usual case would repeat "Formulário" on every card; the lead detail
  always names it (`MLeadChannelChip`).
- **One way to move a card** — `MoveLeadStageService` with `via` (`botao` for ◀ ▶ ✕, `arrastar` for a
  drop, `ficha` for the detail's select). Perdido never moves directly: the view opens
  `MLostReasonForm` in its `MMainDialog`, which owns the call (celer-07 "a destructive action that
  needs input is a form").
- **The team moves anywhere (AM13)**: ◀ ▶ walk `STAGE_FLOW` (the demo's `nextStage`/`prevStage`, ◀
  from Perdido goes to Lead); the detail's select reaches every stage.
- **Granular updates**: a move answers the card and its timeline line — `useLeadBoard.applyMove`
  moves the card and the two counts; the detail prepends the line. A note prepends to both lists.
- **Native drag and drop**, no library: the card sets `LEAD_DRAG_TYPE`; a column takes only that type.
  The ◀ ▶ buttons are the touch and keyboard path.
- **The board scrolls inside its own frame** (`w-0 min-w-full overflow-x-auto`): the seven columns
  never widen the layout's column or the page, at 390 or at 1440 (`e2e/pipeline`).
- **Sentences are the SPA's**: the API stores each timeline line's facts; `useLeadFormat().sentenceOf`
  writes it, keyed by `TIMELINE_EVENT_KEYS` (an i18n key cannot hold the API's dots). Times are São
  Paulo's (AM9, `MEETING_TIME_ZONE` from Diagnostic).
- **Contact actions are real links** (`contactLinks`): `tel:+55…`, `https://wa.me/55…`, `mailto:`.
- **Sector, regime and band labels are Diagnostic's** (`diagnostic.sectors.*` …): one wording.

- **The "Marca" filter's options come with the board** (`GET leads` → `brands`), not from
  `GET brands`, which is `brands.manage` — the team holds `leads.read` only. `main` is the main
  brand's word (`MAIN_BRAND_FILTER`); null asks for every brand (rnc-3 R4, AM13).

## Known Gaps

- **A column shows at most 60 cards** (API `COLUMN_LIMIT`); the rest are reached by the search.
- **The bell does not link to the lead yet** (Notification module): the detail exists now by
  `PIPELINE_ROUTE_NAMES.lead`.
- **A contact link does not log the call or the e-mail** on the timeline.
