# Changelog — Site Module

## 1.2.0 — 2026-11-07

### Changed

- `useTracking().track(type, sector, leadToken)` sends a diagnostic step's `lead_token` (AM2), and `visitContext()` exposes the visitor and source (rnc-1 F3).

### Removed

- The `/diagnostico` placeholder view and its `site.diagnostic` keys: the Diagnostic module owns the route now.

## 1.1.0 — 2026-11-07

### Added

- The landing (hero, `MCbsCountdown`, proof points, "o que muda", `MReformaTimeline` 2026–2033, the sector grid, the closing CTA) and `MDiagnosticPreview` (rnc-1 F2, R5).
- `SectorView` at `/reforma/:slug` (R6) with its dated facts, sources, CTA and the other sectors; the `/diagnostico` placeholder (W12).
- Services `GetPublicFirmService`, `GetSectorPagesService`, `GetSectorPageService`, `RecordEventService` (beacon); composables `usePublicFirm`, `useTracking` (R7), `useSeo` (R8).
- E2E `e2e/site/site.spec.ts` (A3, plus 390/1440 px overflow).

### Changed

- `SiteRoutes` moved to the module's `routes.ts` and gained the sector and diagnostic routes; route names in `SITE_ROUTE_NAMES`.

## 1.0.0 — 2026-11-07

### Added

- The module, `SiteRoutes` (`/`, `site-home`, open to everyone) and a placeholder `LandingView` in `ThePublicLayout` (rnc-1 F1, R3, AM15).
