# Changelog — Firm Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.3.2 — 2026-11-07

### Changed

- `BrandRepository::findActiveBrandBySlug` (the public `X-Brand` lookup) filters by `CurrentFirm` itself, not by the `BelongsToFirm` scope alone, and finds nothing without a current firm (rnc-3 review A1). Tested by `tests/Feature/Firm/BrandRepositoryFirmTest`, which lifts the scope off `FirmBrand`.

## 1.3.1 — 2026-11-07

### Added

- `BrandService::all()` — the firm's sub-brands by name, inactive too, for the board's brand filter (rnc-3 F2).

## 1.3.0 — 2026-11-07

### Added

- Sub-brands (rnc-3 F1, R1–R5): `firm_brands` table, `FirmBrand` model and factory, `CurrentBrand` scoped binding, `ResolveCurrentBrand` middleware (alias `brand`, `X-Brand` looked up inside the request's firm; 404 `brands.notFound`, `brand:fallback` on the events beacon — AM21), `Helpers/Contrast` (WCAG 2), `BrandRepository`, `BrandService`, `UpdateBrandRequest` (`#rrggbb`, four proof points, `brand.lowContrast` under 4,5:1), `UpdateBrandDTO`, `BrandResource`, `MainBrandResource`, `BrandController`: `GET brands`, `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate` (`brands.manage`). Tests: `ContrastTest`, `PublicBrandTest` (A1), `BrandAdminTest` (A5), `Phase3AccessTest` (A25 brand rows).

### Changed

- `PublicFirmResource` gains `brand`, `short_name`, `tagline` and `proof` (null for the main brand) and, under a sub-brand, its name, logo text and colours over the firm's own contact (`underBrand()`). `PublicFirmTest` updated.

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
