---
version: 1.1.0
origin: rnc
based-on: 1.0.1
---

# RULES — Contracts Module

> For AI agents. rnc-3 F7, 07/11/2026 (the "Alerta de contratos" screen, its import and its legal referral).

## Domain

Reinaldo's contract alert (rnc-3 R18–R22): the clients' contracts that cross the 2027–2033
transition without a Reforma price clause, read by the API (api Portfolio module, F6a–F6b) —
`ContractsView` at `/gestao/contratos` (`/management/contracts`, route `contract-alert`), behind
`contracts.manage` (gestao; the operator holds every key), the rail child "Alerta de contratos" last
in Reinaldo's group. The screen imports the contracts CSV, sends one contract to a legal partner
and records "Cláusula incluída"; the API writes everything (api Portfolio RULES → the contract
routes, api Partner RULES → the legal referral).

## Public Barrel

`CONTRACTS_ROUTE_NAMES`, `CONTRACTS_PERMISSIONS` (the rail), `ContractsModuleRoutes` (the router),
the services, `CONTRACT_KIND` and the types. Nothing imports this module but the router and the rail.

## Intentional Decisions

- **Every figure is the API's.** The four KPIs (`summary`), each row's years exposed and exposure,
  the consent and the partners it went to come from `GET contracts`; after a referral, a clause or
  an import the view reads it again rather than patching a row (as the Radar and the ledger, W41).
- **The note carries the dated statement** (facts.md §2 "Everyone": a long contract across
  2027–2033 needs a price clause), with its source and the date it was read (05/11/2026).
- **The chips light the years the contract is exposed**, `max(início, 2027)` to `min(fim, 2033)` —
  the API's `years_exposed` drawn — not every year up to the end as the demo did (W103); a contract
  starting after 2033 lights none and says "Fora da transição". The exposure bar is the row's
  exposure against the first row's (the API ranks most exposed first), never under 2 %
  (`EXPOSURE_BAR_MIN_PERCENT`); it wipes in across its thickness (`motion-wipe-y`), never its width.
- **Consent is visible, never decided here** (rnc-2 R28): a client whose `partner_consent` is not
  `true` (null — never answered — counts as no) gets the demo's reason, the picker hidden and
  "Encaminhar ao jurídico" disabled (`aria-describedby` the reason). The API's 422
  `consent_required` is still said on the row should the list be stale.
- **The picker is the API's legal partners** (`GET contracts/legal-partners`: active, tax law first,
  then pricing), read once with the first alert so a row never flashes "Sem parceiro jurídico na
  rede." before they arrive; a failure leaves it empty and says exactly that. Once a contract went
  to any partner the row says "Com <parceiro>" (every name, in send order) instead of the picker —
  the demo's one-referral row (W104); the API would take a second partner (W95), the screen does not offer it.
- **The row owns its referral call** (`MContractRow`) and tells the view `onSent` (toast, reload) or
  `onStale` (409 `already_referred`, reload). Refusals by `code` (`CONTRACT_REFERRAL_ERROR_KEYS`)
  are said ON the row (`role="alert"`), as the Radar's.
- **"Cláusula incluída" asks on the row** (W101): the mark cannot be undone from the screen (api
  Portfolio Known Gap), so the row turns into "Registrar que o contrato … já tem a cláusula?" with
  "Registrar cláusula" / "Cancelar". Not a `ConfirmPopup`: the popup closes on the first scroll of
  the list, and a row below the fold (every row at 390 px) scrolled it away before the answer.
- **The import is partial, unlike the clients CSV** (R18): `MImportContractsForm` in the view's
  `MMainDialog` (named a Form, not the plan's `MContractImportDialog`: celer-07's form-in-dialog
  contract — W102). A 200 with `total_errors > 0` keeps the dialog open with "N contratos gravados …"
  and each line left out ("Linha 3 · CNPJ do cliente: nenhum cliente da Franco tem este CNPJ"); a clean
  import closes it with a toast; a file refused whole (422 `import_*`) says why. Every 200 reloads
  the alert behind the dialog. The template is the API's (`GET contracts/template`), saved as
  `modelo-contratos.csv`.
- **Labels**: kinds `contracts.kinds.*`, specialties `partners.specialties.*`; money in full
  (`formatReal`) — the alert is Reinaldo's, who may see exact values (only the partner sees a band,
  api Partner RULES / Portal RULES).
- **Motion, sparingly**: `motion-enter` on the cards and on the clause question, `v-count-up` on
  "Encaminhados ao jurídico", the bar's wipe; all off under reduced motion (`motion.css`). State
  words sit in their tint (`bg-warn-50 text-warn-900` lit years, `bg-success-50 text-success-800`
  "Com <parceiro>"), checked at 4.5:1 in both schemes by `e2e/contracts/contracts.spec.ts`.

## Known Gaps

- **The screen does not poll**: a referral or a clause from another session shows on the next load
  (the API still refuses a raced pair, 409 `already_referred`, and the row reloads).
- **No unmark and no second partner from the screen** (api Portfolio Known Gap; W95).
