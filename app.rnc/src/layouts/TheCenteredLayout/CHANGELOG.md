# Changelog — TheCenteredLayout

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.1 — 2026-09-17

### Fixed

- The barrel exported the layout as `TheTheCenteredLayout`, so `import { TheCenteredLayout } from '@/layouts'` found nothing. It is `TheCenteredLayout` now. Nothing imported the misspelt name (the layout is auto-imported), so no alias was kept.

## 1.1.0 — 2026-09-06

### Added

- `data-route-fade` on the root. This layout has no navigation to hold steady, so the whole page
  is the part that travels between routes — App.vue's scoped route-fade fades all of it.

### Changed

- `min-h-dvh` instead of `min-h-screen`: on a mobile browser `100vh` is the viewport with the
  address bar hidden, so the layout was taller than the screen it was centring inside.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
