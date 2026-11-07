---
version: 1.0.0
origin: rnc
---

# RULES — Partner Module

> For AI agents. rnc-1 F6, 07/11/2026.

## Domain

Franco's network (R21–R23): the partner registry (`/reinaldo/parceiros`, route `partners`), the
trail of every referral (`/reinaldo/trilha`, `referral-trail`), each with its `/ceo/…` twin, and the
routing panel `MLeadRouting` the Pipeline's lead detail mounts under the diagnostic. The API is
`GET/POST partners`, `PATCH partners/{id}`, `GET partners/options`, `GET leads/{id}/partner-matches`,
`POST leads/{id}/referrals`, `GET referrals` (api `Partner` module).

## Intentional Decisions

- **Permissions (AM8)**: `partners.manage` opens the registry, `referrals.read` the trail — both in
  Reinaldo's rail group, neither held by the team. The routing panel needs only the team's keys
  (`leads.read` to see the scores); whether "Encaminhar" shows is `leads.update`, computed in
  `LeadView` and passed as `canRefer` (celer-07: permissions live in the view).
- **No consent, no button**: the matches answer `can_refer` and `blocked_reason`; a blocked lead
  shows the reason above the list and every "Encaminhar" disabled, pointing at it with
  `aria-describedby`. A refusal on submit (`code: consent_required`, `already_referred`) is read by
  its CODE (`REFERRAL_ERROR_KEYS`), never by its sentence, and shown inline in `MReferralForm`.
- **The referral form opens inline under its partner**, one at a time, prefilled with the demo's
  sentence; it owns the call (form molecule contract) and the panel marks the partner referred and
  emits `onReferred(event)` so the detail prepends the timeline line — no refetch.
- **The panel loads its own matches** on mount: it is a section of one screen, not a list, and the
  detail has nothing to pass it but the lead id.
- **Deactivate is a switch, not a destructive action**: reversible ("Reativar"), so no confirm
  popup; the row answers the API's partner. A partner is never deleted (the trail points at it).
- **The registry is one list, not a page**: a firm's network is a few dozen at most; the trail,
  which grows with every referral, is paginated (`usePaginatedList`, `TRAIL_PER_PAGE`), and its
  filter goes back to page 1.
- **The trail links the company to the lead detail by route name** (`PIPELINE_ROUTE_NAMES.lead`,
  celer-05) and never shows the lead's contact data.
- **The score badge reads "92% · ERP/NF-e"** (`partners.routing.score`); a partner from
  `COVERED_SCORE` up covers something and wears the brand tint, the rest stay muted.

## Known Gaps

- **No partner portal** (Phase 2): the status stays `enviado`; the trail shows it as sent.
- **The trail has no date range** — newest first and by partner only.
