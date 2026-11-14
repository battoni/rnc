---
version: 1.1.0
origin: rnc
based-on: 1.0.1
---

# RULES — Briefing Module

> For AI agents. rnc-3 F9, 07/11/2026 ("Briefing do dia", Reinaldo's landing).

## Domain

Reinaldo's day in one screen (rnc-3 R26; the demo's `briefing()`, `ceo.js`): `BriefingView` at
`/gestao/briefing` (`/management/briefing`, route `briefing`), behind `briefing.read` (gestao; the operator
holds every key). `/gestao` lands here (Home's Workspace routes); the rail child "Briefing do dia"
is first in Reinaldo's group. Read-only: every figure is `GET briefing` (api Briefing module, F8),
the same payload as the 07:00 e-mail, so the screen and the message never disagree.

## Public Barrel

`BRIEFING_ROUTE_NAMES`, `BRIEFING_PERMISSIONS` (the rail), `BriefingModuleRoutes` (the router),
`GetBriefingService`, `MCountdown`, the enums and the types. Nothing imports this module but the
router, the rail and Home's landing spec.

## Intentional Decisions

- **The screen writes the sentences, the API sends the numbers** (W82, spec C6): an action is
  `{kind, params, to}` and `sentenceOf` picks `briefing.actions.<kind>` (the demo's copy, with the
  plural form by count); a proposal without a portfolio base reads `proposals_no_range`, a Radar
  action on the split-payment rule reads `/mês` (`period: mes`). A new kind on the API needs its
  locale key here — the `switch` is exhaustive over `BRIEFING_ACTION_KIND`, so TypeScript asks.
- **Each action's button is the API's `to`, as is** — a pt-BR path with its filter
  (`/gestao/trilha?parceiro=<id>`, `/equipe/pipeline?etapa=proposta`). The locale guard rewrites
  it to the `en` twin with the query kept; the trail and the board honour the query (W83).
- **São Paulo time everywhere** (`BRIEFING_TIME_ZONE`): the greeting's hour, the date line, the
  meeting times. "Bom dia" from 05:00, "Boa tarde" from 12:00, "Boa noite" from 18:00 to 04:59
  (`GREETING_HOURS`, W105); a browser in another zone greets by Reinaldo's clock, not its own. The
  view reads the clock again every minute (`CLOCK_TICK_MS`, cleared on unmount), so a briefing left
  open turns "Bom dia" into "Boa tarde" at 12:00 (review S3, W115 — W105's "greets by the hour it was
  opened" no longer holds).
- **The countdown aims at 01/01/2027 00:00 −03:00** (`CBS_START`, written with its offset — Brazil
  has no daylight saving since 2019), not the browser's midnight as the site's `MCbsCountdown`
  does; it starts in setup, so the first frame already counts (review S2), ticks by the wall clock (`useCountdown`) and from that instant reads "CBS em vigor desde
  01/01/2027". The caption cites facts.md §2 (LC 214/2025, gov.br/fazenda read 05/11/2026) and the
  2028 postponement request as news "em discussão".
- **Money as a short range** (`useFiscalFormat().formatShortRange`, "R$ 120 mil – R$ 216 mil"): a
  KPI tile must fit 390 px; `null` reads "sem base na carteira" (R24, D12).
- **Motion, sparingly**: `motion-enter` on the cards (`--reveal-index` staggers the KPIs, the actions
  and the panels) and `v-count-up` on the three counts — imported from `@Directives`, never assumed
  global (`ContractsView` once named it without the import and nothing rolled).
- **States**: a skeleton while the first read is in flight, an alert with "Tentar de novo" when it
  fails, and an empty line per panel ("Nada pede você com urgência hoje.", "Nenhuma reunião…",
  "Nenhuma indicação…").

## Do Not

- Do not compute a figure here (no sum of stages, no SLA count): read it from the payload.
- Do not link an action to a path of your own; the API's `to` is the contract with the e-mail.

## Known Gaps

- **No refresh while open.** The briefing is read once per visit; the countdown ticks, the figures
  do not. Reinaldo reopens it (or the rail) to read it again.
- **The greeting follows the device clock** (read in São Paulo's zone); a device with a wrong clock
  greets wrongly (the API's `as_of` is not used for it).
