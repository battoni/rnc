---
version: 1.3.0
origin: rnc
---
# RULES — Partner Module

> For AI agents. rnc-1 F6, 07/11/2026; rnc-2 F1 (partner access, the portal's first route), F2
> (the portal's inbox, the moves along the graph, the SLA).

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
| `GET referrals?partner_id=&page=&per_page=` | `referrals.read` | the trail, newest first, paginated |
| `POST partners/{id}/access` | `partners.manage` | `{username, email}` → the login with `password`, once (201); 409 `code: partner_access_exists` / `partner_inactive` |
| `POST partners/{id}/access/password` | `partners.manage` | a new `password`, once; the login's sessions end; 404 `code: partner_access_missing` |
| `DELETE partners/{id}/access` | `partners.manage` | the login deactivated, its sessions ended → the access as it is now; 404 `partner_access_missing` |
| `GET portal/me` | `portal.access` + `portal` | `{partner: {id, name, specialty}, firm: {name, slug}}` |
| `GET portal/referrals` | `portal.access` + `portal` | the partner's referrals (`PortalReferralResource`), open before closed, newest first; unpaginated (a partner's few dozen) |
| `GET portal/referrals/{id}` | `portal.access` + `portal` | one of them; anybody else's, or no referral, 404 |
| `POST portal/referrals/{id}/transition` | `portal.access` + `portal` | `{to, meeting_at? (reuniao, future), deal_value_cents? (fechado, > 0)}` → the referral as it is now; 422 `code: invalid_transition` off the graph |

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

## Tables read from other modules

`leads`, `lead_consents` (Lead, through `LeadRepository`, `Lead::latestConsent` and the portal's
`Referral::lead` — company, profile, contact, channel); `users` (the
referrer's name; a partner's login through `Partner::account` and `CreatePartnerAccessRequest`'s
uniqueness — written only by Auth's `LinkedAccountService`); `firms` (Franco's contact for the
e-mail and the portal's firm name, through `CurrentFirm` / `BelongsToFirm::firm`; the SLA's
`business_hours` and a `carteira` referral's phone and mailbox, through `CurrentFirm`). Booking's
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
