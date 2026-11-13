# Changelog — Partner Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.6.0 — 2026-11-07

### Added

- The legal referral of a client contract (rnc-3 F6b, R20, R21, D11, AM11, AM20; A18, A19): `GET contracts/legal-partners` and `POST contracts/{id}/referrals` (`ContractReferralController`, `ReferContractRequest`/`ReferContractDTO`, `LegalPartnerResource`; `contracts.manage`); `ReferralService::legalPartners` / `referContract` (legal specialty else 422 `partner_not_legal`, client consent else 422 `consent_required`, through `findOrStartPortfolioLead`, the demo's message with the value banded, 409 `already_referred`, `ReferralSent`); `referrals.contract_id` + `subject_key` folded into `create_referrals` with the unique `(lead_id, partner_id, subject_key)`; `Referral::contract`, `SUBJECT_LEAD`, `contractSubject()`; `ReferralException::partnerNotLegal`; `PartnerRepository::findActivePartnersBySpecialties`; `ReferralRepository::existsReferralOfContractToPartner`; `Helpers/ContractValueBand`; `PortalReferralResource.contract` (kind, end year, `sem_clausula`, `value_range`) with the contract eager-loaded; `ReferralResource.contract_id`.

### Changed

- The plain refer's duplicate check, the routing panel's matches and the Radar batch's "already sent" filter `subject_key = lead` (AM11). Tested by `tests/Feature/Partner/ContractReferralTest`, `PortalContractReferralTest`, `tests/Unit/Modules/Partner/ContractValueBandTest`; `PortalReferralsTest` payload gains `contract: null`.

## 1.5.2 — 2026-11-07

### Changed

- RULES.md records rnc-3 AM20 ahead of the code: a contract referral shows the partner a value range by fixed bands, never the exact annual value (Known Gaps — contract referrals land in rnc-3 F6b). No code change.

## 1.5.1 — 2026-11-07

### Fixed

- A portfolio (`carteira`) referral's contact phone is Franco's, stored as typed ("11 3732-5500"), and was answered as stored, so the portal rendered "+1137325500". `PortalReferralResource` now answers `PhoneNumber::normalize` (`551137325500`), the form a lead's phone already has. Tested by `PortalReferralsTest`.

## 1.5.0 — 2026-11-07

### Added

- The Radar's batch referral (rnc-2 F10, R27, A21; D13): `ReferralService::referBatch` (an active partner whose specialty covers the rule, else 422 `partner_not_covering`; the first 10 clients in the Radar's order with consent TRUE and never sent to that partner; each through Lead's `findOrStartPortfolioLead`, the referral scored by `PartnerMatcher` on the client's profile with the demo's Radar message and the lead's `via contrato` consent copied, one `encaminhamento.enviado` line each, all in one transaction, then one `ReferralSent` each) and `portfolioBatchOptions` (per Radar row, the active covering partners with `batch` and `sent`, three queries); `ReferralException::partnerNotCovering`; `ReferralRepository::findPartnerIdsByLeads`. Tested by `tests/Feature/Radar/RadarBatchReferralTest`.

## 1.4.1 — 2026-11-07

### Changed

- `PortalReferralResource` tells a portfolio referral by `LeadChannel::Carteira` instead of its own `PORTFOLIO_CHANNEL` constant (rnc-2 F5, W23); no behaviour change.

## 1.4.0 — 2026-11-07

### Added

- The network ledger (rnc-2 F4, R11, D10, A9; plan AM9): `GET portal/statement` (`PortalController::statement`, `PartnerStatementResource`), `GET ledger` and `PATCH ledger/model` (`LedgerController`, `NetworkStatementResource`, `LedgerModelResource`); `LedgerService` (`statementFor`, `networkStatement`, the pure `commission` — half-up — and `subscribedPartnerIds` — folded from `partner_access_events`), `LedgerRepository` (closings in a month, access history, UTC bounds), `LedgerMonthRequest`/`LedgerMonthDTO` (`YYYY-MM`), `UpdateLedgerModelRequest`/`UpdateLedgerModelDTO`. Tested by `tests/Feature/Partner/LedgerTest` and `tests/Unit/Modules/Partner/LedgerServiceTest`; routes classified in `tests/Feature/Access/routeClasses.php`.

## 1.3.0 — 2026-11-07

### Added

- Franco's view of the partner's progress (rnc-2 F3, R10, A8): `ReferralResource` (the trail) and `PartnerMatchesResource`'s `referral` carry `status_at`, `sla` and `deal_value_cents` (`null` until `fechado`). Tested by `PortalReferralsTest` ("shows Franco's routing panel and trail…").

## 1.2.0 — 2026-11-07

### Added

- The partner's inbox and its moves (rnc-2 F2, R4–R9; A3, A5–A7 and A8's Pest half; plan AM1): `GET portal/referrals`, `GET portal/referrals/{id}`, `POST portal/referrals/{id}/transition` (`PortalController`, `PortalReferralService`, `PortalReferralRepository` — always `partner_id = CurrentPartner` — `TransitionReferralRequest`, `TransitionReferralDTO`, `PortalReferralResource`, the allow-list: contact only from the accept on, Franco's for a `carteira` lead, the value only once closed).
- `ReferralStatus` graph: `allowedTransitions`, `canTransitionTo`, `isTerminal`, `label`, `showsContact`; `ReferralException::invalidTransition` (422 `referral.invalidTransition`, `code: invalid_transition`).
- The SLA: `Helpers/BusinessHours` (the firm's week in São Paulo minus Booking's `Holidays`) and `Services/ReferralSla` (24/12 business hours; ok · atencao · estourado · cumprido · fora · encerrado). `referrals` gains `status_at`, `status_by`, `contacted_at`, `meeting_at`, `deal_value_cents`, `closed_at` (UTC, `UtcDateTime`).
- Tested by `PortalReferralsTest`, `tests/Unit/Modules/Partner/{ReferralStatus,BusinessHours,ReferralSla}Test` and `PartnerSeederTest` (the demo's twelve referrals).

## 1.1.0 — 2026-11-07

### Added

- A partner's portal login (rnc-2 F1, R1–R4, A1–A2; plan AM1, AM5, AM9): `POST partners/{id}/access`, `POST partners/{id}/access/password`, `DELETE partners/{id}/access` (`PartnerAccessController`, `PartnerAccessService`, `CreatePartnerAccessRequest`, `PartnerAccessResource`, `PartnerAccessException` with codes `partner_access_exists`, `partner_access_missing`, `partner_inactive`); `partners.access_granted_at` / `access_revoked_at`, the `partner_access_events` table (`PartnerAccessEvent`, `PartnerAccessEventType`); `Partner::account`; `PartnerResource` carries `access`.
- The portal's scope: `CurrentPartner`, `ResolveCurrentPartner` (alias `portal`, 403 `portal.noPartner`) and `GET portal/me` (`PortalController`, `PortalMeResource`). Tested by `PartnerAccessTest`, `PortalMeTest`, `PartnerUserSeederTest` and `tests/Feature/Access/RouteMatrixTest`.

### Changed

- `PartnerService::update` closes the partner's login when it deactivates the partner.

## 1.0.1 — 2026-11-07

### Fixed

- `ReferralService::refer` catches `UniqueConstraintViolationException` instead of comparing the SQLSTATE with `23000` (PostgreSQL's `23505` answered 500 instead of 409 `already_referred`); `ReferralsTest` now reaches the catch through a repository double (rnc-1 review).

## 1.0.0 — 2026-11-07

### Added

- `partners` and `referrals` tables; `GET/POST partners`, `PATCH partners/{id}`, `GET partners/options`, `GET leads/{id}/partner-matches`, `POST leads/{id}/referrals` (422 `consent_required`, 409 `already_referred`), `GET referrals` (rnc-1 F6, R21–R23, A12, AM6, AM8). `PartnerSpecialty` (the demo's `PARTNER_RULES`), `ReferralStatus`, `PartnerMatcher`, `PartnerService`, `ReferralService`, `ReferralException`, the queued `ReferralSent` with `ReferralMailDTO`; `PartnerSeeder` (eight "(exemplo)" partners and a few referrals of consenting leads, local only). Tested by `tests/Feature/Partner`.
