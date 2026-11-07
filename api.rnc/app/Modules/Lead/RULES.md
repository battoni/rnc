---
version: 1.1.0
origin: rnc
---
# RULES — Lead Module

> For AI agents. rnc-1 F3–F5, 07/11/2026.

## Domain

A prospect of the firm (R11, R18): `leads` (firm-scoped), its consent answers (`lead_consents`) and
its timeline (`lead_events`). F3 creates them from the public diagnostic; F5's Pipeline module moves
them and writes the team's notes — through this module's service.

## Intentional Decisions

- **`LeadService` is the only writer** of the three tables. The Diagnostic, Booking and Pipeline
  modules call it (Partner too, for the referral line, F6); Tracking only reads (`findLeadByToken`).
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
- **Every contact submission writes a consent row (AM12)**, given true or false — the box starts
  unticked — with `ConsentTerms::TEXT`, `VERSION` 1, `via = formulario` and `given_at`. No IP, no
  user agent. A new wording is a new version; the stored text is the text read.
- **The visitor is kept by UUID** (`visitor_uuid`), not a foreign key to Tracking's `visitors`: the
  diagnostic may start before the visit's beacon lands, and the two meet on (firm, uuid).
- **Children carry no firm column** (`lead_consents`, `lead_events`): they belong to their lead.

## Known Gaps

- **No retention rule** for leads and consents until LGPD retention is decided.
- **A visitor can start many leads** (each path choice after a restart is a new `visitante`); F5's
  kanban may need to fold empty visitantes.
