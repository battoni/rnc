# Changelog — Firm Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-07

### Added

- The network's money model (rnc-2 F4, R11, D10): `firms.partner_money_model` (default `comissao`), `partner_commission_rate_bp` (1000) and `partner_monthly_fee_cents` (49000), folded into `create_firms`; `PartnerMoneyModel` enum (`other()`), `Firm::partnerMoneyModel()`, `FirmRepository::updateFirm`, and `FirmSettingsService::updatePartnerMoneyModel` — the only writer, on the current firm. Tested by `tests/Feature/Partner/LedgerTest` (the switch moves only the signed-in person's firm, whatever `X-Firm` says).

## 1.1.0 — 2026-11-07

### Added

- `GET /api/public/firm` (`PublicFirmController`, `PublicFirmResource`) — the public site's brand and contact (rnc-1 F2, R5). Test: `PublicFirmTest`.

### Removed

- The `trait.unused` PHPStan entry for `BelongsToFirm`: F2's `SectorPage`, `Visitor` and `TrackingEvent` use the trait.

## 1.0.0 — 2026-11-07

### Added

- `firms` table, `Firm` model and factory, `FirmSeeder` (Franco Contabilidade, firm 1, from facts.md §1) (rnc-1 F1, R1).
- `CurrentFirm` scoped binding, `ResolveCurrentFirm` middleware (session → `X-Firm` → `franco`), `FirmScope` and the `BelongsToFirm` trait (D1, plan AM6). Tests: `FirmScopeTest` (A1), `FirmSeederTest`.
