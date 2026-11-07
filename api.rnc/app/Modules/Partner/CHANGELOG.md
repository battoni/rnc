# Changelog — Partner Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

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
