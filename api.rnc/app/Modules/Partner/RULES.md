---
version: 1.1.0
origin: rnc
---
# RULES — Partner Module

> For AI agents. rnc-1 F6, 07/11/2026; rnc-2 F1 (partner access, the portal's first route).

## Domain

The firm's network (R21–R23): the partner registry, the routing score of each partner against a
lead's diagnostic, the referral — Franco recommending one lead to one partner, only with the lead's
consent — and the trail of every referral. It owns `partners` and `referrals`; the lead's timeline
line goes through `LeadService::recordReferral` (Lead owns `lead_events`). From rnc-2 it also owns a
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
- **`status` starts `enviado`** (`ReferralStatus` holds the demo's seven); Phase 2's partner portal
  moves it.
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
- **Every API route is classified** in `tests/Feature/Access/routeClasses.php` (public · portal ·
  staff · shared, A2): a route added without a class fails `RouteMatrixTest`.

## Tables read from other modules

`leads`, `lead_consents` (Lead, through `LeadRepository` and `Lead::latestConsent`); `users` (the
referrer's name; a partner's login through `Partner::account` and `CreatePartnerAccessRequest`'s
uniqueness — written only by Auth's `LinkedAccountService`); `firms` (Franco's contact for the
e-mail and the portal's firm name, through `CurrentFirm` / `BelongsToFirm::firm`).

## Known Gaps

*Retired 07/11/2026 (rnc-2 F1):* "No partner login yet (D7)". A partner now has a portal login
(`POST partners/{id}/access`); the status still stays `enviado` until the portal moves it (F2).

- **The portal answers only `GET portal/me` so far** (rnc-2 F1): the inbox, the transitions and the
  SLA arrive in F2–F3; until then a partner signs in to a landing that names it.
- **No SLA clock** (the demo's 24 business hours): the message asks for it, nothing measures it.
- **The local seed's partners are placeholders** (`PartnerSeeder`, local only): production starts
  with an empty registry until Franco names its partners.
