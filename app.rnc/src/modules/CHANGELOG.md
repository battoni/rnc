# Changelog — src/modules/

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-16

### Fixed

- "Current Modules" listed five of seven: `Idea` and `Tip` were missing, and `Showcase` was "SUPERADMIN only" — it is gated on `design.read` since the Design group became a permission.

## 1.1.1 — 2026-09-15

### Fixed

- Showcase demo forms (`MShowcaseLongForm` / `MShowcaseShortForm`): `InputText` uses `id`, not
  `inputId`, so labels and password managers bind to the real input.

### Removed

- Showcase `guards.ts` (`superAdminGuard`) — unused after Showcase routes stopped gating on it.

## 1.1.0 — 2026-07-13

### Added

- `Personalization` module (`@PersonalizationModule`) for global UI preferences.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
