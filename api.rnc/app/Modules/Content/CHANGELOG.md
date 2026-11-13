# Changelog — Content Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- `sector_pages` table, `SectorPage` model (firm-scoped), `SectorPageSeeder` with the six sectors, `GET /api/public/sectors` and `GET /api/public/sectors/{slug}` through `Public*` resources (rnc-1 F2, R6). Test: `PublicSectorPagesTest`.
