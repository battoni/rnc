---
version: 1.0.0
origin: rnc
---
# RULES — Lead Module

> For AI agents. rnc-1 F3, 07/11/2026.

## Domain

A prospect of the firm (R11, R18): `leads` (firm-scoped), its consent answers (`lead_consents`) and
its timeline (`lead_events`). F3 creates them from the public diagnostic; F5 adds the team's pipeline.

## Intentional Decisions

- **`LeadService` is the only writer** of the three tables. The Diagnostic module calls it; Tracking
  only reads (`findLeadByToken`).
- **The token (AM3)** is `Str::random(48)`, unique, valid 30 days (`token_expires_at`); a lookup
  only finds a live token in the current firm. The internal `id` never leaves through a public route.
- **`PublicLeadResource` is an allow-list**: never the phone, the e-mail, the contact's name, the
  visitor UUID or the id. The token is sent only by the response that creates the lead
  (`withToken()`).
- **Stages (AM13)** are the `LeadStage` enum's slugs, stored as strings (AM11). The diagnostic only
  moves `visitante → lead_parcial` (the contact) and `lead_parcial → lead` (the result, once);
  `locksProfile()` refuses any change from `reuniao_marcada` on (409 `lead_locked`).
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
