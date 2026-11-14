---
version: 1.6.0
origin: rnc
based-on: 1.6.0
---
# RULES — Portfolio Module

> For AI agents. rnc-1 F8, 07/11/2026; rnc-2 F7 (the client's timeline), 07/11/2026; rnc-2 F9 (the client's partner consent), 07/11/2026; rnc-3 F6a (the contract alert), 07/11/2026; rnc-3 F6b (consent and the legal referral on the alert), 07/11/2026.

## Domain

The firm's client portfolio and Reinaldo's fee repricing (R25–R26): the companies Franco already
serves, imported from a CSV (or seeded locally), each quoted by the same rules engine as the
public diagnostic — how much new work the Reforma brings to that client, as a suggested new monthly
fee — with the letter that proposes it and the batch mark. It owns `clients` and, from rnc-2 F7,
`client_events` — a client's timeline (D12): the team's "Registrar aviso" (`nfe.aviso`) and "Propor
revisão" (`fornecedores.revisao`), each with who and when. From rnc-3 F6a it owns
`client_contracts` — the clients' contracts and Reinaldo's contract alert (R17–R19, R22): the ones
that cross the 2027–2033 transition without a Reforma price clause, by exposure.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `GET repricing?sector=&regime=` | `repricing.manage` | `{summary: {clients, current_yearly_cents, uplift_total_cents, uplift_share, average_pct, marked}, spread: [{from, to, count}], clients: [row]}`, biggest yearly uplift first; unpaginated. A row: `{id, name, cnpj, sector, regime, band, b2b_share, current_cents, suggested_cents, pct, uplift_cents, opportunities, risks, repricing: {at, by, by_name, pct, suggested_cents} \| null}` |
| `GET repricing/{id}` | `repricing.manage` | the row plus `items: [{id, kind, title, rule, detail}]` |
| `GET repricing/{id}/letter` | `repricing.manage` | `{client, current_cents, suggested_cents, pct, items: [{kind, title}], firm: {name, address, phone, mailbox}, issued_on, effective_from}` |
| `POST repricing/mark` | `repricing.manage` | `{client_ids}` → `{marked, clients: [row]}`; each client stores `{at, by, pct, suggested}` |
| `POST clients/import` | `clients.import` | multipart `file` → `{created, updated, total}`; 422 with `code` (below). The optional 8th column `autoriza_parceiros` (rnc-2 R28): `sim` / `não` in any case, with or without the accent, or empty; anything else a line error `consent_invalid` |
| `GET clients/import/template` | `clients.import` | `modelo-clientes.csv`: a BOM and the header row, `autoriza_parceiros` last |
| `GET clients/{id}/events` | `fiscal.read` | the client's timeline, newest first: `[{id, client_id, type, data, at, user: {id, name} \| null}]`; 404 for another firm's or an unknown client. Behind Fiscal's key: the fiscal screens are the only ones that write it so far |
| `GET contracts` | `contracts.manage` | `{summary: {at_risk, total, annual_value_cents, exposure_cents, referred}, contracts: [{id, client: {id, name, partner_consent, partner_consent_via}, referred_to: [{id, name}], kind, annual_value_cents, start_year, end_year, years_exposed, exposure_cents}]}` — ending 2027 or later without the clause, exposure highest first, ties by id; unpaginated |
| `POST contracts/import` | `contracts.manage` | multipart `file` (≤ 2 MB, `.csv`/`.txt`, ≤ 5000 lines; 10 a minute, `contracts-import`) → 200 `{created, updated, total, errors: [{line, field, code}], total_errors}`; 422 with `code` for a file refused whole |
| `GET contracts/template` | `contracts.manage` | `modelo-contratos.csv`: a BOM and the header row |
| `POST contracts/{id}/clause` | `contracts.manage` | "Cláusula incluída" → `{id, has_reforma_clause, clause_marked_at, clause_marked_by}`; 404 `portfolio.contractNotFound` for another firm's or an unknown contract |

- **Modules (rnc-4 F3a, R17, R19):** repricing, the client import and the client timeline are
  `module:portfolio`; the contract alert is `module:contracts`, and its legal picker and referral
  `module:contracts,partners`. Without `partners` the alert answers no referral field — no client
  consent, no `referred_to`, no `summary.referred` — and the referrals' query is not run. Tested by
  `ContractDegradeTest`.

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
- **The timeline has ONE writer (rnc-2 F7, D12)**: `ClientService::recordEvent(Client, ClientEventType,
  data, ?userId)` — Fiscal's notice and review call it, never an insert of their own (arcus "a module
  never writes another's tables"). It stamps the client's own `firm_id` (not `BelongsToFirm`'s
  `creating` event, silenced under `WithoutModelEvents`) and `at` in UTC — passed already in UTC,
  because a class cast read back on the created model is the value SET, zone kept. `type` is a
  `ClientEventType` value, a plain string column (AM11). A line is never edited or deleted; the same
  line may be written twice (a second notice is a second warning). The contract alert's "Cláusula incluída" writes `contrato.clausula` the same way.
- **Contracts: exposure (rnc-3 R19, AM7, AM17)**, in `ContractExposure`: years =
  `min(end, 2033) − max(start, 2027) + 1`, floor 0 (2030–2033 = 4, 2025–2035 = 7); exposure = annual
  value × years, integer cents. The alert lists `end_year ≥ 2027` without the clause — a contract
  starting after 2033 is still listed, at exposure 0, last (W80). Four queries (at-risk rows, their
  clients, the count, their referrals); the ranking is PHP over the at-risk rows. Each row carries
  its client's `partner_consent` and `partner_consent_via` (the "Encaminhar ao jurídico" gate, R20 —
  the SPA writes the reason) and `referred_to` — the partners it was sent to, in send order ("Com
  <parceiro>"); `summary.referred` counts the at-risk contracts with a referral (rnc-3 F6b). The
  referral itself is Partner's (`POST contracts/{id}/referrals`, Partner RULES): Portfolio only reads
  `referrals`/`partners`, so the dependency stays Partner → Portfolio.
- **Contracts: the CSV (rnc-3 R18)** `cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma`, read
  by `CsvReader` (BOM, UTF-8, `;`/`,`, header in any order — the clients CSV's rules; whole-file
  refusals reuse its `import_*` codes and `portfolio.import.*` keys) and `ContractCsvParser`. UNLIKE
  the clients CSV, lines are independent: good lines are written (one transaction), bad lines
  reported — `client_unknown` (no client OF THIS FIRM has the CNPJ; check digits not required),
  `kind_invalid`, `value_invalid`, `year_invalid` (2000–2100), `end_before_start`, `clause_invalid`,
  `columns`; at most 100 listed, `total_errors` counts all. Kind by slug or label, any case and accent;
  value `100000`, `1.200.000,00`, `R$ 80.000,50`, `95000.00` read as text into cents (never a float),
  1 cent to R$ 1 bi; clause `sim`/`não` any case and accent, or empty.
- **Contracts: the key and the clause (R18, AM16, W77)**: one row per `(firm, client, kind, start,
  end)`; the same key updates the value. Empty clause = "não" on a new row, keep what is stored on
  an update. A contract marked through "Cláusula incluída" (`clause_marked_at`) keeps its clause
  whatever a re-import says, "não" included — the mark is the record that the clause was signed.
  **Only the button protects** (review A3, W114): a "sim" from a file stamps no `clause_marked_at`,
  so the next file may correct it to "não" — there is no unmark, and stamping on import would make a
  file's typo permanent.
- **Contracts: "Cláusula incluída" (R22, W79)** sets the clause, `clause_marked_at` (UTC) and
  `clause_marked_by`, and writes one `contrato.clausula` line (`{contract_id, kind, end_year}`) through
  `recordEvent`, in one transaction on the locked row. Marking a marked contract answers it as it is
  and writes nothing. There is no unmark (Known Gap).
- **Contracts: no formula can come back out**: nothing of a CSV cell is stored as text — a client id,
  a kind slug, cents, years and a boolean. The template is a constant header. Any later export of
  contracts or clients must still prefix a cell starting `=`, `+`, `-`, `@`, tab or CR with `'`.

- **The client's partner consent (rnc-2 R28)** lives on the client (`partner_consent` true · false ·
  null, `partner_consent_at` UTC, `partner_consent_via` — only `contrato`, `Client::CONSENT_VIA_CONTRACT`,
  the partner clause of the services contract). Null means never answered and counts as NO for
  anything partner-facing (the Radar's "sem autorização", F10's batch). The CSV writes it only when
  the header has `autoriza_parceiros`: a 7-column re-import leaves it; an empty cell clears all
  three; an answer that did not change keeps its first moment, a changed one is stamped with the
  import's. Values are read with `Str::ascii` + lower case (`sim`, `SIM`, `nao`, `NÃO`, `Não`; plan
  AM11). The seed gives ~85 % yes, the rest no, all `via contrato` (a second seeded generator, so
  rnc-1's profiles and fees are unchanged).

## Read by other modules

Partner (rnc-3 F6b) reads a contract with its client through `ContractRepository::findContractWithClientById`
(the legal referral), and a referral's contract through `Referral::contract` (the portal's summary:
kind, end year, clause, value — banded); `ContractKind::label()` names the kind in the message.

The Radar (rnc-2 F9) reads the whole portfolio through `ClientRepository::findAllClients`, each
client's `profile()` and `partner_consent` — read only.

Fiscal (rnc-2 F6a) matches each NF-e to a client by CNPJ through `ClientRepository::findClientIdsByCnpjs`
— read only; a change to `clients.cnpj` (14 digits, unique per firm) is a change for Fiscal too.
Fiscal (rnc-2 F7) lists the Presumido/Real clients through `ClientRepository::findClientsByRegimes`
and reads and writes the timeline through `ClientService` (`recordEvent`, `clientIdsWithEvent`).

## Tables read from other modules

`referrals`, `partners` (Partner — a contract's legal referrals and the partner's name, through
`ContractRepository::findReferredPartnersByContract`, rnc-3 F6b; written only by Partner's
`ReferralService::referContract`); `users` (the name of who marked, through `ClientRepository::findUserNamesByIds`; who wrote a
timeline line, through `ClientEvent::user`); `firms` (the
letter's signature and contact, through `CurrentFirm`). Code reused: Diagnostic's `RulesEngine`,
`DiagnosticProfileDTO` and its `Sector`/`Regime`/`RevenueBand` enums.

## Known Gaps

- **The list is unpaginated and quoted per request**: right for Franco's ~500 clients; a portfolio
  of tens of thousands needs the quote stored per client and the list paged.
- **The letter is not sent**: it is a printable page; e-mailing it to the client is a later phase.
- **No client screen beyond repricing**: no edit or delete of a client in the back office; the CSV
  is the way in.
- **No unmark of a contract clause**: a mistaken "Cláusula incluída" is fixed in the database;
  a re-import cannot undo it on purpose (AM16).
- **Two imports racing on the same new contract key**: the second insert meets the unique index
  and fails (500) — unlikely with one Reinaldo and 10 imports a minute per user.
- **`ClientCsvParser` still reads the CSV with its own copy** of `CsvReader`'s rules; it moves onto
  `CsvReader` after rnc-2's consent column lands in it (W76), to spare that merge a conflict.
