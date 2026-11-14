---
version: 1.2.0
origin: rnc
---

# RULES — Metrics Module

> For AI agents. rnc-1 F7, 07/11/2026.

## Domain

Reinaldo's funnel (R24): `/gestao/metricas` (route `metrics`, `/management/metrics` its `en` twin) —
the seven steps (visitas → clique CTA → início do diagnóstico → contato capturado → resultado →
agendou → cliente) with the drop-off, a table by source and one by sector page, over the last 7 or
30 days. API `GET metrics/funnel?days=7|30` (api `Metrics` module). Reinaldo's rail landing
(`management-home`) redirects here.

## Intentional Decisions

- **Permission (AM8)**: `metrics.read` — Reinaldo and the operator; the team has no Métricas in
  the rail and the route bounces it (the API answers 403 as well).
- **The API counts, the screen formats.** Counts and the drop-off come computed (a cohort: each step
  counts only who passed the one before, so a step never exceeds the one before); the screen only
  draws bars against the top of the funnel and formats numbers and shares in the person's locale.
  Never re-derive a step on the client.
- **The toggle asks the API again** (7 or 30, `METRICS_WINDOWS`), keeps the current numbers on
  screen dimmed while the new ones load, and opens on 30 days, like the demo.
- **Plain HTML bars, not a chart library** (`MFunnelChart`): seven numbers read as an ordered list
  by a screen reader; the bar itself is `aria-hidden`.
- **Tables scroll inside their own frame** (`MFunnelTable`, `w-0 min-w-full overflow-x-auto`), so
  the page never scrolls sideways at 390 px.
- **Sources are labelled here** (`metrics.sources.*`, the four of W15); sector rows carry the API's
  label (the firm's own sector pages).

- **"Por canal" starts at "início"** (rnc-2 R14): before the diagnostic starts a visitor has no
  lead and so no channel; its last column is the share of the channel's starts that booked.

## Known Gaps

- No export (CSV/PDF) and no custom date range — out of Phase 1.
