---
version: 1.11.0
origin: rnc
based-on: 1.10.0
---
# RULES — Lead Module

> For AI agents. rnc-1 F3–F5, 07/11/2026; rnc-2 F2 (the partner's moves on the timeline), F10 (the portfolio lead).

## Domain

A prospect of the firm (R11, R18): `leads` (firm-scoped), its consent answers (`lead_consents`) and
its timeline (`lead_events`). F3 creates them from the public diagnostic; F5's Pipeline module moves
them and writes the team's notes — through this module's service.

## Intentional Decisions

- **`LeadService` is the only writer** of the three tables. The Diagnostic, Booking and Pipeline
  modules call it (Partner too, for the referral line, F6, and the partner's moves, rnc-2 F2); Tracking only reads (`findLeadByToken`).
- **The booking (F4) is `recordBooking`**: `reuniao_marcada`, the e-mail the confirmation goes to,
  and the report's own token (`report_token`, 48 characters, 30 days, AM4) — a second key, so the
  e-mailed link never opens the diagnostic's routes.
- **The first contact rings the bell**: when a lead becomes a `lead_parcial` (and only then),
  `TeamNotifier` sends `NewLeadNotification` to the team (Notification module).
- **The token (AM3)** is `Str::random(48)`, unique, valid 30 days (`token_expires_at`); a lookup
  only finds a live token in the current firm. The internal `id` never leaves through a public route.
- **`PublicLeadResource` is an allow-list**: never the phone, the e-mail, the contact's name, the
  visitor UUID or the id. The token is sent only by the response that creates the lead
  (`withToken()`).
- **Stages (AM13)** are the `LeadStage` enum's slugs, stored as strings (AM11). The diagnostic only
  moves `visitante → lead_parcial` (the contact) and `lead_parcial → lead` (the result, once);
  the booking moves any of those three to `reuniao_marcada`;
  `locksProfile()` refuses any change from `reuniao_marcada` on (409 `lead_locked`).
- **The team moves a lead anywhere (F5, AM13)**: `moveStage` to any stage but the current one,
  backwards too; perdido needs the reason (`lost_reason`, cleared when it leaves). Each move is an
  `etapa.alterada` line `{from, to, via[, reason]}` with the `user_id` — the line F7's funnel counts
  for "cliente". A team note is a `nota.adicionada` line with its author.
- **A referral is a timeline line, not a stage (F6, R22)**: `recordReferral` writes one
  `encaminhamento.enviado` line `{referral_id, partner_id, partner, specialty, score}` with the user
  and touches the lead; the stage stays — Franco remains the owner. The `referrals` row is the
  Partner module's, written in the same transaction.
- **A partner's move is a timeline line too, never a stage (rnc-2 R9)**: `recordReferralStatus`
  writes one `encaminhamento.status` line `{referral_id, partner_id, partner, from, to[, meeting_at]
  [, deal_value_cents]}` with the partner's login as `user_id` and touches the lead, so its card
  rises. The partner never moves the lead's stage; Franco's team reads the line and decides.
- **Every contact submission writes a consent row (AM12)**, given true or false — the box starts
  unticked — with the firm's current version and its filled words (`ConsentTextService::forLead`),
  `via` and `given_at`. No IP, no user agent. A new wording is a new version; the stored text is
  the text read, and an older answer keeps its own words.
- **The consent is per firm and versioned (rnc-4 R13, D12)**: `firm_consent_versions`, written only
  by `ConsentTextService` — version n+1 for a new text, an older row never touched; a firm with none
  starts at 1 from the platform default (`PlatformSettings::consentDefault`, Firm module) the first
  time it is needed. `{escritorio}` is filled when read, with the sub-brand's name, else the firm's.
  Franco's version 1 is its rnc-1 sentence with its name as the placeholder (`FirmSeeder`, spec C5).
  *Retired 14/11/2026 (rnc-4 F2a):* "`ConsentTerms::TEXT`, `VERSION` 1" — one firm's words as code.
- **A lead's brand is fixed at creation (rnc-3 R4)**: `brand_id` is the `CurrentBrand` of the
  diagnostic's start (null = the main brand), written once by `startLead` — never by a later step,
  and kept when the same visitor starts again under another brand — like `channel`. No foreign key:
  `firm_brands` is the Firm module's. The board's filter passes `Lead::MAIN_BRAND` (0) for "only the
  main brand"; ids start at 1.
- **The consent names the visitor's brand (rnc-3 R3)**: `{escritorio}` takes the lead's sub-brand's
  name, at the firm's current version — a sub-brand has no text of its own.
- **The channel is set once (rnc-2 R14)**: `LeadChannel`, stamped when the lead is created and never
  written again — a later PATCH carries no channel, and a start from the other surface on the same
  visitor reopens the `visitante` with its channel kept. `carteira` is Franco's own portfolio
  (F10) and never a visitor's (`StartDiagnosticRequest` takes `formulario|chat` only). The consent's
  `via` is the lead's channel, so a chat walk's consent reads `chat`.
- **A portfolio client has ONE `carteira` lead (rnc-2 F10, R27, D13; plan AM10)**:
  `findOrStartPortfolioLead(client, profile)` — called by Partner's Radar batch inside its
  transaction — finds it by `client_id` (unique per firm, Portfolio's id with no foreign key) or
  creates it at stage `cliente`, `channel`/`path` `carteira`, source `direto`, NO contact (the
  partner's way in is the firm's, R6) and a token born expired, so no public route ever opens it.
  It copies the client's name (`company`), CNPJ and the profile the Radar read (sector, regime, band,
  the measured B2B share when D9 applies) — refreshed on every later batch — and, once, the client's
  consent as a `lead_consents` row `via contrato`, `ConsentTerms::CONTRACT_TEXT` with the firm's name
  filled (`ConsentTextService::contractConsent`, one generic sentence, D12), `given_at` the
  client's `partner_consent_at`. The caller checks the client's consent first; this method trusts it.
  The create is `createOrFirst` (a savepoint): a racing batch that committed the lead after this
  one's read is reused — refreshed, its consent copied if it has none — instead of failing the batch.
- **A beta signup is a lead of the platform firm only (rnc-4 R29, D29)**: `POST public/beta-signups`
on the root (the platform firm, header-less — AM9) writes one `beta` lead through
`LeadService::recordBetaSignup` — stage `lead`, path and channel `beta`, company/contact/e-mail/phone
in their columns, role, city, UF, clients band and message in `profile` over the empty diagnostic
profile (the pipeline's detail still diagnoses it), token born expired, the consent required
(`accepted`) and stored under the platform firm's current version `via formulario`, a
`contato.capturado` line. Any tenant is 404 before the body is read (`platform.firm` middleware),
and `BetaSignupService` refuses again. The platform firm's team hears it by bell
(`BetaSignupNotification`) and e-mail (`BetaSignupMail`, the platform firm's brand). Throttle
5/min per address (`beta-signup`). `GET public/beta-signups/consent` gives the form its words
(`ConsentTextService::forFirm`) — no lead exists before the submit.
- **The visitor is kept by UUID** (`visitor_uuid`), not a foreign key to Tracking's `visitors`: the
  diagnostic may start before the visit's beacon lands, and the two meet on (firm, uuid).
- **Children carry no firm column** (`lead_consents`, `lead_events`): they belong to their lead.

## The manager's consent versions (rnc-4 F5a, R33)

- `GET firm/consent-versions` (newest first: version, the stored words with `{escritorio}` unfilled,
  date, author `{id, name}` or null) and `POST firm/consent-versions` (`{text}` → version n+1 with the
  signed-in person as author), `firm.settings`, through `ConsentTextService::historyOfCurrentFirm` /
  `createForCurrentFirm` — always the CURRENT firm. The words must hold `{escritorio}`
  (`StoreConsentVersionRequest`, 422 `leads.consentPlaceholderMissing`, ≤ 1000), as the platform
  default (C15). A stored answer keeps its version and words; nothing is edited in place.
- `FirmConsentVersion::author()` reads `users` (Auth's table) for the name — a read, declared here.

## A lead's own data (rnc-4 F9a, R39, A48)

- `LeadService::exportData` — the lead's fields, every consent with the words read, the timeline
  without who wrote each line (the team's names are other people's data); never a token.
- `LeadService::anonymise($lead, $by, $via)` wipes company, CNPJ, name, phone, e-mail, `visitor_uuid`,
  `lost_reason` and the report link, replaces the diagnostic token by an expired random one, wipes the
  free text of notes (`text` → null) and stage lines (`reason` dropped), writes `anonymised_at` and a
  `dados.anonimizados` line (`{via: pedido | saida_do_escritorio}`, the actor or null). Stage, path,
  channel, profile, consents and every line stay — the funnel still counts the lead. Irreversible and
  idempotent: an anonymised lead is returned as is, with no second line.

## Known Gaps

- **An anonymised lead's partner referrals keep their message** (rnc-4 F9a): `referrals.message` is
  Partner's table; wiping it on anonymisation needs Partner's service. Other modules' copies (the
  appointment's time, tracking visits by a random id) hold no name or contact.
- **A beta signup has no dedupe**: the same firm sending twice is two `beta` leads; the throttle
(5/min/address) bounds a script, not a person. Decide with the beta's volume.

- **No retention rule** for leads and consents until LGPD retention is decided.
- **Bot traffic still creates visitantes**: `POST public/diagnostics` reuses the open `visitante`
  of the same `visitor_uuid` (no new row), but a script that sends a fresh UUID per call — or none
  — gets one empty `visitante` per call, up to the 20/min/IP throttle. No captcha or proof of work
  yet; the board's Visitante column and the funnel's diagnostic step can be inflated that way.
- ~~A visitor can start many leads~~ — retired 07/11/2026 (rnc-1 review): a restart on the same
  browser now starts its open `visitante` again (`LeadService::startLead`), so only the bot case
  above remains.