---
version: 1.2.0
origin: rnc
---

# RULES — Repricing Module

> For AI agents. rnc-1 F8, 07/11/2026.

## Domain

Reinaldo's fee repricing (R25–R26): `/gestao/reajuste` (route `repricing`, `/management/repricing` its
`en` twin) — the yearly uplift the Reforma's extra work is worth over Franco's portfolio, the
spread by band of percent, the sector and regime filters, the client list, one client's items and
"Gerar carta de reajuste", the batch mark and the CSV import. The letter is
`/gestao/reajuste/:id/carta` (route `repricing-letter`). API: the `Portfolio` module
(`GET repricing`, `GET repricing/{id}`, `GET repricing/{id}/letter`, `POST repricing/mark`,
`POST clients/import`, `GET clients/import/template`).

## Intentional Decisions

- **Permissions (AM8)**: the screens need `repricing.manage` (gestao, operador); the team has no
  Reajuste in the rail and the routes bounce (the API answers 403 as well). "Importar CSV" shows
  only with `clients.import` — a missing key hides the button, never grants it.
- **The API quotes, the screen formats.** Every number — the suggested fee, `pct`, the uplift, the
  hero total (Σ (suggested − current) × 12 of exactly the listed rows) and the spread — comes
  computed (`RepricingCalculator`). Money arrives in integer cents and goes through `formatReal`.
  Never re-derive a quote here.
- **A filter asks the API again** and clears the selection (the demo's rule: what was chosen under
  another filter is not on screen any more); the open client stays open only if it is still listed.
- **The batch mark updates in place**: the answer's rows replace theirs, the "Marcados" count grows
  by the rows that were not marked yet, the selection clears. The import reloads the list instead —
  it can touch every row.
- **The letter is a page of its own, outside `TheLayout`**, so printing prints the letter alone:
  the toolbar is `print:hidden`, the sheet drops its shadow, radius and padding on paper. It reads
  `GET repricing/{id}/letter`; a 404 is "not in the portfolio", anything else "did not load" with a
  retry.
- **Import errors are translated from their codes** (`repricing.import.codes.*` for the file,
  `repricing.import.lineCodes.*` per line, `repricing.import.fields.*` for the column), never from
  the API's sentence. The service forwards the error (`forwardError`) — a refused file is the
  form's to explain, not an unexpected failure to log.
- **The template is the API's** (`GET clients/import/template`), saved through a Blob as
  `modelo-clientes.csv`; the columns and accepted values are also printed in the dialog. The 8th,
  `autoriza_parceiros` (rnc-2 R28), is optional on the API: a 7-column file still imports.
- **The table scrolls inside its own frame, both ways** (`w-0 min-w-full overflow-auto`, a sticky
  header, `max-h-[38rem]`), so 500 rows and a 390 px screen never scroll the page sideways. Below
  `2xl` the detail sits under the list (beside it at 1440 the table was cut to 526 px of 669) and opening a client scrolls to it.
- **Checkboxes are native inputs inside a `touch-target` label** (celer-06: a hand-written control
  opts in), with the client's name as the screen-reader label.

## Known Gaps

- **No pagination or search**: the whole filtered portfolio is one answer (right for ~500 clients).
- **The letter is printed, not sent**: e-mailing it to the client is a later phase.
