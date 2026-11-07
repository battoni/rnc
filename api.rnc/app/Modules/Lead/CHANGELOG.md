# Changelog — Lead Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.6.0 — 2026-11-07

### Added

- `LeadChannel` (`formulario`, `chat`, `carteira`) replaces `LeadService::FORM_CHANNEL` (rnc-2 F5, R14): `startLead` takes the channel and stamps it on a NEW lead only — a reopened `visitante` keeps the one it began in; `recordContact` stores the consent `via` from the lead's channel (`LeadChannel::consentVia`, `ConsentTerms::VIA_CHAT`). `PublicLeadResource` sends `channel`. Tested by `tests/Feature/Diagnostic/ChatChannelTest`.

## 1.5.0 — 2026-11-07

### Added

- `leads.brand_id` (rnc-3 F1, R4; nullable, indexed, no foreign key — folded into `create_leads_table`): written once by `LeadService::startLead` from the request's `CurrentBrand`, never by a later step, and kept when the same visitor starts again. `Lead::brand()`, `Lead::MAIN_BRAND`. `LeadRepository`: the board's brand filter (`findLeadsOfStage`, `countLeadsByStage`), the brand eager-loaded on a column and the detail, `countLeadsByBrand()` in one grouped query. Tests: `BrandLeadTest` (A3), `BrandFilterTest` (A4).
- `LeadEventType::ReferralStatusChanged` (`encaminhamento.status`) and `LeadService::recordReferralStatus` — one timeline line per partner move, by the partner's login; the stage never moves (rnc-2 F2, R9; tested by `PortalReferralsTest`).

### Changed

- `ConsentTerms::textFor(?brandName)`: under a sub-brand the consent names it ("Autorizo a Franco Prime Contabilidade a me apresentar…"); the main brand keeps `TEXT`, so `VERSION` stays 1. `recordContact` stores, and `PublicLeadResource` shows, the lead's brand's words.

## 1.4.0 — 2026-11-07

### Changed

- `LeadService::startLead` starts the visitor's open `visitante` (same `visitor_uuid`, live token) again instead of a new row: path, step and answers reset, token, first touch and timeline kept; `LeadRepository::findOpenVisitanteOfVisitor` (rnc-1 review). The bot case is a Known Gap.

### Fixed

- `LeadRepository::searched` escapes `%`, `_` and `!` with `!` in an explicit `ESCAPE '!'` — the backslash escape without a clause matched nothing on SQLite and depended on MySQL's string escaping. Tested by `PipelineLeadsTest` (`100%`, `A_B`).

## 1.3.0 — 2026-11-07

### Added

- `LeadService::recordReferral` and `LeadEventType::ReferralSent` (`encaminhamento.enviado`) — the referral's timeline line, written by the Partner module (rnc-1 F6, R22).

## 1.2.0 — 2026-11-07

### Added

- `leads.lost_reason`, `LeadService::moveStage` (the team's move to any other stage, perdido with a reason, an `etapa.alterada` line with the user) and `addNote` (`nota.adicionada`), `LeadStage::boardOrder`/`canBeMovedByTeamTo`/`requiresReason`, `LeadEvent::user`, `LeadRepository` board reads and search (rnc-1 F5, R18–R19, AM13).

## 1.1.0 — 2026-11-07

### Added

- `report_token` / `report_expires_at` on `leads` (AM4), `LeadService::recordBooking` (→ `reuniao_marcada`, the e-mail, the report token, `reuniao.marcada` on the timeline) and `findLeadByReportToken`, `LeadStage::canBeBooked`, `has_email` in `PublicLeadResource` (AM5); the first contact rings the team's bell (`NewLeadNotification`) (rnc-1 F4).

## 1.0.0 — 2026-11-07

### Added

- `leads`, `lead_consents`, `lead_events` tables, `Lead`/`LeadConsent`/`LeadEvent` models, `LeadStage`, `LeadStep`, `LeadEventType` enums, `ConsentTerms`, `LeadService` (start, CNPJ, contact + consent, progress, result seen), `PublicLeadResource`, `LeadException` (409 `lead_locked`), `LeadFactory` (rnc-1 F3, R11, AM3, AM12, AM13). Tested through `PublicDiagnosticsTest`.
