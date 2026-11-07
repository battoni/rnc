---
version: 1.1.0
origin: rnc
---

# RULES — Portal Module

> For AI agents. rnc-2 F1, 07/11/2026; F3 (the inbox, the cards and their moves, the SLA chip).

## Domain

The partner's portal (rnc-2 R3–R10): `/parceiro` (route `portal`, `/partner` its `en` twin), inside
Franco's own shell: `PortalInboxView` names the partner (`GET portal/me`), counts Novos · Em
andamento · Fechados · SLA estourado, lists "Próximas reuniões" and shows each referral as an
`MPortalReferralCard` with the moves its status allows (`GET portal/referrals`, `POST
portal/referrals/{id}/transition`). The statement arrives in F4. API: the `portal/*` routes of api
`Partner` (`permission:portal.access` + `portal`).

## Public Barrel

`routes` (`PortalModuleRoutes`), `constants` (`PORTAL_ROUTE_NAMES`, `PORTAL_PERMISSIONS`,
`PORTAL_ROLE_SLUG`, `STAFF_HOME_PERMISSION`, `SLA_HOURS`, `IN_PROGRESS_STATUSES`, `MOVES_WITH_FORM`,
`TRANSITION_ERROR_KEYS`, `DEAL_VALUE_MAX_CENTS`), `enums` (`REFERRAL_STATUS`, `SLA_STATE`),
`components` (`MSlaChip` — shared with Partner's routing panel and trail —, `MPortalReferralCard`,
`MReferralMeetingForm`, `MReferralCloseForm`), `composables` (`usePortalLanding`), `services`
(`GetPortalMeService`, `GetPortalReferralsService`, `TransitionReferralService`), types (`PortalMe`,
`PortalReferral`, `ReferralSla`, …).

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
- **Motion, sparingly**: the greeting, the KPI tiles and the cards enter with `.motion-enter`, the
  stagger capped at six cards; nothing moves after a move — the card just changes.

## Known Gaps

- **No statement yet** — `/parceiro/extrato` is F4.
- **A meeting's time reads in São Paulo**, whatever the partner's own zone: every partner of the
  network is in Franco's region (facts.md); a partner elsewhere would type its own time and read
  São Paulo's back.
