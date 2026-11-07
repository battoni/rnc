# Changelog — Partner Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- "Acesso ao portal" on an active partner's row (rnc-2 F1, R2, A1): `MPartnerAccessForm` creates the login (username + e-mail), regenerates its password or revokes it; a generated password is shown once with a copy button and never handed to the view; refusals are named by `code` (`PARTNER_ACCESS_ERROR_KEYS`). The row wears "Portal ativo". Services `CreatePartnerAccessService`, `RegeneratePartnerPasswordService`, `RevokePartnerAccessService`; `Partner.access`.

## 1.0.0 — 2026-11-07

### Added

- The partner registry (`PartnersView` with `MAddEditPartnerForm`, deactivate/reactivate), the trail (`TrailView`: newest first, consent text and time, who and when, filter by partner, paginated) and the routing panel `MLeadRouting` with `MReferralForm` for the lead detail ("92% · ERP/NF-e", "Encaminhar" with a message, disabled with the reason without consent); services; rail children Parceiros and Trilha under Reinaldo (rnc-1 F6, R21–R23, A12).
