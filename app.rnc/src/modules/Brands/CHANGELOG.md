# Changelog — Brands Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- The brands console (rnc-3 F2, R5): `BrandsView` at `/operador/marcas` (`brands.manage`) — the main brand's row, the sub-brands with their lead counts, edit, activate/deactivate, "Ver como <short>" (disabled "Inativa" when off, AM12); `MAddEditBrandForm` in `MMainDialog` (name, short name, tagline, colours with a live contrast note and a palette strip, four proof points); services `GetBrandsService`, `UpdateBrandService`, `ToggleBrandService`. Tests: `BrandsView.view.integration.spec.ts` (A6), `MAddEditBrandForm.spec.ts`.
