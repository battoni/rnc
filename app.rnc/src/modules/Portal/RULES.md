---
version: 1.3.0
origin: rnc
---

# RULES — Portal Module

> For AI agents. rnc-2 F1, 07/11/2026; F3 (the inbox, the cards and their moves, the SLA chip); F4
> (the statement).

## Domain

The partner's portal (rnc-2 R3–R10): `/parceiro` (route `portal`, `/partner` its `en` twin), inside
Franco's own shell: `PortalInboxView` names the partner (`GET portal/me`), counts Novos · Em
andamento · Fechados · SLA estourado, lists "Próximas reuniões" and shows each referral as an
`MPortalReferralCard` with the moves its status allows (`GET portal/referrals`, `POST
portal/referrals/{id}/transition`), and `/parceiro/extrato` (route `portal-statement`, `/partner/statement`)
is `PortalStatementView` — the partner's month (`GET portal/statement`). API: the `portal/*` routes of api
`Partner` (`permission:portal.access` + `portal`).

## Public Barrel

`routes` (`PortalModuleRoutes`), `constants` (`PORTAL_ROUTE_NAMES`, `PORTAL_PERMISSIONS`,
`PORTAL_ROLE_SLUG`, `STAFF_HOME_PERMISSION`, `SLA_HOURS`, `IN_PROGRESS_STATUSES`, `MOVES_WITH_FORM`,
`TRANSITION_ERROR_KEYS`, `DEAL_VALUE_MAX_CENTS`), `enums` (`REFERRAL_STATUS`, `SLA_STATE`,
`LEDGER_MODEL`, `STATEMENT_LINE_KIND` — Partner's network ledger reads the first),
`components` (`MSlaChip` — shared with Partner's routing panel and trail —, `MPortalReferralCard`,
`MReferralMeetingForm`, `MReferralCloseForm`), `composables` (`usePortalLanding`, `useLedgerMonth` +
`shiftMonth` — shared with Partner's network ledger), `services` (`GetPortalMeService`,
`GetPortalReferralsService`, `GetPortalStatementService`, `TransitionReferralService`), types
(`PortalMe`, `PortalReferral`, `PortalStatement`, `StatementLine`, `ReferralSla`, …).

## Intentional Decisions

- **One SPA, one shell** (D2): the portal renders in `TheLayout`, the same shell as the back
  office. What makes it the partner's is the rail — `navItems.local.ts` gates the portal entry on
  `portal.access` AND the role `parceiro`, and a partner holds no key of the staff groups — not a
  third layout. The core Home entry stays in the rail and leads a partner here (Home redirects).
- **Who belongs here is `usePortalLanding`**: `portal.access` and no `home.read`. The operator holds
  every key and stays in the back office; Home reads it to bounce a partner, which covers every
  refused back-office route because the guard falls them back to Home.
- **The API decides who the partner is** (`GET portal/me`, from the session's `partner_id`), never a
  route param: there is no partner id in any portal URL.
- **Willy/APW never appear** (`src/test/noPartnerNetworkNames.spec.ts` names this module).
- **The counts come from the list** (W22): the inbox is unpaginated (a partner holds a few dozen
  referrals), so the KPIs and "Próximas reuniões" are computed here; the API sends no `meta`.
- **The API decides the SLA and the contact.** `MSlaChip` only speaks `sla.state`/`sla.hours`
  (`ReferralSla`); the card shows a contact only when the answer carries one (from the accept on,
  D3), Franco's for a portfolio client (`via: firm`).
- **Who owns which move**: a move that needs nothing (aceitar, recusar, primeiro contato, enviar
  proposta) is the card's own call; the two that need input (`MOVES_WITH_FORM`: reunião, fechado)
  emit `onAskMeeting`/`onAskDeal` and the view opens `MReferralMeetingForm`/`MReferralCloseForm` in
  its one `MMainDialog` (form molecule contract). Every answer replaces its card (granular); a
  refusal is a warn toast with the API's message read by `code` (`TRANSITION_ERROR_KEYS`), and a
  coded refusal (`invalid_transition`) also emits `onStale`, which re-reads the list.
- **The meeting is a native date-time field** (W29): the partner types its own clock's time and the
  API receives the instant (`toISOString`); PrimeVue's `DatePicker` with a time is a picker jsdom
  and Playwright cannot type into reliably. The value is `InputNumber` in reais, sent as cents.
- **A contract referral shows its contract as a band** (rnc-3 R21, AM20): `contract` (null on any
  other referral) is drawn as the kind ("Contrato de distribuição"), "até <ano>", "sem cláusula da
  Reforma" while it lacks it, and the API's `value_range` short ("R$ 1 mi – R$ 2 mi por ano", "a
  partir de R$ 2 mi por ano" on the top band, `max_cents` null) with "Faixa de valor, não o valor do
  contrato." The card formats the band's bounds; it never has, and never shows, the exact value or
  anything else of the client (the API's allow-list, api Partner RULES).
- **Motion, sparingly**: the greeting, the KPI tiles and the cards enter with `.motion-enter`, the
  stagger capped at six cards; nothing moves after a move — the card just changes.

- **The statement shows one model** (rnc-2 R11, D10): the lines, "Total devido à {firm}" and the
  current model's rule — never the other model; the API does not even send it. "Valor a conferir —
  sem cobrança automática" sits above every figure. A closing under assinatura reads "incluído".
- **The API decides "this month"** (`useLedgerMonth`): the first request sends no month and the
  answer's `month` becomes the screen's; ◀ ▶ move from there, ▶ stops at that first month. The
  browser's clock never decides which month is current.
- **The statement is a rail child of the portal entry** and a link on the inbox: the mobile bottom
  bar shows no children, so the inbox link is the way in there.

## Known Gaps

_Retired 07/11/2026 (rnc-2 F4):_ "No statement yet" — `/parceiro/extrato` is `PortalStatementView`.

- **A meeting's time reads in São Paulo**, whatever the partner's own zone: every partner of the
  network is in Franco's region (facts.md); a partner elsewhere would type its own time and read
  São Paulo's back.
