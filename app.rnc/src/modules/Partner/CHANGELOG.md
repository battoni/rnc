# Changelog — Partner Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.4.0 — 2026-11-07

### Added

- The trail honours `?parceiro=<id>` on open (rnc-3 F9, W83), so the briefing's breached-SLA action lands on the partner's referrals. Tested by `TrailView.view.integration.spec.ts`.

## 1.3.0 — 2026-11-07

### Added

- The network ledger (rnc-2 F4, R11, A9's Vitest half): `LedgerView` at `/reinaldo/extrato` (`/ceo/ledger`, route `network-ledger`, `ledger.read`) — every partner's month, the total and the other model's, the model switch (`partners.manage`) that reloads both totals, "valor a conferir — sem cobrança automática", ◀ ▶ by month; `NetworkLedger`, `NetworkLedgerRow`, `LedgerModelSettings`; services `GetLedgerService`, `UpdateLedgerModelService`; rail child "Extrato da rede" under Reinaldo. Tested by `LedgerView.view.integration.spec.ts` and `navItems.local.spec.ts`.

## 1.2.0 — 2026-11-07

### Added

- The partner's progress on Franco's side (rnc-2 F3, R10, A8's Vitest half): `MLeadRouting` shows each referred partner's status, `MSlaChip` and "Fechado · R$ …" once closed; `TrailView` adds the status, the SLA chip and the first-year value. `Referral` and `PartnerMatch.referral` gain `ReferralProgress` (`status_at`, `sla`, `deal_value_cents`). Tested by `MLeadRouting.spec.ts` and `TrailView.view.integration.spec.ts`.

## 1.1.0 — 2026-11-07

### Added

- "Acesso ao portal" on an active partner's row (rnc-2 F1, R2, A1): `MPartnerAccessForm` creates the login (username + e-mail), regenerates its password or revokes it; a generated password is shown once with a copy button and never handed to the view; refusals are named by `code` (`PARTNER_ACCESS_ERROR_KEYS`). The row wears "Portal ativo". Services `CreatePartnerAccessService`, `RegeneratePartnerPasswordService`, `RevokePartnerAccessService`; `Partner.access`.

## 1.0.0 — 2026-11-07

### Added

- The partner registry (`PartnersView` with `MAddEditPartnerForm`, deactivate/reactivate), the trail (`TrailView`: newest first, consent text and time, who and when, filter by partner, paginated) and the routing panel `MLeadRouting` with `MReferralForm` for the lead detail ("92% · ERP/NF-e", "Encaminhar" with a message, disabled with the reason without consent); services; rail children Parceiros and Trilha under Reinaldo (rnc-1 F6, R21–R23, A12).
