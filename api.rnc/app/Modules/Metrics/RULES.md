---
version: 1.1.0
origin: rnc
---
# RULES — Metrics Module

> For AI agents. rnc-1 F7, 07/11/2026.

## Domain

Reinaldo's funnel (R24, A4): how many visitors of the last 7 or 30 days reached each step —
visitas → clique CTA → início do diagnóstico → contato capturado → resultado → agendou → cliente —
with the drop-off from the step before, a table by source and a table by sector page. Owns no
table: it reads Tracking's and Lead's.

## The route (plan AM8)

| Route | Permission | What |
| --- | --- | --- |
| `GET metrics/funnel?days=7\|30` | `metrics.read` (ceo, operador) | `{days, from, to, steps: [{key, count, drop_off}], by_source: [{source, visitas, cta, contato, agendou, cliente}], by_sector: [{slug, label, visitas, cta, contato, agendou}]}`; no `days` = 30, anything else 422 |

The team (`equipe`) gets 403 — tested in `tests/Feature/Metrics/FunnelTest.php` (A2's metrics part).

## How a step is counted (`FunnelService`)

- **A cohort, not totals.** A visitor counts at a step only when it reached every step before it
  inside the window, so each step is ≤ the one before by construction (the demo clamped totals
  instead). A CTA beacon with no page view, or a diagnostic begun without the CTA, does not count.
- **The unit is the visitor** with a `visit` in the window, counted once however many beacons it sent.
- **A step is a beacon or its lead's line.** `diag.start`/`diag.contact`/`diag.result`/`booking`
  (R7), or the matching line of the visitor's lead (`diagnostico.inicio`, `contato.capturado` — the
  lead reaching lead parcial —, `resultado.visto`, `reuniao.marcada`), so a beacon the browser lost
  does not lose the step. The visitor's lead is the one a beacon carried (`events.lead_id`, from the
  token, AM2) or the one started with its UUID (`leads.visitor_uuid`).
- **Cliente** is the visitor's lead moved to `cliente` by the team inside the window: an
  `etapa.alterada` line whose `data.to` is `cliente` (F5).
- **Source** is the visitor's first touch (`visitors.source`, W15); all four always listed.
- **Sector page** is the page of the visitor's first visit in the window when it is
  `/reforma/<slug>`; every sector page of the firm is listed, the landing's visits are in none.
- **Channel** (rnc-2 R14) is the channel the visitor's lead started in (`leads.channel`); a visitor
  with a chat lead among its leads counts under chat. Only the two public channels are rows, from
  "início" on — a visitor that never started a lead has no channel and is in neither row, so the
  two rows add up to the funnel's own início.
- **Drop-off** is the share of the step before that did not come through, one decimal; 0 when the
  step before is 0; null for the first step.

## Portable SQL (AM11)

The window is two Carbon bounds (`now - days`, `now`) in `whereBetween`; distinctness is `DISTINCT`
over plain columns; the JSON `to` goes through the builder's `data->to` (`json_extract` on SQLite
and MySQL alike); the visitor ↔ lead join is on (uuid, firm). The cohort itself is computed in PHP
over those few distinct rows — no window functions, no driver-specific SQL.

## Tables read from other modules

- `events`, `visitors` (Tracking) — the beacons, the first touch, the entry page.
- `leads`, `lead_events` (Lead) — the visitor's lead, its channel (rnc-2) and its timeline lines.
- `sector_pages` (Content), through `SectorPageService::list()` — the sector table's rows.

## Known Gaps

- **A bot can inflate the visits** (Tracking's gap): there is no bot filter, so the top of the
  funnel and its drop-off move with it.
- **The cohort is per window**: a visitor whose visit fell just before the window and who booked
  inside it counts nowhere in that window. Acceptable for a 7/30-day view; a "by first visit" mode
  is out of Phase 1.
- **Computed on every request.** Fine for a pilot (hundreds of visitors a month); at tens of
  thousands it wants a daily rollup table.
