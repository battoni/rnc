---
version: 1.3.0
origin: rnc
based-on: 1.3.0
---
# RULES — Radar Module

> For AI agents. rnc-2 F9, 07/11/2026 (the Radar API); F10 (the row's partner picker and the batch referral); rnc-3 F7 (`firstOpportunity` for the briefing).

## Domain

"Radar da carteira" (rnc-2 R24–R27): Diagnostic's `RulesEngine` run over every client of the
firm's portfolio, each read with the evidence its NF-e XMLs give (D9). No table of its own: it
reads Portfolio's `clients` and Fiscal's `nfe_documents`, and uses Fiscal's `SplitPaymentCalculator`.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `GET radar` | `radar.manage` | `{clients, with_opportunity, total_cents: [lo, hi], risk_types, types: [row]}`; a row `{rule, kind, title, citation, clients, without_consent, range_cents: [lo, hi], period, partners: [{id, name, specialty, batch, sent}]}`, `kind` ∈ `oportunidade · risco`, `period` `ano` (or `mes` for `split-payment`) |
| `GET radar/types/{rule}` | `radar.manage` | `{type: row + detail, clients: [{id, name, cnpj, sector, regime, band, b2b_share, partner_consent, range_cents, evidence, keys}]}`; `{rule}` a `DiagnosticRule` value, else 404 `radar.notFound`; a known rule no client has → `clients: []` |
| `POST radar/referrals` | `radar.manage` | `{rule, partner_id}` → 200 `{sent, skipped_no_consent, skipped_already}`; an unknown rule or a partner not active in the firm 422 on the field; a partner whose specialty does not cover the rule 422 `partner_not_covering`; a raced pair 409 `already_referred` |

- **Modules (rnc-4 F3a, R17, R19, D16):** every route is `module:radar`; the batch referral is
  `module:radar,partners`. Without `fiscal` no invoice is read — every client from the cadastro, no
  evidence, no access key; without `partners` every row's `partners` picker is empty. Tested by
  `RadarDegradeTest`.

## Intentional Decisions

- **Every client, every rule (R24)**: no regime filter — the 13 rules of `DiagnosticRule` decide. A
  client's profile is `Client::profile()` (ICMS incentives and export false, rnc-1 C11) with the
  measured B2B share when D9 applies.
- **D9 (`EvidenceBuilder::MIN_DOCUMENTS` = 10)**: the share measured BY VALUE from the issued
  invoices of the last 90 days (to a CNPJ ÷ all, half-up to a whole percent) replaces the declared
  one only from 10 such invoices; fewer, or a total of R$ 0, keep the declared share.
- **Ranges in integer cents**: the engine's reais × 100 rounded; a year for every rule but
  `split-payment`, whose range is R23's cash held PER MONTH (`SplitPaymentCalculator`): from the
  invoices whenever the client issued any in the 90 days (none to a CNPJ is zero held — Fiscal W80),
  else from the cadastro. Its row is the Σ of those (R26). The total adds opportunities only.
- **Order**: opportunities first, then the biggest range's top, then the engine's own order; a
  type's clients biggest top first, ties by id.
- **Proof (R25)**: `evidence.source` `notas` or `cadastro` ("do cadastro"). `notas` for `erp-nfe`
  with the 30-day readiness counts (`window_days`, `invoices`, `complete`, `at_risk`); for the three
  `simples-*` rules with the measured share (`documents`, `b2b_share`, `declared_b2b_share`); for
  `split-payment` with `monthly_b2b_cents` and `held_per_month_cents` (the cadastro case carries the
  same two, from the band). Every other rule is `cadastro`.
- **Keys**: up to three access keys per client with a `notas` proof — `erp-nfe` the incomplete
  invoices of the 30 days first (nothing older), `simples-b2c` sales to consumers first, every other
  rule sales to a company first; newest first within each. Never the file or its path.
- **Consent (R28)**: `without_consent` counts the row's clients whose `partner_consent` is not
  true (null = never answered = no); every client row carries `partner_consent`. Nothing here is
  partner-facing; the batch (F10) skips those clients in Partner's `referBatch`.
- **The batch is Partner's (F10, R27)**: `RadarService::referBatch` only hands Partner the rule's
  clients in this screen's order (the range's top, ties by id) with the profile it read (the
  measured share when D9 applies, so the matcher covers the rule the batch is for); Partner picks
  the 10, checks the partner and writes (Partner RULES → "The Radar's batch referral"). Each row's
  `partners` is Partner's `portfolioBatchOptions` over the same order, so the screen's "Encaminhar
  os N maiores" is the API's N.
- **No N+1 (plan AM6)**: `GET radar` is the clients (one query) + ONE sales aggregate grouped by
  client + the pickers' three (partners, portfolio leads, their referrals — F10); `GET
  radar/types/{rule}` adds one keys query for the listed clients. `RadarSummaryTest` counts the
  queries for 4 and 24 clients (≤ 8 and ≤ 9), `RadarBatchReferralTest` once leads and referrals exist. Measured on the seed: `state.md` → F9.
- **`firstOpportunity()` is the briefing's** (rnc-3 F7, W100): the row `summary()` puts first among
  the opportunities, built from the same items, rule rows and `compareTypes` order, but without each
  row's clients ranked and without the pickers (`partners: []`) — two queries less, and the ranking's
  comparator, which reads Eloquent attributes ~25 000 times on 500 clients, never runs. A change to
  `summary()`'s order is a change here too; `BriefingTest` holds the two equal.

## Tables read from other modules

`clients` (Portfolio, through `ClientRepository::findAllClients`); `nfe_documents` (Fiscal, through
`NfeDocumentRepository::sumNfeDocumentSalesByClient` and `findNfeDocumentSalesKeysByClients`).
Code reused: Diagnostic's `RulesEngine`, `DiagnosticRule`, `DiagnosticProfileDTO`; Fiscal's
`SplitPaymentCalculator` and `ReadinessService::WINDOW_DAYS`. The batch request's `exists` reads Partner's `partners` (an active partner of the firm) and reuses Partner's `partners.routing.partnerUnavailable` message. Writes nothing itself: the batch's leads, consents, referrals and timeline lines are written by Partner's `ReferralService` (through Lead's `LeadService`).

## Known Gaps

- **Quoted per request, unpaginated**: right for Franco's ~500 clients (measured in `state.md`);
  tens of thousands need a stored snapshot.
- **The rates are estimates** (Fiscal's Known Gaps: 26,5 %, 40–60 % settled) and the engine's
  shares are the demo's: every figure is shown as a range and an estimate.
- **ICMS incentives and exports are never known** from the CSV, so `icms-incentivos` and
  `exportacao` never appear (rnc-1 C11).
