---
version: 1.1.0
origin: rnc
---
# RULES — Portfolio Module

> For AI agents. rnc-1 F8, 07/11/2026.

## Domain

The firm's client portfolio and Reinaldo's fee repricing (R25–R26): the companies Franco already
serves, imported from a CSV (or seeded locally), each quoted by the same rules engine as the
public diagnostic — how much new work the Reforma brings to that client, as a suggested new monthly
fee — with the letter that proposes it and the batch mark. It owns `clients`.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `GET repricing?sector=&regime=` | `repricing.manage` | `{summary: {clients, current_yearly_cents, uplift_total_cents, uplift_share, average_pct, marked}, spread: [{from, to, count}], clients: [row]}`, biggest yearly uplift first; unpaginated. A row: `{id, name, cnpj, sector, regime, band, b2b_share, current_cents, suggested_cents, pct, uplift_cents, opportunities, risks, repricing: {at, by, by_name, pct, suggested_cents} \| null}` |
| `GET repricing/{id}` | `repricing.manage` | the row plus `items: [{id, kind, title, rule, detail}]` |
| `GET repricing/{id}/letter` | `repricing.manage` | `{client, current_cents, suggested_cents, pct, items: [{kind, title}], firm: {name, address, phone, mailbox}, issued_on, effective_from}` |
| `POST repricing/mark` | `repricing.manage` | `{client_ids}` → `{marked, clients: [row]}`; each client stores `{at, by, pct, suggested}` |
| `POST clients/import` | `clients.import` | multipart `file` → `{created, updated, total}`; 422 with `code` (below) |
| `GET clients/import/template` | `clients.import` | `modelo-clientes.csv`: a BOM and the header row |

## Intentional Decisions

- **The rule is the demo's `repricing()` (W32/W43 of battonidev-1)**, in `RepricingCalculator`:
  1,5 points per opportunity and 2,5 per risk of `RulesEngine::diagnose()`, clamped to 4–25 %; the
  fee rounds to R$ 50, floored to R$ 50 above 25 % and ceiled below 4 %; `pct` is read from the
  ROUNDED fee, everywhere (list, detail, letter, mark). `uplift = (suggested − current) × 12`, and
  the list's `uplift_total` is the sum over exactly the filtered rows (A14).
- **Money travels as integer cents** (`*_cents`, the SPA's `formatReal`); the table and the mark
  keep reais (`monthly_fee` decimal, `repricing.suggested`), converted once in the resource.
- **The quote is computed on every read, never stored**: it follows the engine and the profile as
  they are today. Only the mark is a snapshot — `{at (UTC ISO-8601), by (user id), pct, suggested}`
  copied onto `clients.repricing` (arcus "What was agreed is copied onto the row"). Marking again
  overwrites it; a re-import keeps it.
- **A client's profile is the CSV's**: sector, regime, band and B2B share. The CSV has no ICMS
  incentive or export column, so both are false for every client (fewer items than a full quiz).
  A client's regime is Simples, Presumido or Real — "não sei" is the quiz's.
- **The import is all or nothing (A13)**: `ClientCsvParser` reads the whole file and collects every
  problem of every line before anything is written; then `ClientImportService` writes in one
  transaction. Codes on the 422: `import_encoding` (not UTF-8), `import_header` (+ `missing`),
  `import_empty`, `import_too_many` (+ `limit`, 5000 rows), `import_invalid` (+ `errors:
  [{line, field, code}]`, at most 100, and `total_errors`). Line codes: `required`, `name_too_long`,
  `cnpj_invalid`, `cnpj_duplicate` (a CNPJ repeated in the file), `sector_invalid`,
  `regime_invalid`, `band_invalid`, `b2b_invalid`, `fee_invalid`, `columns`. The line is the file's
  (the header is line 1); the SPA translates the codes.
- **The file as people write it**: a BOM is dropped; the delimiter is the header's (`;` or `,`);
  columns in any order, extras ignored; sector and regime by slug or label ("Indústria",
  "Lucro Real"); share `80` or `80%`; fee `1850`, `1.850,00` or `1850.50`, an optional `R$`.
- **The smallest fee is R$ 250**: the 4–25 % range of a smaller fee may hold no multiple of R$ 50,
  and the rule could not keep its promise.
- **Upsert per firm**: one row per `(firm_id, cnpj)`; a CNPJ already in the firm is updated in place.
- **Another firm's client is a 404 or absent** (`BelongsToFirm`, A1); `POST repricing/mark` asks
  each id against the current firm (422 on `client_ids.N`).
- **The local seed is invented** (`ClientSeeder`, refuses production): 500 clients, deterministic
  (Mt19937 seeded 2027); fees by band × regime ±10 % in R$ 900–9.000 (demo W27). Every CNPJ passes
  the check digits but is plainly not real: root `999` + the client's number on five digits, branch
  `9999` (`999.00NNN/9999-DD`). It is never looked up.

## Read by other modules

Fiscal (rnc-2 F6a) matches each NF-e to a client by CNPJ through `ClientRepository::findClientIdsByCnpjs`
— read only; a change to `clients.cnpj` (14 digits, unique per firm) is a change for Fiscal too.

## Tables read from other modules

`users` (the name of who marked, through `ClientRepository::findUserNamesByIds`); `firms` (the
letter's signature and contact, through `CurrentFirm`). Code reused: Diagnostic's `RulesEngine`,
`DiagnosticProfileDTO` and its `Sector`/`Regime`/`RevenueBand` enums.

## Known Gaps

- **The list is unpaginated and quoted per request**: right for Franco's ~500 clients; a portfolio
  of tens of thousands needs the quote stored per client and the list paged.
- **The letter is not sent**: it is a printable page; e-mailing it to the client is a later phase.
- **No client screen beyond repricing**: no edit or delete of a client in the back office; the CSV
  is the way in.
