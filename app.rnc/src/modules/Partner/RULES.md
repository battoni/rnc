---
version: 1.4.0
origin: rnc
---

# RULES — Partner Module

> For AI agents. rnc-1 F6, 07/11/2026; rnc-2 F1 (portal access), F3 (the partner's progress on the panel and the trail).

## Domain

Franco's network (R21–R23): the partner registry (`/reinaldo/parceiros`, route `partners`), the
trail of every referral (`/reinaldo/trilha`, `referral-trail`), each with its `/ceo/…` twin, and the
routing panel `MLeadRouting` the Pipeline's lead detail mounts under the diagnostic. The API is
`GET/POST partners`, `PATCH partners/{id}`, `GET partners/options`, `GET leads/{id}/partner-matches`,
`POST leads/{id}/referrals`, `GET referrals` (api `Partner` module). From rnc-2 F4 also the network
ledger (`/reinaldo/extrato`, `network-ledger`, `ledger.read`): `GET ledger`, `PATCH ledger/model`.

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
- **`?parceiro=<id>` opens the trail filtered** (rnc-3 F9, W83): the briefing's "Cobrar <parceiro>"
  lands there. Only a positive integer filters; anything else opens the whole trail. The filter
  is read once on open — changing it later does not rewrite the address.
- **The score badge reads "92% · ERP/NF-e"** (`partners.routing.score`); a partner from
  `COVERED_SCORE` up covers something and wears the brand tint, the rest stay muted.

- **"Acesso ao portal" (rnc-2 R2)** opens `MPartnerAccessForm` in its own `MMainDialog`, on an
  active partner only (the API refuses an inactive one). The form owns the three calls; a
  generated password lives only in that panel — shown once, with "Copiar" — and `onChanged` hands
  the view the login WITHOUT it, so the row (and anything that keeps it) never holds a password.
  Refusals are read by `code` (`PARTNER_ACCESS_ERROR_KEYS`), like the routing form's. Revoke is
  reversible (create again), so no confirm popup.

- **The partner's progress, read-only (rnc-2 R10)**: the routing panel and the trail show each
  referral's status, the shared `MSlaChip` (Portal's — the partner and Franco read the same
  words) and, once `fechado`, the first-year value ("Fechado · R$ …"; the trail's "Contrato no 1º
  ano"). The API sends `status_at`, `sla` and `deal_value_cents` (`null` until closed) on both
  answers; Franco never moves a status — only the partner does, from its portal.

- **The network ledger (rnc-2 R11, D10)**: every partner's month, the total under the network's
  model and the other model's — Franco's comparison, never the partner's. The switch is two
  buttons with `aria-pressed` in a labelled group, offered only with `partners.manage`; a switch
  PATCHes then reads the month again, so both totals come from the API, never from SPA maths.
  Without the key the model is only named. The month is Portal's `useLedgerMonth` (Partner may read
  Portal; Portal never reads Partner).

## Known Gaps

- ~~The status still stays `enviado` until the portal's transitions~~ — retired in rnc-2 F3: the
  partner moves it from `/parceiro` and the panel and the trail show where it stands.
- **The panel's status is as of its load**: it does not poll; the bell (30 s) says when a partner
  moved something, and reopening the lead shows it.
- **The trail has no date range** — newest first and by partner only.
