---
version: 1.4.0
origin: rnc
based-on: 1.3.0
---
# RULES — Briefing Module

> For AI agents. rnc-3 F8, 07/11/2026; F7 (the Radar action's cost), 07/11/2026.

## Domain

Reinaldo's day in one payload (rnc-3 R23–R25, R27; the demo's `briefingNumbers` / `topActions`,
`ceo.js`): what came in, what is booked, what the network still owes and what the pipeline is
worth, with the three things worth his time today. The same payload is the screen (`GET briefing`,
F9) and the 07:00 e-mail (`briefing:send`). **The module owns no table**; it reads Lead, Booking,
Partner and Portfolio, each through the models' own `BelongsToFirm` scope.

## Endpoints

| Route | Key | Answers |
| --- | --- | --- |
| `GET briefing` | `briefing.read` (gestao, operador) | the briefing |

- `{as_of, leads, meetings, referrals, pipeline, actions}` (`BriefingService::build`, typed there):
  - `leads` — `last_7_days`, `last_24_hours`: leads that left a contact (phone or e-mail) since.
  - `meetings` — `count` from today 00:00 to the end of the 5th business day (weekends and
    Booking's national holidays skipped; today counts when it is one), `today`, `window_end` (the
    5th business day), `next` (the first 5: `lead_id`, `company`, `starts_at` in São Paulo ISO,
    `specialist`). A lost lead's meeting is left out.
  - `referrals` — every referral still owed a first contact (not contacted, not declined), by
    `ReferralSla` state (`by_state` ok · atencao · estourado), `goal_hours` 24, `top` (5:
    breached first, then attention, then ok; longest first within each) and `more`.
  - `pipeline` — lead · reuniao_marcada · proposta: `count`, `range_cents` and the same per stage.
  - `actions` — at most three `{kind, params, to}` (`Helpers/ActionPicker.php`).
- **A portfolio (`carteira`) lead counts in no lead, meeting or pipeline figure**; the referral
  figures count every open referral whatever its lead (R23: a partner still owes the first contact
  of a contract or Radar referral too).
- **A fixed number of queries** whatever the rows (plan P13, `BriefingTest`): two lead counts, the
  stages' leads, the fees, the meetings with lead and specialist, the referrals with lead and partner.

## Money (R24, D12, plan P5)

- **A lead has no fee, so it borrows the portfolio's** (`Helpers/FeeQuartiles.php`, pure): the
  monthly fee quartiles p25–p75 of the clients in its band, × 12, summed. A band under
  `MIN_BAND_CLIENTS` (5) or a lead without a band borrows every client's quartiles.
- **Nearest-rank on the cents**: p25 is the ⌈n/4⌉-th fee, p75 the ⌈3n/4⌉-th — a fee somebody pays.
  `clients.monthly_fee` (decimal) becomes cents as text, never through arithmetic on a float.
- **An empty portfolio gives no range** (`range_cents: null`, "sem base na carteira"); no lead on a
  non-empty portfolio gives `[0, 0]`.

## The actions (R25)

- The first three that apply, in the demo's order: the longest **breached** SLA → the partner's
  trail (`/gestao/trilha?parceiro=<id>`), **today's meetings** → `/equipe/agenda`, the **open
  proposals** with their range → `/equipe/pipeline?etapa=proposta`, the **partial leads** →
  `/equipe/pipeline?etapa=lead_parcial`, then **the Radar's first opportunity type** (rnc-3 F6b,
  W81) → `/gestao/radar`, `params {rule, title, clients, range_cents, period}` — the same row
  `GET radar` shows first among the opportunities (`RadarService::firstOpportunity`, a fixed cost:
  the clients and one sales aggregate — not `summary()`, whose ranking and partner pickers took
  ~80 ms of every briefing for rows it never shows, W100). What does not apply is left out; a portfolio
  with no opportunity has no Radar action. The rule's `title` is Diagnostic's pt-BR name, so the
  `en` e-mail quotes it in Portuguese.
- **The API sends numbers, not sentences** (rnc-3 W82): `params` carry what the sentence needs; the
  SPA's locale (F9) and `lang/*/mail.php` → `briefing.actions.*` write it, so `en` follows.

## The 07:00 e-mail (R27, D13, AM6)

- `briefing:send` (`app/Console/Commands/SendDailyBriefing.php`, `Services/DailyBriefingSender.php`)
  is scheduled `dailyAt('07:00')->timezone('America/Sao_Paulo')` in `routes/console.php`. On a
  weekend or a national holiday (São Paulo date) it sends nothing.
- Every firm in turn (`CurrentFirm::forEachFirm`) builds its briefing once and queues
  `Mail/DailyBriefingMail` (`ShouldQueue`, `pt_BR`) to each **active `gestao` with an e-mail** of that
  firm. The Mailable carries values (`DTOs/BriefingMailDTO`), never models. Locally the mailer is
  `log` and the queue `sync`.
- Views `resources/views/mail/daily-briefing{,-text}.blade.php`; preview at
  `/mail-preview/daily-briefing` (local only).

## Tables read from other modules

- `leads` (Lead) — stage, channel, contact, `profile.band`, `created_at` (a plain timestamp in the
  app's zone, unlike the `UtcDateTime` columns).
- `appointments`, `specialists` (Booking) — through `AppointmentRepository::findAppointmentsBetween`.
- `referrals`, `partners` (Partner) — the SLA through `ReferralSla::of`.
- `clients` (Portfolio) — `band`, `monthly_fee`.
- The Radar (rnc-2) — `RadarService::firstOpportunity` for the fifth action (it reads `clients` and
  `nfe_documents` itself).
- `users`, `roles` (Auth) — the firm's active ceos, for the e-mail.

## Known Gaps

*Retired 07/11/2026 (rnc-3 F6b):* "The Radar's action waits for rnc-2" — rnc-2's Radar is merged and
`ActionPicker` has the fifth kind `radar` (W81).

*Retired 07/11/2026 (rnc-3 F9):* "The screens the actions open read no filter yet" — the trail
honours `?parceiro=` and the board `?etapa=` (W83; app Partner and Pipeline RULES), and the SPA's
"Briefing do dia" (app Briefing module) writes the sentences.

- **No e-mail opt-out.** Every active ceo gets it on every business day; a preference waits for a
  person who asks.
