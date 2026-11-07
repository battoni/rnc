---
version: 1.6.0
origin: rnc
based-on: 1.6.0
---
# RULES — Partner Module

> For AI agents. rnc-1 F6, 07/11/2026; rnc-2 F1 (partner access, the portal's first route), F2
> (the portal's inbox, the moves along the graph, the SLA), F4 (the ledger); rnc-3 F6b (the legal referral of a contract).

## Domain

The firm's network (R21–R23): the partner registry, the routing score of each partner against a
lead's diagnostic, the referral — Franco recommending one lead to one partner, only with the lead's
consent — and the trail of every referral. It owns `partners` and `referrals`; the lead's timeline
line goes through `LeadService::recordReferral` (Lead owns `lead_events`), and each move the partner
makes from its portal through `LeadService::recordReferralStatus`. From rnc-2 it also owns a
partner's portal login — `partners.access_*_at` and `partner_access_events` — and the portal's
`CurrentPartner` scope; the login's `users` row is written by Auth's `LinkedAccountService`.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `GET partners` | `partners.manage` | every partner of the firm, active first, then by name (unpaginated: a firm's network is a few dozen) |
| `POST partners` | `partners.manage` | `{name, specialty, email, phone?, city?}` → the partner (201) |
| `PATCH partners/{id}` | `partners.manage` | any field, or `{active: false}` to deactivate → the partner as it is now |
| `GET partners/options` | `referrals.read` | `[{value, label, active}]`, inactive too — the trail's filter keeps the history |
| `GET leads/{id}/partner-matches` | `leads.read` | `{can_refer, blocked_reason, matches: [{partner, score, covers, referral}]}`, best first |
| `POST leads/{id}/referrals` | `leads.update` | `{partner_id, message}` → `{referral, event}` (201); 422 `code: consent_required`, 409 `code: already_referred` |
| `GET referrals?partner_id=&page=&per_page=` | `referrals.read` | the trail, newest first, paginated; a row says its `contract_id` (null but for a contract referral) |
| `GET contracts/legal-partners` | `contracts.manage` | rnc-3 R20: `[{id, name, specialty}]` — the active `advocacia_tributaria` partners, then `precificacao_controladoria`, each by name; `[]` without them |
| `POST contracts/{id}/referrals` | `contracts.manage` | rnc-3 R20: `{partner_id}` → the referral (`ReferralResource`, 201); 422 on `partner_id` (inactive, another firm's), 422 `code: partner_not_legal`, 422 `code: consent_required`, 409 `code: already_referred`; 404 `portfolio.contractNotFound` for another firm's or an unknown contract |
| `POST partners/{id}/access` | `partners.manage` | `{username, email}` → the login with `password`, once (201); 409 `code: partner_access_exists` / `partner_inactive` |
| `POST partners/{id}/access/password` | `partners.manage` | a new `password`, once; the login's sessions end; 404 `code: partner_access_missing` |
| `DELETE partners/{id}/access` | `partners.manage` | the login deactivated, its sessions ended → the access as it is now; 404 `partner_access_missing` |
| `GET portal/me` | `portal.access` + `portal` | `{partner: {id, name, specialty}, firm: {name, slug}}` |
| `GET portal/referrals` | `portal.access` + `portal` | the partner's referrals (`PortalReferralResource`), open before closed, newest first; unpaginated (a partner's few dozen) |
| `GET portal/referrals/{id}` | `portal.access` + `portal` | one of them; anybody else's, or no referral, 404 |
| `POST portal/referrals/{id}/transition` | `portal.access` + `portal` | `{to, meeting_at? (reuniao, future), deal_value_cents? (fechado, > 0)}` → the referral as it is now; 422 `code: invalid_transition` off the graph |
| `GET portal/statement?month=YYYY-MM` | `portal.access` + `portal` | the partner's month (`PartnerStatementResource`): `{month, model, firm: {name}, commission_rate_bp \| monthly_fee_cents, lines: [{kind: subscription\|closing, referral_id, company, closed_at, deal_value_cents, amount_cents}], deals, deal_total_cents, total_cents}`; no month = this one in São Paulo |
| `GET ledger?month=YYYY-MM` | `ledger.read` | every partner's month (`NetworkStatementResource`): both rules, `partners: [{partner, deals, deal_total_cents, commission_cents, subscribed, subscription_cents, due_cents}]`, `total_cents` and `other: {model, total_cents}` |
| `PATCH ledger/model` | `partners.manage` | `{model: comissao\|assinatura}` → `{model, commission_rate_bp, monthly_fee_cents}` (`LedgerModelResource`) |

## Intentional Decisions

- **The score is the plan's Default, not the demo's jitter**: `PartnerMatcher` — 70 for one covered
  item + 11 per extra, capped at 97; a partner covering nothing gets 12–28 from
  `crc32("{partner}:{lead}") % 17`, fixed per pair. Which items a specialty covers is
  `PartnerSpecialty::covers()` (the demo's `PARTNER_RULES`). Only active partners are scored.
- **Eight specialties, from facts.md §3** (ESG left out as "low relevance"). The demo's later ninth,
  "BPO Financeiro" (split payment), is not a specialty here: facts.md folds BPO into "Precificação e
  controladoria", and the split payment is covered by "Crédito e capital de giro" (W42).
- **No consent, no referral (R22, AM12)**: the latest `lead_consents` row must have `given = true`.
  The matches say why not (`consent_missing` — never answered, `consent_refused` — said no) so the
  panel disables the buttons with the reason; the referral itself throws
  `ReferralException::consentRequired` (422, `code`). The SPA branches on the code.
- **What was agreed is copied onto the row**: `consent_text`, `consent_version`, `consent_given_at`,
  the `score` and the `covers` at the moment of the referral — the trail shows what the lead
  agreed to then, whatever the terms say later.
- **One referral per lead and partner** (unique pair): a second is 409 `already_referred`; the
  unique index answers a race the same way.
- **The lead stays Franco's**: the stage never moves; `LeadService::recordReferral` writes one
  `encaminhamento.enviado` line `{referral_id, partner_id, partner, specialty, score}` with the user
  and touches the lead.
- **The partner's e-mail never carries the lead's contact** (R22): `ReferralSent` (queued, AM6)
  carries a `ReferralMailDTO` of values — partner, specialty, company, sector, the covered items,
  the message, who sent it and Franco's phone, mailbox and address — and replies go to the firm's
  mailbox. No phone, e-mail or contact name of the lead; `ReferralsTest` reads both parts.
- **A partner is deactivated, never deleted**: `referrals.partner_id` restricts the delete, and the
  trail keeps pointing at it. A deactivated partner leaves the matches and cannot be chosen
  (`ReferLeadRequest`'s `exists` asks the firm AND `active`).
- **`status` starts `enviado`; the partner moves it, Franco reads it** (rnc-2 R7, D4): the graph
  lives on `ReferralStatus` (`allowedTransitions` — enviado → aceito | recusado; aceito → contato →
  reuniao → proposta → fechado; no step back, no skip; fechado and recusado terminal) and
  `PortalReferralService::transition` asks it under the row's lock (`lockForUpdate`), so two racing
  moves cannot both pass. Each move stores `status`, `status_at`, `status_by` (the partner's login)
  and its step's own column — `contacted_at` on contato (the SLA's stop), `meeting_at` on reuniao,
  `deal_value_cents` + `closed_at` on fechado. `TransitionReferralRequest` drops a meeting or a
  value sent with another move (`exclude_unless`), so nothing stray is stored.
- **Another firm's partner, lead or referral is a 404 or absent** (`BelongsToFirm` on both tables, A1).

### Partner access and the portal (rnc-2 F1, R1–R4, D1–D2)

- **One login per partner, one `users` row** (`PartnerAccessService`): role `parceiro` (only
  `portal.access`), `partner_id` set, and `firm_id` = the PARTNER's firm (AM1) — a login without a
  firm would fall through `ResolveCurrentFirm` to the `X-Firm` header. A second active login is 409
  `partner_access_exists`; a revoked one is reopened on the next grant (same user, the username may
  stay), so `CreatePartnerAccessRequest`'s uniqueness ignores the partner's own row. An inactive
  partner gets none (409 `partner_inactive`). `users.partner_id` is unique, so two grants racing
  past the check meet the index and the loser gets the same 409.
- **The password is generated and answered once** (`PartnerAccessResource::withPassword`, closed by
  default); only its hash is stored. Regenerate and revoke delete the login's `sessions` rows and
  revoke deactivates it, so its live cookie is a 401 on the next request and the right password then
  answers `auth.accountInactive` (AM5). **Deactivating the partner closes its login** in the same
  transaction (`PartnerService::update`).
- **History is `partner_access_events`** — one `granted`/`revoked` row per change, UTC (AM9);
  `partners.access_granted_at` / `access_revoked_at` are only the current state. The ledger (F4)
  bills subscription months from the rows. The seeded logins get their granted row too.
- **`CurrentPartner`** (scoped binding) is set by `App\Http\Middleware\ResolveCurrentPartner`, the
  route alias `portal`, on every `portal/*` route and never globally: it loads the user's
  `partner_id` through FirmScope and requires `active`, else 403 `portal.noPartner` — the operator
  holds `portal.access` and is refused here. Portal code reads the partner from it, never from
  the request.

### The portal's inbox and moves (rnc-2 F2, R4–R9, D3–D4)

- **Portal reads go through `PortalReferralRepository`**: every query is
  `where partner_id = CurrentPartner::id()` under `FirmScope` — never an id from the request.
  Another partner's referral, another firm's, or an id that is a lead's is a 404 (A3), whatever
  `X-Firm` says (the partner's firm comes from its login, AM1).
- **What the partner sees is an allow-list** (`PortalReferralResource`, R6, D3): company, sector ·
  regime · porte (never the rest of `profile`), the covered titles, score, message, dates, status,
  SLA. The lead's contact only from the accept on (`ReferralStatus::showsContact` — a declined
  referral never saw a phone); a `carteira` lead (a portfolio client, R27) shows Franco's own name,
  phone and mailbox instead. `deal_value_cents` only once fechado — the partner's own number. Never
  a range, a note, the timeline, the consent or another referral (A5 reads the bodies).
- **Every move is one `encaminhamento.status` line** (`LeadService::recordReferralStatus`, by the
  partner's login, `{referral_id, partner_id, partner, from, to[, meeting_at][, deal_value_cents]}`)
  in the move's transaction; the lead's stage never moves (R9). **The bell rings on aceito,
  recusado and fechado only** (Notification's `ReferralStatusNotification`, after the commit,
  through `TeamNotifier`); contato, reunião and proposta reach the timeline alone.
- **The SLA is computed, not stored** (`ReferralSla`, R8): 24 business hours from `created_at` to
  `contacted_at` (or now), on the CURRENT firm's `business_hours` in `America/Sao_Paulo` minus
  Booking's national holidays (`Helpers/BusinessHours`, pure maths). ok < 12 ≤ atencao < 24 ≤
  estourado while open; cumprido ≤ 24 < fora once contacted; encerrado when declined. The state
  reads the exact hours, the answer carries one decimal (a whole number reaches JSON as an integer).
- **Franco reads the partner's progress** (R10): `ReferralResource` (the trail) and
  `PartnerMatchesResource`'s `referral` carry `status_at`, `sla` (`ReferralSla::of`, the same clock
  as the portal) and `deal_value_cents` — `null` until `fechado`, then the partner's value. The key
  is always there for the staff (the partner's answer omits it until closed, W24): Franco sees
  every referral, and a typed field reads simpler than a missing one.
- **Every API route is classified** in `tests/Feature/Access/routeClasses.php` (public · portal ·
  staff · shared, A2): a route added without a class fails `RouteMatrixTest`.

### The Radar's batch referral (rnc-2 F10, R27–R28, D13)

- **Partner writes the batch, the Radar only ranks it**: Radar's `RadarService::referBatch` hands
  `ReferralService::referBatch(rule, partnerId, ranked, user)` the rule's clients in the screen's
  order (the range's top, ties by client id) with the profile the Radar read; this module decides
  who goes, so the consent gate and the partner checks live where the `referrals` rows are written.
- **Who may receive a batch**: an ACTIVE partner of the current firm (the request's `exists`, then
  `findActivePartnerById` under `FirmScope` — another firm's partner is 422 on `partner_id` either
  way) whose specialty covers the rule (`PartnerSpecialty::covers`), else 422 `partner_not_covering`.
- **Who goes**: the first `BATCH_SIZE` (10) clients whose `partner_consent` is TRUE — null, never
  answered, counts as no, like `false` (W98) — and whose `carteira` lead was never sent to this
  partner. The answer `{sent, skipped_no_consent, skipped_already}` counts the row's clients left
  out for each reason (clients past the 10th are neither: they wait for the next batch). A second
  identical batch sends what is left, then 0.
- **Each client goes through its one portfolio lead** (Lead's `findOrStartPortfolioLead`, D13), so
  the referral is the routing panel's own shape: score and covered items from `PartnerMatcher` on the
  lead's (the Radar's) profile, the lead's `via contrato` consent copied onto the row, one
  `encaminhamento.enviado` line by the sender, the trail and the portal inbox unchanged (the inbox
  shows the company and, from the accept on, the firm's contact — `PortalReferralResource`). The
  message is the demo's Radar text in pt-BR: "A <firma> indica a <cliente>, cliente da carteira desde
  <ano>: o Radar da Reforma apontou “<título>”. …", the year that of the client's consent (its
  contract), else of its row. All writes in ONE transaction; a pair raced in by another batch is the
  unique index's 409 `already_referred` and nothing of the batch is kept. One `ReferralSent` per
  referral is queued after the commit.
- **The row's picker (`portfolioBatchOptions`)**: per rule, the active covering partners by name,
  each `{id, name, specialty, batch, sent}` — `batch` the size its next batch would have, `sent` how
  many of the row's clients it already got. Three queries whatever the rows (partners, the firm's
  portfolio leads, their referrals).

### The ledger (rnc-2 F4, R11, D10; plan AM9)

- **One model for the network, stored on the firm** (Firm's `partner_money_model`, written only by
  `FirmSettingsService`); `LedgerService` reads it, the rate and the fee through `CurrentFirm`.
- **Comissão** = per closing `intdiv(deal_value_cents * rate_bp + 5000, 10000)` — half-up to the
  cent, each closing on its own (`LedgerService::commission`); R$ 12.345,67 at 10 % is R$ 1.234,57.
- **A closing belongs to the month of its `closed_at` in São Paulo** (`LedgerService::bounds`): the
  repository converts the bounds to UTC itself, because a `Carbon` binding is written as its
  wall-clock time.
- **Assinatura** = the fee for each partner whose access existed on any day of the month, folded
  from `partner_access_events` (`LedgerService::subscribedPartnerIds`, pure): a grant opens a
  period, a revoke closes it; a period that touches the month bills it. A revoke and a later regrant
  bill both periods, never the gap. Closings are listed at `0` ("incluído").
- **The partner sees only its own month under the current model** — the rule of that model, its
  lines, its total — never the other model, nor another partner (D10). Its id comes from
  `CurrentPartner`, so `?partner_id=` and `X-Firm` change nothing. Franco's network ledger carries
  both rules, every partner (inactive too: history), the total and the other model's.
- **Three queries per answer, whatever the network's size** (closings, access history, partners —
  the statement eager-loads the lead's company): the maths is PHP; `LedgerTest` counts them.
- **Nothing is billed**: both screens say "valor a conferir — sem cobrança automática".

### The legal referral of a contract (rnc-3 F6b, R20, R21, D11, AM11, AM20)

- **Partner's, not Portfolio's**: the referral is a `referrals` row, and Partner already reads
  Portfolio (`Client`), so `ContractReferralController` lives here and Portfolio only READS
  `referrals` for its alert — the dependency stays one-way.
- **Who may receive it**: an active partner of the firm (422 on `partner_id`) whose specialty is
  `advocacia_tributaria` or `precificacao_controladoria` (`ReferralService::LEGAL_SPECIALTIES`, the
  demo's, not by rule coverage — rnc-3 W15), else 422 `partner_not_legal`.
- **Consent**: the client's `partner_consent` must be TRUE (null, never answered, is no — as the
  Radar batch), else 422 `consent_required`, nothing written, no lead started.
- **Through the client's one portfolio lead** (Lead's `findOrStartPortfolioLead` with the client's
  own cadastro profile), its `via contrato` consent copied onto the row; score and covers from
  `PartnerMatcher`; one `encaminhamento.enviado` line (with `contract_id`); `ReferralSent` with
  Franco's contact after the commit.
- **Subject (D11, AM11)**: `contract_id` and `subject_key = contrato:<id>`; every other referral is
  `subject_key = lead`, and the unique key is `(lead_id, partner_id, subject_key)`. The plain refer's
  duplicate check, the routing panel's matches and the Radar batch's "already sent" all filter
  `subject_key = lead` explicitly, so a contract referral and a lead/Radar referral of one client to
  one partner never block each other; a second send of one contract to one partner is 409.
- **The message is the demo's (`ctRefer`) with the value banded**: "A <firma> indica a <cliente>: o
  contrato de <tipo> de <faixa> por ano vai até <fim> sem cláusula de reequilíbrio para a Reforma.
  Podem revisar e propor o aditivo? A <firma> segue responsável pelo cliente." — the demo printed the
  exact value; the partner reads the message, so AM20 bands it here too.
- **The portal's summary (AM20)**: `PortalReferralResource.contract` = `{kind, end_year,
  sem_clausula, value_range: {min_cents, max_cents|null, label}}` by `ContractValueBand` (bounds R$ 0
  · 100 mil · 250 mil · 500 mil · 1 mi · 2 mi, lower included); `sem_clausula` follows the contract
  now (Franco marking the clause turns it false). Never `annual_value_cents`, the client's fee, its
  other contracts or other referrals — `PortalContractReferralTest` scans the payload. A plain
  referral answers `contract: null`.

## Tables read from other modules

`leads`, `lead_consents` (Lead, through `LeadRepository`, `Lead::latestConsent` and the portal's
`Referral::lead` — company, profile, contact, channel); `users` (the
referrer's name; a partner's login through `Partner::account` and `CreatePartnerAccessRequest`'s
uniqueness — written only by Auth's `LinkedAccountService`); `firms` (Franco's contact for the
e-mail and the portal's firm name, through `CurrentFirm` / `BelongsToFirm::firm`; the SLA's
`business_hours` and a `carteira` referral's phone and mailbox, through `CurrentFirm`; the ledger's
money model, rate and fee, through `CurrentFirm` — written only by Firm's `FirmSettingsService`);
`clients`, `client_contracts` (Portfolio — the client's consent, name and profile and a contract's
kind, years, value and clause, through `ContractRepository::findContractWithClientById` and
`Referral::contract`, rnc-3 F6b; written only by Portfolio). Booking's
`Holidays` helper (no table) for the SLA's days off.

## Known Gaps

*Retired 07/11/2026 (rnc-2 F3):* "The portal's screens are not built yet" — the SPA's inbox and
cards, and Franco's status and SLA on the routing panel and the trail, now read these answers.

*Retired 07/11/2026 (rnc-2 F2):* "The portal answers only `GET portal/me` so far" and "No SLA
clock" — the inbox, the moves and `ReferralSla` now answer on the API.

*Retired 07/11/2026 (rnc-2 F1):* "No partner login yet (D7)". A partner now has a portal login
(`POST partners/{id}/access`); the status still stays `enviado` until the portal moves it (F2).

- **Seeded moves carry no login** (`PartnerSeeder`): `status_by` and the seeded lines' `user_id`
  are null, because the partners' logins are seeded after the referrals (`PartnerUserSeeder`).
- **A firm without `business_hours` never breaches the SLA** (zero hours a week): `firms` requires
  the column, so only a hand-edited row meets it.
- **The local seed's partners are placeholders** (`PartnerSeeder`, local only): production starts
  with an empty registry until Franco names its partners.
*Retired 07/11/2026 (rnc-3 F6b):* "A contract referral shows the partner a value RANGE … written ahead
of the code" — the contract referral is built; the rule now lives in Intentional Decisions → "The
legal referral of a contract".
