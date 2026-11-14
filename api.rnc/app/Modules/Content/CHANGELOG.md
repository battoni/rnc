# Changelog — Content Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-14
- The six default sector pages moved from `SectorPageSeeder` to `Helpers/DefaultSectorPages`; `SectorPageService::startFirm()` + `SectorPageRepository::upsertSectorPage()` write them for any firm — the seeder (Franco) and `firm:create` (rnc-4 F3b1, R21). Tested by `tests/Feature/Firm/CreateFirmCommandTest`.

## 1.0.0 — 2026-11-07

### Added

- `sector_pages` table, `SectorPage` model (firm-scoped), `SectorPageSeeder` with the six sectors, `GET /api/public/sectors` and `GET /api/public/sectors/{slug}` through `Public*` resources (rnc-1 F2, R6). Test: `PublicSectorPagesTest`.
