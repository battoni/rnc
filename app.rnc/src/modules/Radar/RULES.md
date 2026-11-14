---
version: 1.1.0
origin: rnc
based-on: 1.0.0
---

# RULES — Radar Module

> For AI agents. rnc-2 F10, 07/11/2026 (the "Radar da carteira" screen and its batch referral).

## Domain

Reinaldo's "Radar da carteira" (rnc-2 R24–R27): the Reforma's rules engine over every client of the
firm, read by the API (api Radar module, F9) — `RadarView` at `/gestao/radar` (`/management/radar`, route
`portfolio-radar`), behind `radar.manage` (gestao; the operator holds every key), the rail child
"Radar da carteira" last in Reinaldo's group. The screen sends a row's batch of portfolio clients
to a partner; the API writes everything (api Partner RULES → "The Radar's batch referral").

## Intentional Decisions

- **Every figure is the API's.** The KPIs, each row's range (a year, or a month for the split
  payment, `period`), the partners a row may pick (only those whose specialty covers the rule), the
  next batch's size and the already sent come from `GET radar`; after a batch the view reads it
  again rather than doing arithmetic (as the ledger, W41). "Encaminhar os N maiores" is the chosen
  partner's `batch`; at 0 the button says "Todos já encaminhados" and is disabled.
- **Opportunities and risks apart** (R24): risks sit in their own panel, "fora do total em R$"; the
  total KPI is the API's opportunities-only sum, short ("R$ 13 mi – R$ 40 mi") with the full range in
  its `title`, and labelled an estimate. Every range on screen carries "estimativa" and its period.
- **The split-payment row (R26)** says "≈ <range> por mês retidos na liquidação das vendas B2B
  (estimativa)" — the API's Σ of R23 per month.
- **Consent is visible, never decided here** (R28): the row prints "N sem autorização ficam fora do
  lote" from `without_consent`; an opened row marks each client whose `partner_consent` is not
  `true` "Sem autorização" (null counts as no, W98). The batch's consent gate is the API's.
- **The row owns its two calls** (`MRadarTypeRow`): the batch (`ReferRadarBatchService`) and its
  clients (`GetRadarTypeService`, loaded on the first "Ver clientes", re-read after a batch when
  open), and tells the view `onSent`, which toasts and reloads. A refusal the API names by `code`
  (`partner_not_covering`, `already_referred` — `RADAR_ERROR_KEYS`) is said ON the row
  (`role="alert"`), as Fiscal's supplier row (W89).
- **The proof (R25)** is `MRadarTypeDetail`, presentational: "do cadastro" in a neutral chip, or what
  the XMLs showed in a pale brand chip (`bg-primary-50 text-primary-950`) — the 30-day readiness
  counts, the measured B2B share against the declared one, or the B2B sales and the cash held per
  month — and up to three access keys (monospace, wrapping). Never a file or a path.
- **Formatting is Fiscal's** (`useFiscalFormat` from `@FiscalModule`), sector and regime labels
  `fiscal.*`, band labels `diagnostic.bands.*`, specialty labels `partners.specialties.*` — one-way
  imports into this module; nothing imports Radar but the router and the rail.
- **Motion, sparingly**: `motion-enter` on the cards, the opened detail and its lines, `v-count-up`
  on the clients KPI; all off under reduced motion (`motion.css`). State words sit in their tint
  (`bg-success-50 text-success-800` Oportunidade, `bg-warn-50 text-warn-900` Risco and "Sem
  autorização"), checked at 4.5:1 in both schemes by `e2e/radar/radar.spec.ts`.

## Known Gaps

- **The screen does not poll**: a batch sent from another session shows on the next load (the API
  still refuses a raced pair, 409 `already_referred`).
- **One batch per click, per partner**: there is no "send to every covering partner" nor a choice
  of clients inside a batch — the demo's shape (R27).
