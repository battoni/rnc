# Changelog — Firm Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-07

### Added

- Sub-brands (rnc-3 F1, R1–R5): `firm_brands` table, `FirmBrand` model and factory, `CurrentBrand` scoped binding, `ResolveCurrentBrand` middleware (alias `brand`, `X-Brand` looked up inside the request's firm; 404 `brands.notFound`, `brand:fallback` on the events beacon — AM21), `Helpers/Contrast` (WCAG 2), `BrandRepository`, `BrandService`, `UpdateBrandRequest` (`#rrggbb`, four proof points, `brand.lowContrast` under 4,5:1), `UpdateBrandDTO`, `BrandResource`, `MainBrandResource`, `BrandController`: `GET brands`, `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate` (`brands.manage`). Tests: `ContrastTest`, `PublicBrandTest` (A1), `BrandAdminTest` (A5), `Phase3AccessTest` (A25 brand rows).

### Changed

- `PublicFirmResource` gains `brand`, `short_name`, `tagline` and `proof` (null for the main brand) and, under a sub-brand, its name, logo text and colours over the firm's own contact (`underBrand()`). `PublicFirmTest` updated.


## 1.1.0 — 2026-11-07

### Added

- `GET /api/public/firm` (`PublicFirmController`, `PublicFirmResource`) — the public site's brand and contact (rnc-1 F2, R5). Test: `PublicFirmTest`.

### Removed

- The `trait.unused` PHPStan entry for `BelongsToFirm`: F2's `SectorPage`, `Visitor` and `TrackingEvent` use the trait.

## 1.0.0 — 2026-11-07

### Added

- `firms` table, `Firm` model and factory, `FirmSeeder` (Franco Contabilidade, firm 1, from facts.md §1) (rnc-1 F1, R1).
- `CurrentFirm` scoped binding, `ResolveCurrentFirm` middleware (session → `X-Firm` → `franco`), `FirmScope` and the `BelongsToFirm` trait (D1, plan AM6). Tests: `FirmScopeTest` (A1), `FirmSeederTest`.
